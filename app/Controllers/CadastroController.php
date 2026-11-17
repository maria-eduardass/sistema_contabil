<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Request;
use App\Core\View;
use App\Services\CadastroService;
use App\Services\LimiteTentativas;
use App\Services\ValidadorCadastro;
use App\Services\ValidadorEmpresa;

/**
 * Tela pública "Criar conta": a pessoa cria a própria empresa e o próprio acesso de cliente.
 * Rotas públicas, mas o Router continua exigindo o token CSRF no POST. As regras (validação e
 * gravação segura) ficam em ValidadorCadastro e CadastroService; aqui só se conduz a requisição.
 */
final class CadastroController
{
    private const CAMPOS = ['nome', 'email', 'razao_social', 'cnpj', 'regime_tributario'];

    public function formulario(Request $req): void
    {
        $this->irParaPainelSeLogado();
        $this->exibir(200, array_fill_keys(self::CAMPOS, ''), []);
    }

    public function salvar(Request $req): void
    {
        $this->irParaPainelSeLogado();

        // Freio por sessão contra cadastro em massa: 5 tentativas a cada 10 minutos (cada envio conta,
        // com erro ou não). Quem descarta o cookie recomeça; um controle real exigiria e-mail confirmado/CAPTCHA.
        $_SESSION['limite_cadastro'] ??= [];
        $limite = new LimiteTentativas($_SESSION['limite_cadastro'], 5, 600);
        $agora = time();

        $entrada = [];
        foreach ([...self::CAMPOS, 'senha', 'senha_confirmacao'] as $campo) {
            $entrada[$campo] = $req->post($campo) ?? '';
        }
        // A senha NUNCA volta para a tela.
        $valores = array_intersect_key($entrada, array_flip(self::CAMPOS));

        if ($limite->bloqueado($agora)) {
            $minutos = (int) ceil($limite->segundosRestantes($agora) / 60);
            $this->exibir(429, $valores, ['geral' => "Muitas tentativas de cadastro. Aguarde $minutos minuto(s) e tente novamente."]);
            return;
        }
        $limite->registrarFalha($agora);

        $r = ValidadorCadastro::validar($entrada);
        $erros = $r['erros'];
        if ($erros === []) {
            $resultado = CadastroService::cadastrar(Database::conexao(), $r['dados']);
            $erros = $resultado['erros'];
            if ($erros === []) {
                Auth::entrar($resultado['usuario']); // já entra logada (o ID da sessão é renovado)
                Flash::definir('sucesso', 'Conta criada! Você já está dentro. Seu contador verá a sua empresa e poderá cadastrar as suas obrigações.');
                redirecionar('dashboard/index');
            }
        }

        $this->exibir(422, $valores, $erros);
    }

    /** Quem já está logado não precisa criar conta. */
    private function irParaPainelSeLogado(): void
    {
        if (Auth::usuario() !== null) {
            redirecionar('dashboard/index');
        }
    }

    /**
     * @param array<string,string> $valores
     * @param array<string,string> $erros
     */
    private function exibir(int $status, array $valores, array $erros): void
    {
        http_response_code($status);
        View::exibir('cadastro/form', [
            'titulo' => 'Criar conta',
            'valores' => $valores,
            'erros' => $erros,
            'regimes' => ValidadorEmpresa::REGIMES,
            'auth' => 'larga', // o formulário é mais largo que a tela de login
        ]);
    }
}

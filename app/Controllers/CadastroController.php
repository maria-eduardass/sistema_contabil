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
    /**
     * Campos de texto que voltam preenchidos na tela depois de um erro. As senhas ficam DE FORA de propósito
     * (ver salvar): nunca devolver uma senha digitada para o HTML, nem escapada.
     */
    private const CAMPOS = ['nome', 'email', 'cpf', 'telefone', 'razao_social', 'cnpj', 'regime_tributario'];

    /** GET: mostra o formulário vazio (200). Quem já está logado é mandado ao painel. */
    public function formulario(Request $req): void
    {
        $this->irParaPainelSeLogado();
        $this->exibir(200, array_fill_keys(self::CAMPOS, ''), []);
    }

    /**
     * POST: o passo a passo, na ordem em que importa.
     *  1. Quem já está logado vai ao painel (não faz sentido criar outra conta).
     *  2. O token CSRF já foi exigido pelo Router antes de chegar aqui (vale para todo POST).
     *  3. Limite de envios por sessão, conferido ANTES de qualquer trabalho caro (validar e gravar).
     *  4. Validação dos campos (pura, sem banco) e depois a gravação atômica (CadastroService).
     *  5. Sucesso: já entra logado e vai ao painel. Falha: mostra o formulário de novo, com o status certo
     *     (422 = dados recusados, 429 = muitas tentativas).
     */
    public function salvar(Request $req): void
    {
        $this->irParaPainelSeLogado();

        // Freio por sessão contra cadastro em massa: 5 tentativas a cada 10 minutos (cada envio conta,
        // com erro ou não). Quem descarta o cookie recomeça; um controle real exigiria e-mail confirmado/CAPTCHA.
        $_SESSION['limite_cadastro'] ??= [];
        $limite = new LimiteTentativas($_SESSION['limite_cadastro'], 5, 600);
        $agora = time();

        // Lê só os campos conhecidos (lista fixa): qualquer outro valor enviado, como perfil ou empresa_id,
        // simplesmente nunca é lido, então não há como forjar o perfil ou a empresa pelo formulário.
        $entrada = [];
        foreach ([...self::CAMPOS, 'senha', 'senha_confirmacao'] as $campo) {
            $entrada[$campo] = $req->post($campo) ?? '';
        }
        // A senha NUNCA volta para a tela: $valores é a entrada sem as duas senhas.
        $valores = array_intersect_key($entrada, array_flip(self::CAMPOS));

        if ($limite->bloqueado($agora)) {
            $minutos = (int) ceil($limite->segundosRestantes($agora) / 60);
            $this->exibir(429, $valores, ['geral' => "Muitas tentativas de cadastro. Aguarde $minutos minuto(s) e tente novamente."]);
            return;
        }
        // Conta o envio JÁ AQUI (e não só quando falha): assim também limita quem cria muitas contas válidas seguidas.
        $limite->registrarFalha($agora);

        $r = ValidadorCadastro::validar($entrada);
        $erros = $r['erros'];
        if ($erros === []) {
            $resultado = CadastroService::cadastrar(Database::conexao(), $r['dados']);
            $erros = $resultado['erros'];
            if ($erros === []) {
                // Auth::entrar renova o ID da sessão (contra fixação de sessão) e troca o token CSRF. O redirecionar()
                // encerra a requisição, por isso o exibir() lá embaixo só roda quando algo deu errado.
                Auth::entrar($resultado['usuario']);
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
            'scripts' => ['assets/js/cadastro.js'],
        ]);
    }
}

<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\View;
use App\Models\Empresa;
use App\Models\Usuario;
use App\Services\ValidadorUsuario;
use PDOException;

/** Cadastro de usuários do perfil cliente, sempre vinculados a uma empresa. */
final class UsuarioController
{
    private const MSG_EMAIL_DUPLICADO = 'Já existe um usuário com este e-mail.';

    public function criar(Request $req): void
    {
        $empresa = $this->empresaAtivaOu404($req->getInt('empresa_id'));
        $this->formulario(200, $empresa, ['nome' => '', 'email' => ''], []);
    }

    public function salvar(Request $req): void
    {
        $empresa = $this->empresaAtivaOu404($req->postInt('empresa_id'));
        $modelo = new Usuario(Database::conexao());

        $entrada = [
            'nome' => $req->post('nome') ?? '',
            'email' => $req->post('email') ?? '',
            'senha' => $req->post('senha') ?? '',
        ];
        $r = ValidadorUsuario::validar($entrada);
        $erros = $r['erros'];
        $d = $r['dados'];

        if (!isset($erros['email']) && $modelo->emailExiste($d['email'])) {
            $erros['email'] = self::MSG_EMAIL_DUPLICADO;
        }

        if ($erros === []) {
            try {
                $modelo->criarCliente((int) $empresa['id'], $d['nome'], $d['email'], $d['senha']);
                Flash::definir('sucesso', 'Usuário cadastrado. Ele já pode entrar com o e-mail e a senha informados.');
                redirecionar('empresas/ver', ['id' => (int) $empresa['id']]);
            } catch (PDOException $e) {
                // Corrida: o UNIQUE do banco é a palavra final sobre e-mail duplicado.
                if ((string) $e->getCode() !== '23000') {
                    throw $e;
                }
                $erros['email'] = self::MSG_EMAIL_DUPLICADO;
            }
        }
        // A senha nunca volta para o formulário.
        $this->formulario(422, $empresa, ['nome' => $entrada['nome'], 'email' => $entrada['email']], $erros);
    }

    /** @return array<string,mixed> */
    private function empresaAtivaOu404(?int $id): array
    {
        $empresa = $id === null ? null : (new Empresa(Database::conexao()))->buscar($id);
        if ($empresa === null || (int) $empresa['ativa'] !== 1) {
            throw new HttpException(404); // empresa inativa não recebe novos usuários
        }
        return $empresa;
    }

    /**
     * @param array<string,mixed> $empresa
     * @param array<string,string> $valores
     * @param array<string,string> $erros
     */
    private function formulario(int $status, array $empresa, array $valores, array $erros): void
    {
        http_response_code($status);
        View::exibir('usuarios/form', [
            'titulo' => 'Novo usuário',
            'empresa' => $empresa,
            'valores' => $valores,
            'erros' => $erros,
        ]);
    }
}

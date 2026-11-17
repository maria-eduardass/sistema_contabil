<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Guarda;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\View;
use App\Models\Empresa;
use App\Models\Usuario;
use App\Services\ValidadorEmpresa;
use PDOException;

final class EmpresaController
{
    private const CAMPOS = ['razao_social', 'cnpj', 'regime_tributario', 'email_contato'];
    private const MSG_CNPJ_DUPLICADO = 'Já existe uma empresa com este CNPJ.';

    public function index(Request $req): void
    {
        $busca = mb_substr(trim($req->get('busca') ?? ''), 0, 100);
        View::exibir('empresas/index', [
            'titulo' => 'Empresas',
            'empresas' => $this->modelo()->listar($busca),
            'busca' => $busca,
        ]);
    }

    public function criar(Request $req): void
    {
        $this->formulario(200, 'Nova empresa', url('empresas/salvar'), array_fill_keys(self::CAMPOS, ''), [], null);
    }

    public function salvar(Request $req): void
    {
        [$entrada, $dados, $erros] = $this->validar($req, null);

        if ($erros === []) {
            try {
                $this->modelo()->criar($dados);
                Flash::definir('sucesso', 'Empresa cadastrada com sucesso.');
                redirecionar('empresas/index');
            } catch (PDOException $e) {
                // Corrida: dois cadastros simultâneos passam na checagem; o UNIQUE do banco decide.
                if ((string) $e->getCode() !== '23000') {
                    throw $e;
                }
                $erros['cnpj'] = self::MSG_CNPJ_DUPLICADO;
            }
        }
        $this->formulario(422, 'Nova empresa', url('empresas/salvar'), $entrada, $erros, null);
    }

    public function editar(Request $req): void
    {
        $empresa = $this->empresaOu404($req->getInt('id'));
        $valores = array_intersect_key($empresa, array_flip(self::CAMPOS));
        $valores['cnpj'] = formatarCnpj((string) $valores['cnpj']);

        $this->formulario(200, 'Editar empresa', url('empresas/atualizar'), $valores, [], (int) $empresa['id']);
    }

    public function atualizar(Request $req): void
    {
        $empresa = $this->empresaOu404($req->postInt('id'));
        $id = (int) $empresa['id'];
        [$entrada, $dados, $erros] = $this->validar($req, $id);

        if ($erros === []) {
            try {
                $this->modelo()->atualizar($id, $dados);
                Flash::definir('sucesso', 'Empresa atualizada com sucesso.');
                redirecionar('empresas/index');
            } catch (PDOException $e) {
                if ((string) $e->getCode() !== '23000') {
                    throw $e;
                }
                $erros['cnpj'] = self::MSG_CNPJ_DUPLICADO;
            }
        }
        $this->formulario(422, 'Editar empresa', url('empresas/atualizar'), $entrada, $erros, $id);
    }

    public function inativar(Request $req): void
    {
        $empresa = $this->empresaOu404($req->postInt('id'));
        $this->modelo()->inativar((int) $empresa['id']);
        Flash::definir('sucesso', 'Empresa inativada. O histórico foi preservado.');
        redirecionar('empresas/index');
    }

    /** Acessível ao contador e ao cliente DA empresa (a regra por objeto vem antes de consultar o banco). */
    public function ver(Request $req): void
    {
        $id = $req->getInt('id');
        $usuario = Auth::usuario();
        if ($id === null) {
            throw new HttpException(404);
        }
        Guarda::exigirEmpresa($usuario, $id);

        $ehContador = $usuario['perfil'] === 'contador';
        View::exibir('empresas/ver', [
            'titulo' => 'Empresa',
            'scripts' => ['assets/js/termometro.js'],
            'empresa' => $this->empresaOu404($id),
            'ehContador' => $ehContador,
            // A lista de usuários é gestão do escritório: o cliente não vê quem mais tem acesso.
            'usuarios' => $ehContador ? (new Usuario(Database::conexao()))->listarPorEmpresa($id) : [],
        ]);
    }

    private function modelo(): Empresa
    {
        return new Empresa(Database::conexao());
    }

    /** @return array<string,mixed> */
    private function empresaOu404(?int $id): array
    {
        $empresa = $id === null ? null : $this->modelo()->buscar($id);
        if ($empresa === null) {
            throw new HttpException(404);
        }
        return $empresa;
    }

    /** @return array{0:array<string,string>,1:array<string,string>,2:array<string,string>} [entrada crua, dados limpos, erros] */
    private function validar(Request $req, ?int $idAtual): array
    {
        $entrada = [];
        foreach (self::CAMPOS as $campo) {
            $entrada[$campo] = $req->post($campo) ?? '';
        }
        $r = ValidadorEmpresa::validar($entrada);
        if (!isset($r['erros']['cnpj']) && $this->modelo()->cnpjExiste($r['dados']['cnpj'], $idAtual)) {
            $r['erros']['cnpj'] = self::MSG_CNPJ_DUPLICADO;
        }

        return [$entrada, $r['dados'], $r['erros']];
    }

    /**
     * @param array<string,string> $valores
     * @param array<string,string> $erros
     */
    private function formulario(int $status, string $titulo, string $acao, array $valores, array $erros, ?int $id): void
    {
        http_response_code($status);
        View::exibir('empresas/form', [
            'titulo' => $titulo,
            'acao' => $acao,
            'valores' => $valores,
            'erros' => $erros,
            'id' => $id,
            'regimes' => ValidadorEmpresa::REGIMES,
        ]);
    }
}

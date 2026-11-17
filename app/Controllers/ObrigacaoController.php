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
use App\Models\Obrigacao;
use App\Models\TipoObrigacao;
use App\Services\RegraDeNegocio;
use App\Services\StatusService;
use App\Services\ValidadorObrigacao;
use DateTimeImmutable;
use PDOException;

final class ObrigacaoController
{
    private const CAMPOS = ['tipo_obrigacao_id', 'competencia', 'data_vencimento', 'valor', 'observacao'];
    private const MYSQL_DUPLICADO = 1062;

    /** Contador vê qualquer empresa; cliente, só a própria (conferido antes de consultar o banco). */
    public function index(Request $req): void
    {
        $id = $req->getInt('empresa_id');
        if ($id === null) {
            throw new HttpException(404);
        }
        Guarda::exigirEmpresa(Auth::usuario(), $id);

        $this->exibirLista($id);
    }

    /** Registra o pagamento (só contador). A empresa vem da própria obrigação, nunca do formulário. */
    public function pagar(Request $req): void
    {
        $obrigacao = $this->obrigacaoOu404($req->postInt('id'));
        $this->executarAcao(
            (int) $obrigacao['empresa_id'],
            'Pagamento registrado.',
            fn () => $this->servico()->pagar((int) $obrigacao['id'], $req->post('data_pagamento') ?? '', new DateTimeImmutable())
        );
    }

    public function excluir(Request $req): void
    {
        $obrigacao = $this->obrigacaoOu404($req->postInt('id'));
        $this->executarAcao(
            (int) $obrigacao['empresa_id'],
            'Obrigação excluída.',
            fn () => $this->servico()->excluir((int) $obrigacao['id'])
        );
    }

    /**
     * Roda a ação. Sucesso: mensagem + redirect (POST -> GET). Regra violada: mostra a lista de
     * novo com o status HTTP da regra (409/422) e a mensagem de erro.
     */
    private function executarAcao(int $empresaId, string $sucesso, callable $acao): void
    {
        try {
            $acao();
        } catch (RegraDeNegocio $e) {
            Flash::definir('erro', $e->getMessage());
            http_response_code($e->codigoHttp);
            $this->exibirLista($empresaId);
            return;
        }
        Flash::definir('sucesso', $sucesso);
        redirecionar('obrigacoes/index', ['empresa_id' => $empresaId]);
    }

    private function exibirLista(int $empresaId): void
    {
        $this->servico()->sincronizar(new DateTimeImmutable()); // garante "atrasada" em dia, sem cron
        View::exibir('obrigacoes/index', [
            'titulo' => 'Obrigações',
            'empresa' => $this->empresaOu404($empresaId),
            'obrigacoes' => (new Obrigacao(Database::conexao()))->listarPorEmpresa($empresaId),
            'ehContador' => Auth::usuario()['perfil'] === 'contador',
        ]);
    }

    private function servico(): StatusService
    {
        return new StatusService(new Obrigacao(Database::conexao()));
    }

    /** @return array<string,mixed> */
    private function obrigacaoOu404(?int $id): array
    {
        $obrigacao = $id === null ? null : (new Obrigacao(Database::conexao()))->buscar($id);
        if ($obrigacao === null) {
            throw new HttpException(404);
        }
        return $obrigacao;
    }

    public function criar(Request $req): void
    {
        $empresa = $this->empresaAtivaOu404($req->getInt('empresa_id'));
        $this->formulario(200, $empresa, array_fill_keys(self::CAMPOS, ''), []);
    }

    public function salvar(Request $req): void
    {
        $empresa = $this->empresaAtivaOu404($req->postInt('empresa_id'));
        $empresaId = (int) $empresa['id'];
        $modelo = new Obrigacao(Database::conexao());

        $entrada = [];
        foreach (self::CAMPOS as $campo) {
            $entrada[$campo] = $req->post($campo) ?? '';
        }
        $tiposValidos = array_map('intval', array_column((new TipoObrigacao(Database::conexao()))->listar(), 'id'));
        $r = ValidadorObrigacao::validar($entrada, $tiposValidos, new DateTimeImmutable());
        $erros = $r['erros'];
        $dados = ['empresa_id' => $empresaId] + $r['dados'];

        $status = 422;
        if ($erros === [] && $modelo->existe($empresaId, $dados['tipo_obrigacao_id'], $dados['competencia'])) {
            $erros['competencia'] = $this->mensagemDuplicada();
            $status = 409;
        }

        if ($erros === []) {
            try {
                $modelo->criar($dados);
                Flash::definir('sucesso', 'Obrigação cadastrada com sucesso.');
                redirecionar('obrigacoes/index', ['empresa_id' => $empresaId]);
            } catch (PDOException $e) {
                // Corrida entre dois cadastros iguais: o UNIQUE do banco decide. Outros erros sobem (500).
                if (($e->errorInfo[1] ?? 0) !== self::MYSQL_DUPLICADO) {
                    throw $e;
                }
                $erros['competencia'] = $this->mensagemDuplicada();
                $status = 409;
            }
        }
        $this->formulario($status, $empresa, $entrada, $erros);
    }

    private function mensagemDuplicada(): string
    {
        return 'Já existe uma obrigação deste tipo para esta competência nesta empresa.';
    }

    /** @return array<string,mixed> */
    private function empresaOu404(int $id): array
    {
        $empresa = (new Empresa(Database::conexao()))->buscar($id);
        if ($empresa === null) {
            throw new HttpException(404);
        }
        return $empresa;
    }

    /** @return array<string,mixed> */
    private function empresaAtivaOu404(?int $id): array
    {
        $empresa = $id === null ? null : (new Empresa(Database::conexao()))->buscar($id);
        if ($empresa === null || (int) $empresa['ativa'] !== 1) {
            throw new HttpException(404); // empresa inativa não recebe novas obrigações
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
        View::exibir('obrigacoes/form', [
            'titulo' => 'Nova obrigação',
            'empresa' => $empresa,
            'valores' => $valores,
            'erros' => $erros,
            'tipos' => (new TipoObrigacao(Database::conexao()))->listar(),
        ]);
    }
}

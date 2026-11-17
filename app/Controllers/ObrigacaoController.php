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
use App\Services\MensagemCobranca;
use App\Services\RegraDeNegocio;
use App\Services\SimulacaoTermometro;
use App\Services\StatusService;
use App\Services\TermometroEmpresa;
use App\Services\ValidadorObrigacao;
use DateTimeImmutable;
use PDOException;

/**
 * Obrigações fiscais de uma empresa.
 *
 * QUEM PODE O QUÊ: o contador age em qualquer empresa; o DONO da empresa (cliente) cria, paga e exclui as obrigações
 * da PRÓPRIA empresa e de nenhuma outra. As rotas são "logado" (não "contador"), então a única barreira contra mexer
 * na empresa de outra pessoa é o Guarda::exigirEmpresa chamado AQUI, em toda ação, antes de consultar o banco.
 * (Um teste em RotasTeste confere que essas chamadas continuam existindo.)
 *
 * Toda ação que muda dados termina dizendo como o termômetro mudou ("foi de 26% para 87%"), para a pessoa ver a
 * ligação entre o que ela fez e o risco. O cálculo vem do mesmo serviço do termômetro de verdade.
 */
final class ObrigacaoController
{
    private const CAMPOS = ['tipo_obrigacao_id', 'competencia', 'data_vencimento', 'valor', 'observacao'];
    private const MYSQL_DUPLICADO = 1062;

    /** Lista da empresa, com o termômetro no topo. Contador vê qualquer uma; o dono, só a sua. */
    public function index(Request $req): void
    {
        $id = $req->getInt('empresa_id');
        if ($id === null) {
            throw new HttpException(404);
        }
        Guarda::exigirEmpresa(Auth::usuario(), $id);

        $this->exibirLista($id);
    }

    /**
     * Menu lateral: só as guias pagas (?filtro=pagas) ou só as em aberto (?filtro=abertas). O contador vê todas as empresas
     * ativas; o cliente, só a própria (a empresa vem da sessão, nunca da URL).
     */
    public function situacao(Request $req): void
    {
        $pagas = $req->get('filtro') === 'pagas';
        $usuario = Auth::usuario();
        $empresaId = $usuario['perfil'] === 'contador' ? null : (int) $usuario['empresa_id'];

        View::exibir('obrigacoes/situacao', [
            'titulo' => $pagas ? 'Obrigações pagas' : 'Obrigações em aberto',
            'pagas' => $pagas,
            'filtro' => $pagas ? 'pagas' : 'abertas',
            'obrigacoes' => (new Obrigacao(Database::conexao()))->listarPorSituacao($pagas, $empresaId),
        ]);
    }

    /** Registra o pagamento. A empresa vem da própria obrigação, nunca do formulário. */
    public function pagar(Request $req): void
    {
        $obrigacao = $this->obrigacaoDoUsuarioOu404($req->postInt('id'));
        $this->executarAcao(
            (int) $obrigacao['empresa_id'],
            'Pagamento registrado.',
            fn () => $this->servico()->pagar((int) $obrigacao['id'], $req->post('data_pagamento') ?? '', new DateTimeImmutable())
        );
    }

    public function excluir(Request $req): void
    {
        $obrigacao = $this->obrigacaoDoUsuarioOu404($req->postInt('id'));
        $this->executarAcao(
            (int) $obrigacao['empresa_id'],
            'Obrigação excluída.',
            fn () => $this->servico()->excluir((int) $obrigacao['id'])
        );
    }

    /** Formulário de nova obrigação, com a prévia do termômetro (assets/js/obrigacao-form.js). */
    public function criar(Request $req): void
    {
        $empresaId = $req->getInt('empresa_id');
        if ($empresaId === null) {
            throw new HttpException(404);
        }
        Guarda::exigirEmpresa(Auth::usuario(), $empresaId); // dono só da própria empresa; contador de qualquer uma

        $this->formulario(200, $this->empresaAtivaOu404($empresaId), array_fill_keys(self::CAMPOS, ''), []);
    }

    public function salvar(Request $req): void
    {
        $empresaId = $req->postInt('empresa_id');
        if ($empresaId === null) {
            throw new HttpException(404);
        }
        // O id da empresa vem do formulário, então ele PRECISA ser conferido: sem isso, um cliente trocaria o número
        // no HTML e cadastraria obrigações na empresa de outra pessoa.
        Guarda::exigirEmpresa(Auth::usuario(), $empresaId);

        $empresa = $this->empresaAtivaOu404($empresaId);
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
                $antes = $this->termometro($empresaId);
                $modelo->criar($dados);
                Flash::definir('sucesso', 'Obrigação cadastrada. ' . SimulacaoTermometro::resumoImpacto($antes, $this->termometro($empresaId)));
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

    /**
     * Roda a ação. Sucesso: mensagem (com o efeito no termômetro) + redirect (POST -> GET). Regra violada:
     * mostra a lista de novo com o status HTTP da regra (409/422) e a mensagem de erro.
     */
    private function executarAcao(int $empresaId, string $sucesso, callable $acao): void
    {
        $antes = $this->termometro($empresaId);
        try {
            $acao();
        } catch (RegraDeNegocio $e) {
            Flash::definir('erro', $e->getMessage());
            http_response_code($e->codigoHttp);
            $this->exibirLista($empresaId);
            return;
        }
        Flash::definir('sucesso', $sucesso . ' ' . SimulacaoTermometro::resumoImpacto($antes, $this->termometro($empresaId)));
        redirecionar('obrigacoes/index', ['empresa_id' => $empresaId]);
    }

    private function exibirLista(int $empresaId): void
    {
        $hoje = new DateTimeImmutable();
        $this->servico()->sincronizar($hoje); // garante "atrasada" em dia, sem cron
        $empresa = $this->empresaOu404($empresaId);
        $obrigacoes = (new Obrigacao(Database::conexao()))->listarPorEmpresa($empresaId);
        $ehContador = Auth::usuario()['perfil'] === 'contador';

        if ($ehContador) {
            // Botão "Cobrar no WhatsApp" e valor atualizado com multa: ferramenta do escritório, não do cliente.
            $obrigacoes = MensagemCobranca::enriquecerLista($obrigacoes, (string) $empresa['razao_social'], $hoje);
        }

        View::exibir('obrigacoes/index', [
            'titulo' => 'Obrigações',
            'empresa' => $empresa,
            'obrigacoes' => $obrigacoes,
            'ehContador' => $ehContador,
            'podeAgir' => (int) $empresa['ativa'] === 1, // empresa inativa só consulta; não recebe nem altera guias
            'scripts' => ['assets/js/termometro.js'],
        ]);
    }

    /** O termômetro atual da empresa (mesmo cálculo da tela e da API), para comparar antes e depois de uma ação. */
    private function termometro(int $empresaId): array
    {
        return TermometroEmpresa::montar((new Obrigacao(Database::conexao()))->listarPorEmpresa($empresaId), new DateTimeImmutable());
    }

    private function servico(): StatusService
    {
        return new StatusService(new Obrigacao(Database::conexao()));
    }

    /**
     * Busca a obrigação E confere que o usuário pode mexer nela. Para o dono, uma obrigação que não existe e uma que é
     * de outra empresa dão o MESMO 403: assim ninguém descobre, testando ids, quais obrigações existem em outras empresas.
     * O contador recebe 404 para o que não existe (ele pode ver tudo, então não há o que esconder).
     *
     * @return array<string,mixed>
     */
    private function obrigacaoDoUsuarioOu404(?int $id): array
    {
        $usuario = Auth::usuario();
        $obrigacao = $id === null ? null : (new Obrigacao(Database::conexao()))->buscar($id);
        if ($obrigacao === null) {
            throw new HttpException($usuario['perfil'] === 'contador' ? 404 : 403);
        }
        Guarda::exigirEmpresa($usuario, (int) $obrigacao['empresa_id']);

        return $obrigacao;
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
            'scripts' => ['assets/js/obrigacao-form.js'],
        ]);
    }
}

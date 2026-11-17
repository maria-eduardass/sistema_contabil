<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Guarda;
use App\Core\HttpException;
use App\Core\Json;
use App\Core\Request;
use App\Models\Empresa;
use App\Models\Obrigacao;
use App\Services\SimulacaoTermometro;
use App\Services\StatusService;
use App\Services\TermometroEmpresa;
use DateTimeImmutable;

/** Dados do termômetro em JSON, consumidos por assets/js/termometro.js. */
final class TermometroController
{
    public function json(Request $req): void
    {
        $empresaId = $req->getInt('empresa_id');
        if ($empresaId === null) {
            throw new HttpException(404);
        }
        // Autorização por objeto ANTES de consultar o banco: cliente só vê a própria empresa.
        Guarda::exigirEmpresa(Auth::usuario(), $empresaId);

        $pdo = Database::conexao();
        if ((new Empresa($pdo))->buscar($empresaId) === null) {
            throw new HttpException(404);
        }

        $hoje = new DateTimeImmutable();
        $obrigacoes = new Obrigacao($pdo);
        (new StatusService($obrigacoes))->sincronizar($hoje);

        Json::enviar(TermometroEmpresa::montar($obrigacoes->listarPorEmpresa($empresaId), $hoje));
    }

    /**
     * Prévia "e se eu cadastrar esta obrigação?" para o formulário de nova obrigação. Só leitura: nada é gravado.
     * Mesma autorização do termômetro (dono só da própria empresa). Dados incompletos NÃO são erro HTTP: a tela
     * chama isto a cada tecla, então um formulário pela metade devolve 200 com a lista "erros" e só o termômetro atual.
     */
    public function simular(Request $req): void
    {
        $empresaId = $req->getInt('empresa_id');
        if ($empresaId === null) {
            throw new HttpException(404);
        }
        Guarda::exigirEmpresa(Auth::usuario(), $empresaId);

        $pdo = Database::conexao();
        if ((new Empresa($pdo))->buscar($empresaId) === null) {
            throw new HttpException(404);
        }

        Json::enviar(SimulacaoTermometro::simular(
            (new Obrigacao($pdo))->listarPorEmpresa($empresaId),
            $req->get('vencimento') ?? '',
            $req->get('valor') ?? '',
            new DateTimeImmutable()
        ));
    }
}

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
}

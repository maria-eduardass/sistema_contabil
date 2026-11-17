<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\View;
use App\Models\Empresa;
use App\Models\Obrigacao;
use App\Services\ProximasObrigacoes;
use App\Services\RankingRisco;
use App\Services\ResumoFinanceiro;
use App\Services\StatusService;
use DateTimeImmutable;

/** Painéis por perfil. A regra de cálculo mora nos Services; aqui só se carrega e se entrega. */
final class DashboardController
{
    /** Porta de entrada: cada perfil vai para o seu painel. */
    public function index(Request $req): void
    {
        $perfil = Auth::usuario()['perfil'] ?? '';
        redirecionar($perfil === 'contador' ? 'dashboard/contador' : 'dashboard/cliente');
    }

    /** Resumo financeiro + ranking de risco de todas as empresas ativas, da mais crítica para a mais tranquila. */
    public function contador(Request $req): void
    {
        $pdo = Database::conexao();
        $hoje = new DateTimeImmutable();
        $obrigacoes = new Obrigacao($pdo);
        (new StatusService($obrigacoes))->sincronizar($hoje); // atrasos em dia antes de calcular

        $abertas = $obrigacoes->listarEmAbertoDeEmpresasAtivas();
        $ranking = RankingRisco::montar((new Empresa($pdo))->listarAtivas(), $abertas, $hoje);

        // Tudo o que o resumo financeiro precisa, numa lista só: as guias em aberto de TODAS as empresas
        // ativas (vêm agrupadas por empresa, daí o array_merge(...) que "achata") + as pagas neste mês.
        // Duas consultas no total, não importa quantas empresas existam.
        $linhas = array_merge(
            $abertas === [] ? [] : array_merge(...array_values($abertas)),
            $obrigacoes->listarPagasNoPeriodo($hoje->format('Y-m-01'), $hoje->format('Y-m-d'))
        );

        View::exibir('dashboard/contador', [
            'titulo' => 'Painel do contador',
            'usuario' => Auth::usuario(),
            'ranking' => $ranking['empresas'],
            'resumo' => $ranking['resumo'],
            'financeiro' => ResumoFinanceiro::calcular($linhas, $hoje),
            'mes' => $hoje->format('m/Y'),
        ]);
    }

    /** Resumo financeiro, termômetro (via JS) e as próximas obrigações da empresa do próprio cliente. */
    public function cliente(Request $req): void
    {
        $usuario = Auth::usuario();
        $empresaId = $usuario['empresa_id'] ?? null;
        if ($empresaId === null) {
            throw new HttpException(403); // cliente sempre tem empresa; sem ela não há o que mostrar
        }

        $pdo = Database::conexao();
        $hoje = new DateTimeImmutable();
        $obrigacoes = new Obrigacao($pdo);
        (new StatusService($obrigacoes))->sincronizar($hoje);
        $linhas = $obrigacoes->listarPorEmpresa($empresaId);

        View::exibir('dashboard/cliente', [
            'titulo' => 'Minha empresa',
            'usuario' => $usuario,
            'empresa' => (new Empresa($pdo))->buscar($empresaId),
            'proximas' => ProximasObrigacoes::de($linhas, $hoje, 5),
            'financeiro' => ResumoFinanceiro::calcular($linhas, $hoje),
            'mes' => $hoje->format('m/Y'),
            'scripts' => ['assets/js/termometro.js'],
        ]);
    }
}

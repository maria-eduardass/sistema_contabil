<?php
declare(strict_types=1);

use App\Services\RankingRisco;

function empresaRankingTeste(int $id, string $nome): array
{
    return ['id' => $id, 'razao_social' => $nome, 'cnpj' => '11222333000181'];
}

function linhaRankingTeste(int $id, string $vencimento, string $valorCentavos, string $status = 'pendente'): array
{
    return ['id' => $id, 'tipo_sigla' => 'DAS', 'data_vencimento' => $vencimento, 'valor_centavos' => $valorCentavos, 'status' => $status];
}

function rankingTeste(array $empresas, array $porEmpresa): array
{
    return RankingRisco::montar($empresas, $porEmpresa, new DateTimeImmutable('2026-11-17'));
}

/** Seed: 1 = Padaria (2 atrasadas + 1 a vencer), 2 = Tech (a vencer), 3 = Clínica (longe). */
function seedRankingTeste(): array
{
    return [
        [empresaRankingTeste(3, 'Clínica Bem Viver S/S'), empresaRankingTeste(1, 'Padaria Pão Quente ME'), empresaRankingTeste(2, 'Tech Soluções Ltda')], // fora de ordem de propósito
        [
            1 => [linhaRankingTeste(1, '2026-10-28', '185000', 'atrasado'), linhaRankingTeste(2, '2026-11-12', '62050', 'atrasado'), linhaRankingTeste(3, '2026-11-20', '0')],
            2 => [linhaRankingTeste(4, '2026-11-19', '320000')],
            3 => [linhaRankingTeste(6, '2026-12-12', '98000')],
        ],
    ];
}

teste('Ranking: ordena do maior para o menor risco (Padaria 100, Tech 26, Clínica 0)', function () {
    [$empresas, $porEmpresa] = seedRankingTeste();
    $r = rankingTeste($empresas, $porEmpresa);
    afirmarIgual(['Padaria Pão Quente ME', 'Tech Soluções Ltda', 'Clínica Bem Viver S/S'], array_column($r['empresas'], 'razao_social'));
    afirmarIgual([100, 26, 0], array_column($r['empresas'], 'score'));
});

teste('Ranking: cada linha traz faixa, multa estimada e contagens de atrasadas e em aberto', function () {
    [$empresas, $porEmpresa] = seedRankingTeste();
    $padaria = rankingTeste($empresas, $porEmpresa)['empresas'][0];
    afirmarIgual(1, $padaria['id']);
    afirmarIgual('critico', $padaria['faixa']);
    afirmarIgual('Risco crítico', $padaria['faixa_rotulo']);
    afirmarIgual(14570, $padaria['multa_total_centavos']);
    afirmarIgual(2, $padaria['atrasadas']);
    afirmarIgual(3, $padaria['em_aberto']);
    afirmarIgual('11222333000181', $padaria['cnpj']);
});

teste('Ranking: empresa sem obrigações em aberto aparece com score 0, no fim', function () {
    $r = rankingTeste(
        [empresaRankingTeste(1, 'Sem Pendências Ltda'), empresaRankingTeste(2, 'Com Atraso Ltda')],
        [2 => [linhaRankingTeste(1, '2026-11-10', '100000', 'atrasado')]]
    );
    afirmarIgual(['Com Atraso Ltda', 'Sem Pendências Ltda'], array_column($r['empresas'], 'razao_social'));
    afirmarIgual(0, $r['empresas'][1]['score']);
    afirmarIgual(0, $r['empresas'][1]['em_aberto']);
    afirmarIgual('baixo', $r['empresas'][1]['faixa']);
});

teste('Ranking: empate de score desempata pela maior multa e depois pelo nome', function () {
    $atrasada = fn (int $id, string $valor) => [linhaRankingTeste($id, '2026-10-28', $valor, 'atrasado')]; // 20 dias: risco 100
    $r = rankingTeste(
        [empresaRankingTeste(1, 'Beta'), empresaRankingTeste(2, 'Alfa'), empresaRankingTeste(3, 'Gama')],
        [1 => $atrasada(1, '100000'), 2 => $atrasada(2, '100000'), 3 => $atrasada(3, '200000')]
    );
    afirmarIgual(['Gama', 'Alfa', 'Beta'], array_column($r['empresas'], 'razao_social'));
});

teste('Ranking: obrigações de empresas que não estão na lista (ex.: inativas) são ignoradas', function () {
    $r = rankingTeste(
        [empresaRankingTeste(1, 'Ativa')],
        [1 => [linhaRankingTeste(1, '2026-12-30', '1000')], 99 => [linhaRankingTeste(2, '2026-10-01', '999999', 'atrasado')]]
    );
    afirmarIgual(1, count($r['empresas']));
    afirmarIgual(0, $r['resumo']['multa_total_centavos']);
});

teste('Ranking: resumo soma empresas, empresas em risco alto/crítico, atrasadas e multa', function () {
    [$empresas, $porEmpresa] = seedRankingTeste();
    afirmarIgual(
        ['total_empresas' => 3, 'em_risco' => 1, 'atrasadas' => 2, 'multa_total_centavos' => 14570],
        rankingTeste($empresas, $porEmpresa)['resumo']
    );
});

teste('Ranking: risco "alto" também conta como empresa em risco', function () {
    $r = rankingTeste([empresaRankingTeste(1, 'X')], [1 => [linhaRankingTeste(1, '2026-11-12', '1000', 'atrasado')]]); // 5 dias: 60 = alto
    afirmarIgual('alto', $r['empresas'][0]['faixa']);
    afirmarIgual(1, $r['resumo']['em_risco']);
});

teste('Ranking: sem empresas o resultado é vazio e zerado', function () {
    $r = rankingTeste([], []);
    afirmarIgual([], $r['empresas']);
    afirmarIgual(['total_empresas' => 0, 'em_risco' => 0, 'atrasadas' => 0, 'multa_total_centavos' => 0], $r['resumo']);
});

<?php
declare(strict_types=1);

use App\Services\ResumoFinanceiro;

// "Hoje" fixo: 2026-11-17 (terça). Linhas no formato do banco (valores como texto).
function linhaFinTeste(int $id, string $status, string $vencimento, string $valorCentavos, ?string $pagamento = null): array
{
    return [
        'id' => $id, 'status' => $status, 'data_vencimento' => $vencimento,
        'data_pagamento' => $pagamento, 'valor_centavos' => $valorCentavos,
    ];
}

function resumoFinTeste(array $linhas, string $hoje = '2026-11-17'): array
{
    return ResumoFinanceiro::calcular($linhas, new DateTimeImmutable($hoje));
}

/** O seed do projeto: Padaria (2 atrasadas + 1 a vencer), Tech (1 a vencer + 1 paga), Clínica (1 longe + 1 paga). */
function seedFinTeste(): array
{
    return [
        linhaFinTeste(1, 'atrasado', '2026-10-28', '185000'),
        linhaFinTeste(2, 'atrasado', '2026-11-12', '62050'),
        linhaFinTeste(3, 'pendente', '2026-11-20', '0'),
        linhaFinTeste(4, 'pendente', '2026-11-19', '320000'),
        linhaFinTeste(5, 'pago', '2026-11-07', '0', '2026-11-05'),
        linhaFinTeste(6, 'pendente', '2026-12-12', '98000'),
        linhaFinTeste(7, 'pago', '2026-11-09', '150000', '2026-11-08'),
    ];
}

teste('ResumoFinanceiro: dados do seed — em atraso R$ 2.470,50 (+ R$ 145,70 de encargos)', function () {
    $a = resumoFinTeste(seedFinTeste())['em_atraso'];
    afirmarIgual(2, $a['quantidade']);
    afirmarIgual(247050, $a['valor_centavos']);
    afirmarIgual(14570, $a['encargos_centavos']);
    afirmarIgual(261620, $a['total_atualizado_centavos']);
});

teste('ResumoFinanceiro: dados do seed — a vencer em 7 dias R$ 3.200,00 (2 guias)', function () {
    $v = resumoFinTeste(seedFinTeste())['a_vencer'];
    afirmarIgual(2, $v['quantidade']);
    afirmarIgual(320000, $v['valor_centavos']);
    afirmarIgual(7, $v['dias']);
});

teste('ResumoFinanceiro: dados do seed — pago no mês R$ 1.500,00 (2 guias)', function () {
    $p = resumoFinTeste(seedFinTeste())['pago_no_mes'];
    afirmarIgual(2, $p['quantidade']);
    afirmarIgual(150000, $p['valor_centavos']);
});

teste('ResumoFinanceiro: sem guias tudo vale zero', function () {
    afirmarIgual([
        'em_atraso' => ['quantidade' => 0, 'valor_centavos' => 0, 'encargos_centavos' => 0, 'total_atualizado_centavos' => 0],
        'a_vencer' => ['quantidade' => 0, 'valor_centavos' => 0, 'dias' => 7],
        'pago_no_mes' => ['quantidade' => 0, 'valor_centavos' => 0],
    ], resumoFinTeste([]));
});

teste('ResumoFinanceiro: janela de 7 dias — vence hoje e daqui a 7 dias entram; daqui a 8 não', function () {
    $r = resumoFinTeste([
        linhaFinTeste(1, 'pendente', '2026-11-17', '100'),  // hoje
        linhaFinTeste(2, 'pendente', '2026-11-24', '200'),  // +7
        linhaFinTeste(3, 'pendente', '2026-11-25', '400'),  // +8
    ]);
    afirmarIgual(300, $r['a_vencer']['valor_centavos']);
    afirmarIgual(2, $r['a_vencer']['quantidade']);
    afirmarIgual(0, $r['em_atraso']['quantidade']);
});

teste('ResumoFinanceiro: vencida ontem já é atraso (e não "a vencer")', function () {
    $r = resumoFinTeste([linhaFinTeste(1, 'pendente', '2026-11-16', '100000')]);
    afirmarIgual(1, $r['em_atraso']['quantidade']);
    afirmarIgual(0, $r['a_vencer']['quantidade']);
    afirmarIgual(100000 + 330 + 33, $r['em_atraso']['total_atualizado_centavos']); // 1 dia: multa 0,33% + juros 0,033%
});

teste('ResumoFinanceiro: status "pendente" com vencimento passado conta como atraso (a data manda)', function () {
    afirmarIgual(1, resumoFinTeste([linhaFinTeste(1, 'pendente', '2026-10-01', '5000')])['em_atraso']['quantidade']);
});

teste('ResumoFinanceiro: guia paga nunca conta como atraso nem a vencer, mesmo vencida', function () {
    $r = resumoFinTeste([linhaFinTeste(1, 'pago', '2026-01-10', '900000', '2026-11-03')]);
    afirmarIgual(0, $r['em_atraso']['quantidade']);
    afirmarIgual(0, $r['a_vencer']['quantidade']);
    afirmarIgual(900000, $r['pago_no_mes']['valor_centavos']);
});

teste('ResumoFinanceiro: "pago no mês" usa a data do PAGAMENTO e o mês corrente', function () {
    $r = resumoFinTeste([
        linhaFinTeste(1, 'pago', '2026-11-01', '100', '2026-11-01'),  // 1º dia do mês: entra
        linhaFinTeste(2, 'pago', '2026-10-20', '200', '2026-10-31'),  // mês passado: fora
        linhaFinTeste(3, 'pago', '2026-12-05', '400', '2026-11-17'),  // pago hoje, venceria depois: entra
    ]);
    afirmarIgual(500, $r['pago_no_mes']['valor_centavos']);
    afirmarIgual(2, $r['pago_no_mes']['quantidade']);
});

teste('ResumoFinanceiro: virada de mês — no dia 1º, o pago do mês anterior não conta', function () {
    $linhas = [linhaFinTeste(1, 'pago', '2026-11-20', '700', '2026-11-30')];
    afirmarIgual(700, resumoFinTeste($linhas, '2026-11-30')['pago_no_mes']['valor_centavos']);
    afirmarIgual(0, resumoFinTeste($linhas, '2026-12-01')['pago_no_mes']['valor_centavos']);
});

teste('ResumoFinanceiro: guia atrasada de valor zero conta na quantidade mas não soma encargos', function () {
    $a = resumoFinTeste([linhaFinTeste(1, 'atrasado', '2026-11-01', '0')])['em_atraso'];
    afirmarIgual(1, $a['quantidade']);
    afirmarIgual(0, $a['valor_centavos']);
    afirmarIgual(0, $a['encargos_centavos']);
});

teste('ResumoFinanceiro: pago sem data de pagamento (dado inconsistente) é ignorado, sem erro', function () {
    afirmarIgual(0, resumoFinTeste([linhaFinTeste(1, 'pago', '2026-11-01', '999', null)])['pago_no_mes']['quantidade']);
});

teste('ResumoFinanceiro: data de vencimento inválida lança exceção (nunca um total errado em silêncio)', function () {
    afirmarExcecao(InvalidArgumentException::class, fn () => resumoFinTeste([linhaFinTeste(1, 'pendente', '2026-02-31', '100')]));
});

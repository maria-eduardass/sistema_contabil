<?php
declare(strict_types=1);

use App\Services\TermometroRisco;

/** Monta uma obrigação cujo vencimento é $dias a partir de HOJE_TESTE (negativo = já venceu). */
function obrigacaoTeste(int $dias, string $status = 'pendente', int $id = 1): array
{
    $venc = (new DateTimeImmutable('2026-11-17'))->modify(($dias >= 0 ? '+' : '') . $dias . ' days');
    return ['id' => $id, 'status' => $status, 'data_vencimento' => $venc->format('Y-m-d')];
}

function scoreTeste(array $obrigacoes): int
{
    return TermometroRisco::calcular($obrigacoes, new DateTimeImmutable('2026-11-17'))['score'];
}

// --- Casos básicos ---
teste('Termômetro: sem obrigações o score é 0 e a faixa é baixo', function () {
    $r = TermometroRisco::calcular([], new DateTimeImmutable('2026-11-17'));
    afirmarIgual(0, $r['score']);
    afirmarIgual('baixo', $r['faixa']);
    afirmarIgual([], $r['itens']);
});

teste('Termômetro: obrigação paga é ignorada mesmo vencida', function () {
    afirmarIgual(0, scoreTeste([obrigacaoTeste(-30, 'pago')]));
});

// --- A vencer ---
teste('Termômetro: vence em mais de 15 dias não gera risco', function () {
    afirmarIgual(0, scoreTeste([obrigacaoTeste(16)]));
});

teste('Termômetro: vence em exatamente 15 dias ainda é 0', function () {
    afirmarIgual(0, scoreTeste([obrigacaoTeste(15)]));
});

teste('Termômetro: vence em 14 dias gera 2', function () {
    afirmarIgual(2, scoreTeste([obrigacaoTeste(14)]));
});

teste('Termômetro: vence amanhã gera 28', function () {
    afirmarIgual(28, scoreTeste([obrigacaoTeste(1)]));
});

teste('Termômetro: vence hoje gera 30 (faixa atenção)', function () {
    $r = TermometroRisco::calcular([obrigacaoTeste(0)], new DateTimeImmutable('2026-11-17'));
    afirmarIgual(30, $r['score']);
    afirmarIgual('atencao', $r['faixa']);
});

teste('Termômetro: horário do "hoje" não altera o dia de referência', function () {
    $r = TermometroRisco::calcular([obrigacaoTeste(0)], new DateTimeImmutable('2026-11-17 23:59:59'));
    afirmarIgual(30, $r['score']);
});

// --- Atrasadas ---
teste('Termômetro: 1 dia de atraso gera 44', function () {
    afirmarIgual(44, scoreTeste([obrigacaoTeste(-1, 'atrasado')]));
});

teste('Termômetro: 5 dias de atraso gera 60 (faixa alto)', function () {
    $r = TermometroRisco::calcular([obrigacaoTeste(-5, 'atrasado')], new DateTimeImmutable('2026-11-17'));
    afirmarIgual(60, $r['score']);
    afirmarIgual('alto', $r['faixa']);
});

teste('Termômetro: 10 dias de atraso gera 80 (faixa crítico)', function () {
    $r = TermometroRisco::calcular([obrigacaoTeste(-10, 'atrasado')], new DateTimeImmutable('2026-11-17'));
    afirmarIgual(80, $r['score']);
    afirmarIgual('critico', $r['faixa']);
});

teste('Termômetro: 15 dias de atraso atinge 100 e o teto segura em 100', function () {
    afirmarIgual(100, scoreTeste([obrigacaoTeste(-15, 'atrasado')]));
    afirmarIgual(100, scoreTeste([obrigacaoTeste(-400, 'atrasado')]));
});

teste('Termômetro: status desatualizado (pendente já vencida) é tratado como atrasada', function () {
    afirmarIgual(44, scoreTeste([obrigacaoTeste(-1, 'pendente')]));
});

// --- Agregação ---
teste('Termômetro: a pior obrigação define a base e as demais agravam 25%', function () {
    // 60 + 0,25 * 44 = 71
    afirmarIgual(71, scoreTeste([obrigacaoTeste(-5, 'atrasado', 1), obrigacaoTeste(-1, 'atrasado', 2)]));
});

teste('Termômetro: a ordem das obrigações não muda o score', function () {
    $a = [obrigacaoTeste(-1, 'atrasado', 2), obrigacaoTeste(-5, 'atrasado', 1)];
    afirmarIgual(71, scoreTeste($a));
});

teste('Termômetro: agravo é arredondado (60,5 vira 61)', function () {
    afirmarIgual(61, scoreTeste([obrigacaoTeste(-5, 'atrasado', 1), obrigacaoTeste(14, 'pendente', 2)]));
});

teste('Termômetro: soma acima de 100 é limitada a 100', function () {
    $tres = [obrigacaoTeste(-15, 'atrasado', 1), obrigacaoTeste(-15, 'atrasado', 2), obrigacaoTeste(-15, 'atrasado', 3)];
    afirmarIgual(100, scoreTeste($tres));
});

teste('Termômetro: pagas misturadas com em aberto não entram na soma', function () {
    afirmarIgual(60, scoreTeste([obrigacaoTeste(-5, 'atrasado', 1), obrigacaoTeste(-20, 'pago', 2)]));
});

// --- Itens detalhados ---
teste('Termômetro: itens trazem id, dias de atraso e risco individual', function () {
    $r = TermometroRisco::calcular(
        [obrigacaoTeste(-5, 'atrasado', 7), obrigacaoTeste(3, 'pendente', 8), obrigacaoTeste(-9, 'pago', 9)],
        new DateTimeImmutable('2026-11-17')
    );
    afirmarIgual(2, count($r['itens']));
    afirmarIgual(['id' => 7, 'dias_atraso' => 5, 'risco' => 60], $r['itens'][0]);
    afirmarIgual(['id' => 8, 'dias_atraso' => 0, 'risco' => 24], $r['itens'][1]);
});

// --- Faixas ---
teste('Termômetro: limites das faixas (29/30, 59/60, 79/80)', function () {
    afirmarIgual('baixo', TermometroRisco::faixaDoScore(0));
    afirmarIgual('baixo', TermometroRisco::faixaDoScore(29));
    afirmarIgual('atencao', TermometroRisco::faixaDoScore(30));
    afirmarIgual('atencao', TermometroRisco::faixaDoScore(59));
    afirmarIgual('alto', TermometroRisco::faixaDoScore(60));
    afirmarIgual('alto', TermometroRisco::faixaDoScore(79));
    afirmarIgual('critico', TermometroRisco::faixaDoScore(80));
    afirmarIgual('critico', TermometroRisco::faixaDoScore(100));
});

teste('Termômetro: score fora de 0..100 é rejeitado', function () {
    afirmarExcecao(InvalidArgumentException::class, fn () => TermometroRisco::faixaDoScore(-1));
    afirmarExcecao(InvalidArgumentException::class, fn () => TermometroRisco::faixaDoScore(101));
});

// --- Entrada inválida ---
teste('Termômetro: data de vencimento inválida lança exceção', function () {
    $hoje = new DateTimeImmutable('2026-11-17');
    afirmarExcecao(InvalidArgumentException::class, fn () => TermometroRisco::calcular([['status' => 'pendente', 'data_vencimento' => '2026-02-31']], $hoje));
    afirmarExcecao(InvalidArgumentException::class, fn () => TermometroRisco::calcular([['status' => 'pendente', 'data_vencimento' => 'ontem']], $hoje));
});

teste('Termômetro: status desconhecido ou campos ausentes lançam exceção', function () {
    $hoje = new DateTimeImmutable('2026-11-17');
    afirmarExcecao(InvalidArgumentException::class, fn () => TermometroRisco::calcular([['status' => 'x', 'data_vencimento' => '2026-11-20']], $hoje));
    afirmarExcecao(InvalidArgumentException::class, fn () => TermometroRisco::calcular([['status' => 'pendente']], $hoje));
});

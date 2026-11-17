<?php
declare(strict_types=1);

use App\Services\MultaService;

// Todos os valores em CENTAVOS (R$ 1.000,00 = 100000).

teste('Multa: R$ 1.000,00 com 10 dias de atraso = multa 33,00 + juros 3,33 = 36,33', function () {
    afirmarIgual(['multa' => 3300, 'juros' => 333, 'total' => 3633], MultaService::calcular(100000, 10));
});

teste('Multa: sem atraso não há multa nem juros', function () {
    afirmarIgual(['multa' => 0, 'juros' => 0, 'total' => 0], MultaService::calcular(100000, 0));
});

teste('Multa: valor zero (ex.: declaração sem imposto) não gera multa', function () {
    afirmarIgual(['multa' => 0, 'juros' => 0, 'total' => 0], MultaService::calcular(0, 30));
});

teste('Multa: 1 dia de atraso = 0,33% de multa', function () {
    $r = MultaService::calcular(100000, 1);
    afirmarIgual(330, $r['multa']);
    afirmarIgual(33, $r['juros']);
    afirmarIgual(363, $r['total']);
});

teste('Multa: com 60 dias ainda não atingiu o teto (19,8%)', function () {
    afirmarIgual(19800, MultaService::calcular(100000, 60)['multa']);
});

teste('Multa: a partir de 61 dias a multa trava no teto de 20%', function () {
    afirmarIgual(20000, MultaService::calcular(100000, 61)['multa']);
    afirmarIgual(20000, MultaService::calcular(100000, 400)['multa']);
});

teste('Multa: os juros continuam crescendo depois do teto da multa (1% ao mês)', function () {
    afirmarIgual(12000, MultaService::calcular(100000, 360)['juros']);
});

teste('Multa: arredonda meio centavo para cima (R$ 50,00, 1 dia = 0,165 -> 17)', function () {
    afirmarIgual(17, MultaService::calcular(5000, 1)['multa']);
});

teste('Multa: total é sempre multa + juros', function () {
    foreach ([[185000, 20], [62050, 5], [999, 7], [9999999999, 90]] as [$valor, $dias]) {
        $r = MultaService::calcular($valor, $dias);
        afirmarIgual($r['multa'] + $r['juros'], $r['total']);
    }
});

teste('Multa: dados do seed (Padaria) - R$ 1.850,00 / 20 dias e R$ 620,50 / 5 dias', function () {
    afirmarIgual(['multa' => 12210, 'juros' => 1233, 'total' => 13443], MultaService::calcular(185000, 20));
    afirmarIgual(['multa' => 1024, 'juros' => 103, 'total' => 1127], MultaService::calcular(62050, 5));
});

teste('Multa: valor máximo do sistema (R$ 99.999.999,99) não estoura inteiro', function () {
    $r = MultaService::calcular(9999999999, 36500);
    afirmarVerdadeiro($r['total'] > 0 && is_int($r['total']));
});

teste('Multa: valor ou dias negativos lançam exceção', function () {
    afirmarExcecao(InvalidArgumentException::class, fn () => MultaService::calcular(-1, 5));
    afirmarExcecao(InvalidArgumentException::class, fn () => MultaService::calcular(100, -1));
});

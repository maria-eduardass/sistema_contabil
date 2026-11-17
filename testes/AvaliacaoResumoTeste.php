<?php
declare(strict_types=1);

use App\Services\AvaliacaoResumo;

teste('AvaliacaoResumo: sem avaliações não há média (null) e o total é 0', function () {
    $r = AvaliacaoResumo::de([1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]);
    afirmarIgual(0, $r['total']);
    afirmarIgual(null, $r['media']);
});

teste('AvaliacaoResumo: média ponderada pela distribuição, com 1 casa decimal', function () {
    // notas: 3, 4, 5, 5 -> soma 17 / 4 = 4,25 -> 4,3 (meio sobe)
    $r = AvaliacaoResumo::de([1 => 0, 2 => 0, 3 => 1, 4 => 1, 5 => 2]);
    afirmarIgual(4, $r['total']);
    afirmarIgual(4.3, $r['media']);
});

teste('AvaliacaoResumo: todas as notas iguais dão exatamente essa nota', function () {
    afirmarIgual(5.0, AvaliacaoResumo::de([1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 7])['media']);
    afirmarIgual(1.0, AvaliacaoResumo::de([1 => 3, 2 => 0, 3 => 0, 4 => 0, 5 => 0])['media']);
});

teste('AvaliacaoResumo: chaves ausentes valem zero e a distribuição sai completa de 1 a 5', function () {
    $r = AvaliacaoResumo::de([5 => 2, 1 => 1]);
    afirmarIgual([1 => 1, 2 => 0, 3 => 0, 4 => 0, 5 => 2], $r['distribuicao']);
    afirmarIgual(3.7, $r['media']); // (1 + 10) / 3 = 3,666…
});

teste('AvaliacaoResumo: devolve o maior valor da distribuição para escalar as barras', function () {
    afirmarIgual(6, AvaliacaoResumo::de([1 => 1, 2 => 6, 3 => 2, 4 => 0, 5 => 3])['maximo']);
    afirmarIgual(0, AvaliacaoResumo::de([])['maximo']);
});

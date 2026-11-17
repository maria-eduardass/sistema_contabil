<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Resumo das avaliações a partir da distribuição "quantos votos por nota".
 * Por que calcular aqui e não com AVG() do banco: a média sai exata (inteiros), com
 * arredondamento previsível e testável, e a mesma distribuição alimenta as barras da tela.
 */
final class AvaliacaoResumo
{
    /**
     * @param array<int,int> $distribuicao nota => votos (chaves ausentes valem 0)
     * @return array{total:int,media:?float,distribuicao:array<int,int>,maximo:int}
     */
    public static function de(array $distribuicao): array
    {
        $completa = [];
        $total = 0;
        $soma = 0;
        for ($nota = 1; $nota <= 5; $nota++) {
            $votos = (int) ($distribuicao[$nota] ?? 0);
            $completa[$nota] = $votos;
            $total += $votos;
            $soma += $nota * $votos;
        }

        return [
            'total' => $total,
            'media' => $total === 0 ? null : round($soma / $total, 1),
            'distribuicao' => $completa,
            'maximo' => max($completa),
        ];
    }
}

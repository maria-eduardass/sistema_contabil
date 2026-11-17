<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/**
 * Estima multa e juros de mora de UMA obrigação em atraso.
 *
 * Por que inteiros: dinheiro em centavos e taxas em "pontos-base" (1 pb = 0,01%) evitam
 * os erros de arredondamento do float (0.1 + 0.2 != 0.3). Nenhuma conta usa ponto flutuante.
 *
 * Premissas do MVP (simplificação didática, NÃO substitui o cálculo oficial da Receita):
 *  - Multa de mora: 0,33% ao dia, limitada a 20%.
 *  - Juros: 1% ao mês, proporcional aos dias (dias/30), sem teto.
 *  - Arredondamento: meio centavo sobe.
 */
final class MultaService
{
    private const MULTA_DIARIA_PB = 33;   // 0,33% ao dia
    private const MULTA_TETO_PB = 2000;   // 20%
    private const JUROS_MENSAL_PB = 100;  // 1% ao mês
    private const PB = 10000;             // 100% = 10.000 pontos-base

    /** @return array{multa:int,juros:int,total:int} valores em centavos */
    public static function calcular(int $valorCentavos, int $diasAtraso): array
    {
        if ($valorCentavos < 0 || $diasAtraso < 0) {
            throw new InvalidArgumentException('Valor e dias de atraso não podem ser negativos.');
        }

        $multaPb = min(self::MULTA_DIARIA_PB * $diasAtraso, self::MULTA_TETO_PB);
        $multa = intdiv($valorCentavos * $multaPb + intdiv(self::PB, 2), self::PB);
        // Juros: valor × (1%/30 por dia) × dias. "+ metade do divisor" faz o arredondamento para cima.
        $divisor = self::PB * 30;
        $juros = intdiv($valorCentavos * self::JUROS_MENSAL_PB * $diasAtraso + intdiv($divisor, 2), $divisor);

        return ['multa' => $multa, 'juros' => $juros, 'total' => $multa + $juros];
    }
}

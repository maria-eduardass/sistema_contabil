<?php
declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;

/**
 * "O que preciso pagar primeiro?" para o painel do cliente: obrigações NÃO pagas, da mais
 * antiga (atrasadas) para a mais distante, cada uma com os dias até o vencimento.
 */
final class ProximasObrigacoes
{
    /**
     * @param list<array<string,mixed>> $linhas formato de Obrigacao::listarPorEmpresa
     * @return list<array<string,mixed>> mesmas linhas + 'dias_para_vencer' (negativo = atrasada)
     */
    public static function de(array $linhas, DateTimeImmutable $hoje, int $limite = 5): array
    {
        $abertas = array_values(array_filter($linhas, static fn (array $l): bool => $l['status'] !== 'pago'));
        usort($abertas, static fn (array $a, array $b): int =>
            [$a['data_vencimento'], $a['id']] <=> [$b['data_vencimento'], $b['id']]);

        $resultado = [];
        foreach (array_slice($abertas, 0, max(0, $limite)) as $linha) {
            $linha['dias_para_vencer'] = TermometroRisco::diasParaVencer((string) $linha['data_vencimento'], $hoje);
            $resultado[] = $linha;
        }

        return $resultado;
    }
}

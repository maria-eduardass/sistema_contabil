<?php
declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;

/**
 * Resumo em R$ para o topo dos painéis: quanto está em atraso, quanto vence nos próximos
 * 7 dias e quanto já foi pago no mês. Pura: recebe as linhas já carregadas e o "hoje".
 *
 * Regras (as mesmas do Termômetro, para os números nunca se contradizerem):
 *  - Atraso e "a vencer" olham a DATA de vencimento, não o campo status (que só é sincronizado
 *    quando alguém abre uma tela). Vencimento anterior a hoje = atraso; hoje até hoje+7 = a vencer.
 *  - Guia paga nunca entra em atraso nem em "a vencer", mesmo que tenha vencido antes.
 *  - "Pago no mês" usa a data do PAGAMENTO (não a do vencimento) dentro do mês corrente.
 *  - Os encargos do atraso (multa + juros) vêm do MultaService, centavo a centavo.
 */
final class ResumoFinanceiro
{
    public const JANELA_A_VENCER_DIAS = 7;

    /**
     * @param list<array<string,mixed>> $linhas status, data_vencimento, data_pagamento?, valor_centavos
     * @return array{
     *   em_atraso:array{quantidade:int,valor_centavos:int,encargos_centavos:int,total_atualizado_centavos:int},
     *   a_vencer:array{quantidade:int,valor_centavos:int,dias:int},
     *   pago_no_mes:array{quantidade:int,valor_centavos:int}
     * }
     */
    public static function calcular(array $linhas, DateTimeImmutable $hoje): array
    {
        $atrasoQtd = $atrasoValor = $encargos = 0;
        $venceQtd = $venceValor = 0;
        $pagoQtd = $pagoValor = 0;

        $hojeTexto = $hoje->format('Y-m-d');
        $mesAtual = $hoje->format('Y-m');

        foreach ($linhas as $linha) {
            $valor = (int) $linha['valor_centavos'];

            if (($linha['status'] ?? '') === 'pago') {
                $pagamento = (string) ($linha['data_pagamento'] ?? '');
                if ($pagamento !== '' && substr($pagamento, 0, 7) === $mesAtual && $pagamento <= $hojeTexto) {
                    $pagoQtd++;
                    $pagoValor += $valor;
                }
                continue;
            }

            $dias = TermometroRisco::diasParaVencer((string) $linha['data_vencimento'], $hoje); // negativo = atrasada
            if ($dias < 0) {
                $atrasoQtd++;
                $atrasoValor += $valor;
                $encargos += MultaService::calcular($valor, -$dias)['total'];
            } elseif ($dias <= self::JANELA_A_VENCER_DIAS) {
                $venceQtd++;
                $venceValor += $valor;
            }
        }

        return [
            'em_atraso' => [
                'quantidade' => $atrasoQtd,
                'valor_centavos' => $atrasoValor,
                'encargos_centavos' => $encargos,
                'total_atualizado_centavos' => $atrasoValor + $encargos,
            ],
            'a_vencer' => ['quantidade' => $venceQtd, 'valor_centavos' => $venceValor, 'dias' => self::JANELA_A_VENCER_DIAS],
            'pago_no_mes' => ['quantidade' => $pagoQtd, 'valor_centavos' => $pagoValor],
        ];
    }
}

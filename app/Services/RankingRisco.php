<?php
declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;

/**
 * Ranking de risco das empresas para o painel do contador. Puro: recebe as empresas e as
 * obrigações em aberto já carregadas (uma consulta só, sem N+1) e devolve a lista ordenada.
 *
 * Ordem: maior score primeiro; empate decide pela maior multa estimada (quem custa mais
 * a atrasar merece atenção antes) e, por fim, pelo nome, para a ordem ser sempre estável.
 */
final class RankingRisco
{
    /**
     * @param list<array{id:int|string,razao_social:string,cnpj:string}> $empresas somente as ativas
     * @param array<int,list<array<string,mixed>>> $obrigacoesPorEmpresa em aberto, indexadas por empresa_id
     * @return array{empresas:list<array<string,mixed>>,resumo:array{total_empresas:int,em_risco:int,atrasadas:int,multa_total_centavos:int}}
     */
    public static function montar(array $empresas, array $obrigacoesPorEmpresa, DateTimeImmutable $hoje): array
    {
        $linhas = [];
        $resumo = ['total_empresas' => 0, 'em_risco' => 0, 'atrasadas' => 0, 'multa_total_centavos' => 0];

        foreach ($empresas as $empresa) {
            $id = (int) $empresa['id'];
            // Só entra o que pertence a uma empresa da lista: obrigações de inativas são ignoradas.
            $t = TermometroEmpresa::montar($obrigacoesPorEmpresa[$id] ?? [], $hoje);
            $atrasadas = count(array_filter($t['itens'], static fn (array $i): bool => $i['dias_atraso'] > 0));

            $linhas[] = [
                'id' => $id,
                'razao_social' => (string) $empresa['razao_social'],
                'cnpj' => (string) $empresa['cnpj'],
                'score' => $t['score'],
                'faixa' => $t['faixa'],
                'faixa_rotulo' => $t['faixa_rotulo'],
                'multa_total_centavos' => $t['multa_total_centavos'],
                'atrasadas' => $atrasadas,
                'em_aberto' => count($t['itens']),
            ];

            $resumo['total_empresas']++;
            $resumo['em_risco'] += in_array($t['faixa'], ['alto', 'critico'], true) ? 1 : 0;
            $resumo['atrasadas'] += $atrasadas;
            $resumo['multa_total_centavos'] += $t['multa_total_centavos'];
        }

        usort($linhas, static fn (array $a, array $b): int =>
            [$b['score'], $b['multa_total_centavos'], mb_strtolower($a['razao_social'])]
            <=> [$a['score'], $a['multa_total_centavos'], mb_strtolower($b['razao_social'])]);

        return ['empresas' => $linhas, 'resumo' => $resumo];
    }
}

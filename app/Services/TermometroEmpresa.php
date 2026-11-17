<?php
declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;

/**
 * Junta score de risco (TermometroRisco) e multa estimada (MultaService) no resultado que a
 * tela consome. Pura: recebe as linhas já carregadas e o "hoje", sem tocar no banco.
 *
 * Os dois serviços ficam independentes de propósito: o score nasceu testado sozinho e a multa
 * também; aqui só se combina o dia de atraso calculado por um com o valor usado pelo outro.
 */
final class TermometroEmpresa
{
    private const ROTULOS = [
        'baixo' => 'Risco baixo',
        'atencao' => 'Atenção',
        'alto' => 'Risco alto',
        'critico' => 'Risco crítico',
    ];

    /**
     * @param list<array<string,mixed>> $linhas formato de Obrigacao::listarPorEmpresa
     * @return array{score:int,faixa:string,faixa_rotulo:string,limites:array<string,int>,multa_total_centavos:int,itens:list<array<string,mixed>>}
     */
    public static function montar(array $linhas, DateTimeImmutable $hoje): array
    {
        $porId = [];
        $entrada = [];
        foreach ($linhas as $linha) {
            $id = (int) $linha['id'];
            $porId[$id] = $linha;
            $entrada[] = ['id' => $id, 'status' => (string) $linha['status'], 'data_vencimento' => (string) $linha['data_vencimento']];
        }

        $risco = TermometroRisco::calcular($entrada, $hoje);

        $itens = [];
        $multaTotal = 0;
        foreach ($risco['itens'] as $item) {
            $linha = $porId[$item['id']];
            $valor = (int) $linha['valor_centavos'];
            $multa = MultaService::calcular($valor, $item['dias_atraso'])['total'];
            $multaTotal += $multa;

            $itens[] = [
                'id' => $item['id'],
                'tipo_sigla' => (string) $linha['tipo_sigla'],
                'data_vencimento' => (string) $linha['data_vencimento'],
                'valor_centavos' => $valor,
                'dias_atraso' => $item['dias_atraso'],
                'risco' => $item['risco'],
                'multa_centavos' => $multa, // multa + juros estimados
            ];
        }

        return [
            'score' => $risco['score'],
            'faixa' => $risco['faixa'],
            'faixa_rotulo' => self::ROTULOS[$risco['faixa']],
            'limites' => TermometroRisco::LIMITES_FAIXAS,
            'multa_total_centavos' => $multaTotal,
            'itens' => $itens,
        ];
    }
}

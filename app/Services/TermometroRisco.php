<?php
declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Calcula o Termômetro de Risco Fiscal (0 a 100%) de uma empresa.
 *
 * Por que é uma classe pura (sem banco, sem relógio global): a regra de negócio mais
 * importante do sistema precisa ser testável com datas fixas. "Hoje" é sempre injetado.
 *
 * Premissas do MVP (valores simples e explicáveis ao cliente, não são lei):
 *  - Só obrigações não pagas entram no cálculo.
 *  - Risco individual (0-100): vence em mais de 15 dias = 0; entre 15 e 0 dias sobe de 0 a 30
 *    linearmente; atrasada = 40 + 4 pontos por dia de atraso (100 com 15 dias).
 *  - Score da empresa: a pior obrigação define a base; as demais agravam em 25% da soma.
 *  - A data de vencimento é quem manda, não o campo status: o status só é atualizado
 *    quando alguém abre o sistema, então pode estar defasado.
 */
final class TermometroRisco
{
    private const DIAS_ANTECEDENCIA = 15;       // a partir daqui o vencimento começa a pesar
    private const RISCO_NO_VENCIMENTO = 30;     // risco no dia do vencimento
    private const RISCO_BASE_ATRASO = 40;       // piso do risco de uma atrasada (40 + 4 por dia)
    private const RISCO_POR_DIA_ATRASO = 4;
    private const PESO_DEMAIS_OBRIGACOES = 0.25;
    private const STATUS_VALIDOS = ['pendente', 'atrasado', 'pago'];

    /**
     * Início de cada faixa acima de "baixo" (que vai de 0 até aqui). Público para o velocímetro
     * desenhar as cores a partir da MESMA fonte da regra, sem duplicar números no JavaScript.
     */
    public const LIMITES_FAIXAS = ['atencao' => 30, 'alto' => 60, 'critico' => 80];

    /**
     * @param list<array{id?:int,status:string,data_vencimento:string}> $obrigacoes
     * @return array{score:int,faixa:string,itens:list<array{id:?int,dias_atraso:int,risco:int}>}
     */
    public static function calcular(array $obrigacoes, DateTimeImmutable $hoje): array
    {
        $hoje = $hoje->setTime(0, 0);
        $itens = [];

        foreach ($obrigacoes as $obrigacao) {
            self::validar($obrigacao);
            if ($obrigacao['status'] === 'pago') {
                continue;
            }
            $diasParaVencer = self::diasParaVencer($obrigacao['data_vencimento'], $hoje);
            $itens[] = [
                'id' => $obrigacao['id'] ?? null,
                'dias_atraso' => max(0, -$diasParaVencer),
                'risco' => self::riscoIndividual($diasParaVencer),
            ];
        }

        $score = self::agregar(array_column($itens, 'risco'));

        return ['score' => $score, 'faixa' => self::faixaDoScore($score), 'itens' => $itens];
    }

    public static function faixaDoScore(int $score): string
    {
        return match (true) {
            $score < 0 || $score > 100 => throw new InvalidArgumentException('Score deve estar entre 0 e 100.'),
            $score < self::LIMITES_FAIXAS['atencao'] => 'baixo',
            $score < self::LIMITES_FAIXAS['alto'] => 'atencao',
            $score < self::LIMITES_FAIXAS['critico'] => 'alto',
            default => 'critico',
        };
    }

    private static function riscoIndividual(int $diasParaVencer): int
    {
        if ($diasParaVencer < 0) {
            return min(100, self::RISCO_BASE_ATRASO + self::RISCO_POR_DIA_ATRASO * -$diasParaVencer);
        }
        if ($diasParaVencer >= self::DIAS_ANTECEDENCIA) {
            return 0;
        }
        // Linear: 0 pontos em 15 dias, 30 pontos no dia do vencimento (2 pontos por dia).
        return (int) (self::RISCO_NO_VENCIMENTO * (self::DIAS_ANTECEDENCIA - $diasParaVencer) / self::DIAS_ANTECEDENCIA);
    }

    /** @param list<int> $riscos */
    private static function agregar(array $riscos): int
    {
        if ($riscos === []) {
            return 0;
        }
        $maior = max($riscos);
        $demais = array_sum($riscos) - $maior;

        return (int) min(100, round($maior + self::PESO_DEMAIS_OBRIGACOES * $demais));
    }

    /**
     * Dias de calendário até o vencimento: positivo = falta, 0 = vence hoje, negativo = atrasada.
     * Pública para as telas usarem a MESMA contagem de dias do score (nunca duas versões da conta).
     */
    public static function diasParaVencer(string $vencimento, DateTimeImmutable $hoje): int
    {
        $data = DateTimeImmutable::createFromFormat('!Y-m-d', $vencimento);
        // createFromFormat aceita 2026-02-31 (vira março); comparar de volta rejeita datas inexistentes.
        if ($data === false || $data->format('Y-m-d') !== $vencimento) {
            throw new InvalidArgumentException('Data de vencimento inválida: ' . $vencimento);
        }
        $diff = $hoje->setTime(0, 0)->diff($data); // o horário de "agora" não pode mudar o dia

        return $diff->invert ? -(int) $diff->days : (int) $diff->days;
    }

    /** @param array<string,mixed> $obrigacao */
    private static function validar(array $obrigacao): void
    {
        if (!isset($obrigacao['status'], $obrigacao['data_vencimento'])) {
            throw new InvalidArgumentException('Obrigação sem status ou data_vencimento.');
        }
        if (!in_array($obrigacao['status'], self::STATUS_VALIDOS, true)) {
            throw new InvalidArgumentException('Status desconhecido: ' . $obrigacao['status']);
        }
    }
}

<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;
use DateTimeImmutable;

/**
 * "E se eu cadastrar esta obrigação?": o termômetro ANTES e DEPOIS de uma guia hipotética.
 *
 * Por que existe no servidor e não como cálculo em JavaScript: o score e a multa têm regras com limites
 * (15 dias de antecedência, teto de 100, teto de 20% da multa...). Repetir isso no navegador criaria uma segunda
 * versão da regra, que cedo ou tarde divergiria da real. Aqui a prévia usa EXATAMENTE o mesmo TermometroEmpresa
 * que mostra o termômetro de verdade, então o que a tela promete antes de salvar é o que aparece depois.
 *
 * Pura: recebe as linhas já carregadas e o "hoje"; não toca no banco e não altera o que recebeu.
 */
final class SimulacaoTermometro
{
    /** Id da guia hipotética: negativo, para nunca colidir com um id real do banco. */
    private const ID_HIPOTETICO = -1;

    /**
     * @param list<array<string,mixed>> $linhas formato de Obrigacao::listarPorEmpresa
     * @param string $vencimento Y-m-d (como vem do campo de data); $valorTexto no formato brasileiro, vazio = zero
     * @return array{
     *   erros:array<string,string>,
     *   antes:array{score:int,faixa:string,faixa_rotulo:string,multa_total_centavos:int},
     *   depois:?array{score:int,faixa:string,faixa_rotulo:string,multa_total_centavos:int},
     *   nova:?array{status:string,dias_atraso:int,risco:int,multa_centavos:int}
     * }  "antes" sempre vem (a tela mostra o termômetro atual mesmo com o formulário incompleto);
     *    "depois" e "nova" só vêm quando os dados da guia são válidos
     */
    public static function simular(array $linhas, string $vencimento, string $valorTexto, DateTimeImmutable $hoje): array
    {
        $antes = TermometroEmpresa::montar($linhas, $hoje);

        $erros = [];
        $data = Validator::data(trim($vencimento));
        if ($data === null) {
            $erros['vencimento'] = 'Informe uma data de vencimento válida.';
        }
        $valorTexto = trim($valorTexto);
        // Valor vazio vale zero: há declarações sem imposto a pagar (mesma regra do cadastro de verdade).
        $centavos = $valorTexto === '' ? 0 : Validator::valorParaCentavos($valorTexto);
        if ($centavos === null) {
            $erros['valor'] = 'Valor inválido. Use o formato 1.234,56.';
        }

        if ($erros !== [] || $data === null || $centavos === null) {
            return ['erros' => $erros, 'antes' => self::resumo($antes), 'depois' => null, 'nova' => null];
        }

        $hipotetica = [
            'id' => self::ID_HIPOTETICO,
            'tipo_sigla' => 'NOVA',
            'data_vencimento' => $data->format('Y-m-d'),
            'valor_centavos' => $centavos,
            'status' => 'pendente', // o que manda é a data; o termômetro trata vencida como atrasada
        ];
        $depois = TermometroEmpresa::montar([...$linhas, $hipotetica], $hoje);

        $item = [];
        foreach ($depois['itens'] as $candidato) {
            if ($candidato['id'] === self::ID_HIPOTETICO) {
                $item = $candidato;
                break;
            }
        }

        return [
            'erros' => [],
            'antes' => self::resumo($antes),
            'depois' => self::resumo($depois),
            'nova' => [
                'status' => $item['dias_atraso'] > 0 ? 'atrasado' : 'pendente',
                'dias_atraso' => $item['dias_atraso'],
                'risco' => $item['risco'],
                'multa_centavos' => $item['multa_centavos'],
            ],
        ];
    }

    /**
     * Frase para mostrar depois de salvar (ou de pagar): como o termômetro mudou.
     *
     * @param array{score:int,faixa_rotulo:string} $antes
     * @param array{score:int,faixa_rotulo:string} $depois
     */
    public static function resumoImpacto(array $antes, array $depois): string
    {
        if ($antes['score'] === $depois['score']) {
            return "O termômetro continua em {$depois['score']}% ({$depois['faixa_rotulo']}).";
        }

        return "O termômetro foi de {$antes['score']}% ({$antes['faixa_rotulo']}) para {$depois['score']}% ({$depois['faixa_rotulo']}).";
    }

    /**
     * @param array<string,mixed> $termometro saída de TermometroEmpresa::montar
     * @return array{score:int,faixa:string,faixa_rotulo:string,multa_total_centavos:int}
     */
    private static function resumo(array $termometro): array
    {
        return [
            'score' => $termometro['score'],
            'faixa' => $termometro['faixa'],
            'faixa_rotulo' => $termometro['faixa_rotulo'],
            'multa_total_centavos' => $termometro['multa_total_centavos'],
        ];
    }
}

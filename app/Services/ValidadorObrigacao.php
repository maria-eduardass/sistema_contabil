<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;
use DateTimeImmutable;

/**
 * Valida o cadastro de obrigação fiscal. Pura: os tipos válidos e o "hoje" são injetados.
 *
 * Só os campos do formulário são lidos. Status, empresa e data de pagamento NUNCA vêm do
 * usuário: o status inicial é calculado aqui e a empresa vem da URL já autorizada.
 */
final class ValidadorObrigacao
{
    /**
     * @param array<string,mixed> $entrada
     * @param list<int> $tiposValidos ids existentes em tipos_obrigacao
     * @return array{dados:array<string,mixed>,erros:array<string,string>}
     */
    public static function validar(array $entrada, array $tiposValidos, DateTimeImmutable $hoje): array
    {
        $texto = static fn (string $campo): string => is_string($entrada[$campo] ?? null) ? trim($entrada[$campo]) : '';
        $erros = [];

        $tipo = preg_match('/^[1-9]\d{0,9}\z/', $texto('tipo_obrigacao_id')) ? (int) $texto('tipo_obrigacao_id') : 0;
        if (!in_array($tipo, $tiposValidos, true)) {
            $tipo = 0;
            $erros['tipo_obrigacao_id'] = 'Selecione o tipo de obrigação.';
        }

        // A competência é uma DATA completa (dd/mm/aaaa). "AAAA-MM" continua aceito e vira o dia 1º (compatibilidade).
        $dataCompetencia = Validator::data($texto('competencia'));
        $competencia = $dataCompetencia !== null ? $dataCompetencia->format('Y-m-d') : Validator::competencia($texto('competencia'));
        if ($competencia === null) {
            $erros['competencia'] = 'Informe a data da competência.';
        }

        $vencimento = Validator::data($texto('data_vencimento'));
        if ($vencimento === null) {
            $erros['data_vencimento'] = 'Informe uma data de vencimento válida.';
        } elseif ($competencia !== null && $vencimento->format('Y-m-d') < $competencia) {
            $erros['data_vencimento'] = 'O vencimento não pode ser anterior à competência.';
        }

        // Valor vazio vale zero: há declarações sem imposto a pagar.
        $valor = $texto('valor');
        $centavos = $valor === '' ? 0 : Validator::valorParaCentavos($valor);
        if ($centavos === null) {
            $centavos = 0;
            $erros['valor'] = 'Valor inválido. Use o formato 1.234,56.';
        }

        $observacao = $texto('observacao');
        if (!Validator::texto($observacao, 0, 255)) {
            $erros['observacao'] = 'A observação pode ter no máximo 255 caracteres.';
        }

        $vencimentoTexto = $vencimento?->format('Y-m-d') ?? '';
        $atrasada = $vencimentoTexto !== '' && $vencimentoTexto < $hoje->format('Y-m-d');

        return [
            'dados' => [
                'tipo_obrigacao_id' => $tipo,
                'competencia' => $competencia ?? '',
                'data_vencimento' => $vencimentoTexto,
                'valor_centavos' => $centavos,
                'observacao' => $observacao === '' ? null : $observacao,
                'status' => $atrasada ? 'atrasado' : 'pendente',
            ],
            'erros' => $erros,
        ];
    }
}

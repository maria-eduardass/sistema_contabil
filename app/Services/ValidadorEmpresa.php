<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;

/**
 * Valida e normaliza os campos do cadastro de empresa. Pura (sem banco): a checagem de
 * CNPJ duplicado fica no controller, que conhece o banco.
 * Devolve sempre os 4 campos normalizados e um erro (texto) por campo inválido.
 */
final class ValidadorEmpresa
{
    public const REGIMES = [
        'mei' => 'MEI',
        'simples' => 'Simples Nacional',
        'presumido' => 'Lucro Presumido',
        'real' => 'Lucro Real',
    ];

    /**
     * @param array<string,mixed> $entrada
     * @return array{dados:array<string,string>,erros:array<string,string>}
     */
    public static function validar(array $entrada): array
    {
        $texto = static fn (string $campo): string => is_string($entrada[$campo] ?? null) ? trim($entrada[$campo]) : '';

        $dados = [
            'razao_social' => $texto('razao_social'),
            'cnpj' => Validator::normalizarCnpj($texto('cnpj')),
            'regime_tributario' => $texto('regime_tributario'),
            'email_contato' => mb_strtolower($texto('email_contato')),
        ];

        $erros = [];
        if (!Validator::texto($dados['razao_social'], 3, 150)) {
            $erros['razao_social'] = 'Informe a razão social (3 a 150 caracteres).';
        }
        if (!Validator::cnpj($dados['cnpj'])) {
            $erros['cnpj'] = 'CNPJ inválido.';
        }
        if (!array_key_exists($dados['regime_tributario'], self::REGIMES)) {
            $erros['regime_tributario'] = 'Selecione um regime tributário.';
        }
        if (!Validator::email($dados['email_contato'])) {
            $erros['email_contato'] = 'E-mail inválido.';
        }

        return ['dados' => $dados, 'erros' => $erros];
    }
}

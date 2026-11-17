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
     * @param bool $conferirDigitoCnpj false = aceita qualquer sequência de 14 números
     * @return array{dados:array<string,string>,erros:array<string,string>}
     */
    public static function validar(array $entrada, bool $conferirDigitoCnpj = true): array
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
        // Padrão: confere os dígitos verificadores. O cadastro público (projeto escolar) pede só os 14 números,
        // porque quase ninguém tem um CNPJ real para testar. O banco exige 14 dígitos de qualquer forma.
        $cnpjOk = $conferirDigitoCnpj ? Validator::cnpj($dados['cnpj']) : preg_match('/^\d{14}\z/', $dados['cnpj']) === 1;
        if (!$cnpjOk) {
            $erros['cnpj'] = $conferirDigitoCnpj ? 'CNPJ inválido.' : 'O CNPJ precisa ter 14 números.';
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

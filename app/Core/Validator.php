<?php
declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;

/**
 * Validações de entrada, sempre no servidor (o HTML5 do navegador é só conveniência).
 * Métodos puros: recebem string, devolvem bool ou o valor já convertido (null = inválido).
 */
final class Validator
{
    private const VALOR_MAXIMO_CENTAVOS = 9999999999; // R$ 99.999.999,99 (cabe no DECIMAL(12,2))

    public static function normalizarCnpj(string $valor): string
    {
        return preg_replace('/\D/', '', $valor) ?? '';
    }

    /** Espera só dígitos (use normalizarCnpj antes). Confere os dois dígitos verificadores. */
    public static function cnpj(string $cnpj): bool
    {
        if (!preg_match('/^\d{14}\z/', $cnpj) || preg_match('/^(\d)\1{13}\z/', $cnpj)) {
            return false; // tamanho errado ou todos os dígitos iguais (passam na conta, mas são inválidos)
        }
        foreach ([12, 13] as $posicao) {
            $pesos = $posicao === 12 ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2] : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
            $soma = 0;
            foreach ($pesos as $i => $peso) {
                $soma += (int) $cnpj[$i] * $peso;
            }
            $resto = $soma % 11;
            $digito = $resto < 2 ? 0 : 11 - $resto;
            if ((int) $cnpj[$posicao] !== $digito) {
                return false;
            }
        }
        return true;
    }

    public static function email(string $email): bool
    {
        return strlen($email) <= 150 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Converte "1.234,56" em 123456 centavos. Só aceita o formato brasileiro:
     * "1234.56" é recusado de propósito porque "1.234" seria ambíguo (mil ou um vírgula dois três quatro).
     */
    public static function valorParaCentavos(string $valor): ?int
    {
        if (!preg_match('/^(\d{1,3}(\.\d{3})+|\d+)(,\d{1,2})?\z/', $valor)) {
            return null;
        }
        [$inteiro, $decimal] = array_pad(explode(',', str_replace('.', '', $valor)), 2, '');
        if (strlen(ltrim($inteiro, '0')) > 10) {
            return null; // evita estouro antes mesmo do cast para int
        }
        $centavos = (int) $inteiro * 100 + (int) str_pad($decimal, 2, '0');

        return $centavos <= self::VALOR_MAXIMO_CENTAVOS ? $centavos : null;
    }

    /** Data real (rejeita 2026-02-31, que o PHP aceitaria como "março"). */
    public static function data(string $valor): ?DateTimeImmutable
    {
        $data = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
        return $data !== false && $data->format('Y-m-d') === $valor ? $data : null;
    }

    public static function dataNaoFutura(string $valor, DateTimeImmutable $hoje): bool
    {
        $data = self::data($valor);
        return $data !== null && $data <= $hoje->setTime(0, 0);
    }

    /** "2026-11" -> "2026-11-01" (competência é sempre o dia 1 do mês). */
    public static function competencia(string $valor): ?string
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])\z/', $valor)) {
            return null;
        }
        return $valor . '-01';
    }

    public static function nota(string $valor): ?int
    {
        return preg_match('/^[1-5]\z/', $valor) ? (int) $valor : null;
    }

    /** Tamanho em caracteres (não bytes) depois de trim. min = 0 torna o campo opcional. */
    public static function texto(string $valor, int $min, int $max): bool
    {
        $tamanho = mb_strlen(trim($valor));
        return $tamanho >= $min && $tamanho <= $max;
    }

    public static function senhaForte(string $senha): bool
    {
        return strlen($senha) >= 8 && preg_match('/[A-Za-z]/', $senha) === 1 && preg_match('/\d/', $senha) === 1;
    }
}

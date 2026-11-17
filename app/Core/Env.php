<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Lê o arquivo .env uma vez e guarda os valores em memória.
 * Por que não usar getenv/putenv: evita vazar segredos para processos filhos e phpinfo().
 */
final class Env
{
    /** @var array<string,string> */
    private static array $valores = [];

    public static function carregar(string $caminho): void
    {
        self::$valores = [];
        if (!is_file($caminho)) {
            return; // sem .env, valem os padrões de get()
        }
        foreach (file($caminho, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $linha) {
            $linha = trim($linha);
            if ($linha === '' || $linha[0] === '#' || !str_contains($linha, '=')) {
                continue;
            }
            [$chave, $valor] = explode('=', $linha, 2);
            self::$valores[trim($chave)] = trim($valor, " \t\"'");
        }
    }

    public static function get(string $chave, ?string $padrao = null): ?string
    {
        return self::$valores[$chave] ?? $padrao;
    }
}

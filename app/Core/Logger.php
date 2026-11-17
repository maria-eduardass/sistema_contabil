<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Registra exceções em storage/logs/app.log.
 * Por que: o usuário vê só uma página genérica; o detalhe técnico fica aqui.
 * Quebras de linha são removidas para ninguém forjar linhas falsas no log.
 */
final class Logger
{
    public static function erro(Throwable $e, ?string $arquivo = null): void
    {
        $arquivo ??= RAIZ . '/storage/logs/app.log';
        $linha = sprintf(
            '[%s] %s: %s em %s:%d | %s',
            date('Y-m-d H:i:s'),
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );
        $linha = preg_replace('/\s*[\r\n]+\s*/', ' | ', $linha) ?? $linha;
        if (@file_put_contents($arquivo, $linha . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            error_log($linha); // último recurso: log do PHP
        }
    }
}

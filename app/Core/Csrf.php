<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Proteção contra CSRF: todo POST precisa carregar o token secreto da sessão.
 * Por que funciona: um site malicioso consegue disparar o POST com os cookies da vítima,
 * mas não consegue LER o token, então não consegue incluí-lo na requisição.
 */
final class Csrf
{
    private const CHAVE = 'csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::CHAVE]) || !is_string($_SESSION[self::CHAVE])) {
            $_SESSION[self::CHAVE] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::CHAVE];
    }

    /** Troca o token (chamar no login, junto com session_regenerate_id). */
    public static function regenerar(): void
    {
        unset($_SESSION[self::CHAVE]);
        self::token();
    }

    /** hash_equals compara em tempo constante, sem vazar o token por diferença de tempo. */
    public static function validar(?string $enviado): bool
    {
        $esperado = $_SESSION[self::CHAVE] ?? '';
        return is_string($esperado) && $esperado !== '' && $enviado !== null && hash_equals($esperado, $enviado);
    }

    /** @throws HttpException 403 se o token for inválido */
    public static function exigir(?string $enviado): void
    {
        if (!self::validar($enviado)) {
            throw new HttpException(403);
        }
    }

    /** Campo hidden para formulários HTML. */
    public static function campo(): string
    {
        return '<input type="hidden" name="' . self::CHAVE . '" value="' . e(self::token()) . '">';
    }
}

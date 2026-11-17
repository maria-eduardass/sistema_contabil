<?php
declare(strict_types=1);

namespace App\Core;

/** Respostas JSON seguras (usadas pelo velocímetro e pela avaliação). */
final class Json
{
    /**
     * Os flags HEX_* trocam < > & ' " por \uXXXX. Assim, mesmo que o JSON seja colado dentro de
     * uma página HTML, nenhum texto vindo do banco consegue fechar uma tag ou um atributo (XSS).
     */
    public static function codificar(mixed $dados): string
    {
        return json_encode(
            $dados,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }

    public static function enviar(mixed $dados, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store'); // o termômetro muda com o tempo: nunca reutilizar resposta antiga
        echo self::codificar($dados);
    }
}

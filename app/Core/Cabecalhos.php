<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Cabeçalhos HTTP de segurança, enviados em toda resposta gerada pelo PHP.
 *
 * Por que a CSP é a defesa mais importante contra XSS: mesmo que um texto malicioso escape do
 * e() algum dia, o navegador se recusa a executar script que não venha do próprio site (ou do CDN
 * do Bootstrap). Isso só é possível porque o sistema NÃO usa nenhum JavaScript ou estilo inline;
 * um teste estático (SegurancaEstaticaTeste) garante que isso continue verdadeiro.
 */
final class Cabecalhos
{
    private const CDN = 'https://cdn.jsdelivr.net'; // Bootstrap (com SRI nas tags <link>/<script>)

    /** @return array<string,string> nome => valor */
    public static function lista(bool $https): array
    {
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' " . self::CDN,
            "style-src 'self' " . self::CDN,
            "img-src 'self' data:",      // data: para os ícones SVG embutidos no CSS do Bootstrap
            "font-src 'self'",
            "connect-src 'self'",        // o fetch do JS só pode falar com o próprio servidor
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);

        $cabecalhos = [
            'Content-Security-Policy' => $csp,
            'X-Frame-Options' => 'DENY', // clickjacking (para navegadores antigos que ignoram frame-ancestors)
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            // Páginas com dados de clientes não ficam no cache: o botão "voltar" após o logout não as reexibe.
            'Cache-Control' => 'no-store, max-age=0',
        ];
        if ($https) {
            $cabecalhos['Strict-Transport-Security'] = 'max-age=31536000'; // só faz sentido (e só vale) em HTTPS
        }

        return $cabecalhos;
    }

    public static function enviar(bool $https): void
    {
        foreach (self::lista($https) as $nome => $valor) {
            header($nome . ': ' . $valor);
        }
    }
}

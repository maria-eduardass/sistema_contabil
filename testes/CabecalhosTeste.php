<?php
declare(strict_types=1);

use App\Core\Cabecalhos;

/** Diretiva da CSP (ex.: "script-src") -> lista de fontes. */
function diretivaCspTeste(string $nome, bool $https = false): array
{
    foreach (explode(';', Cabecalhos::lista($https)['Content-Security-Policy']) as $parte) {
        $itens = preg_split('/\s+/', trim($parte));
        if (($itens[0] ?? '') === $nome) {
            return array_slice($itens, 1);
        }
    }
    return [];
}

teste('Cabeçalhos: a CSP não libera código inline nem eval, e não usa curinga', function () {
    $csp = Cabecalhos::lista(false)['Content-Security-Policy'];
    afirmarFalso(str_contains($csp, 'unsafe-inline'));
    afirmarFalso(str_contains($csp, 'unsafe-eval'));
    afirmarFalso(str_contains($csp, ' * '));
    afirmarFalso(preg_match('/\s\*(;|$)/', $csp) === 1);
});

teste('Cabeçalhos: a CSP fecha objetos, base, formulários e enquadramento', function () {
    afirmarIgual(["'none'"], diretivaCspTeste('object-src'));
    afirmarIgual(["'self'"], diretivaCspTeste('base-uri'));
    afirmarIgual(["'self'"], diretivaCspTeste('form-action'));
    afirmarIgual(["'none'"], diretivaCspTeste('frame-ancestors'));
    afirmarIgual(["'self'"], diretivaCspTeste('default-src'));
});

teste('Cabeçalhos: scripts só do próprio site e do CDN do Bootstrap', function () {
    afirmarIgual(["'self'", 'https://cdn.jsdelivr.net'], diretivaCspTeste('script-src'));
    afirmarIgual(["'self'", 'https://cdn.jsdelivr.net'], diretivaCspTeste('style-src'));
    afirmarIgual(["'self'"], diretivaCspTeste('connect-src')); // o fetch só fala com o próprio servidor
});

teste('Cabeçalhos: anti-clickjacking, anti-sniffing e política de referência', function () {
    $h = Cabecalhos::lista(false);
    afirmarIgual('DENY', $h['X-Frame-Options']);
    afirmarIgual('nosniff', $h['X-Content-Type-Options']);
    afirmarIgual('same-origin', $h['Referrer-Policy']);
    afirmarVerdadeiro(str_contains($h['Permissions-Policy'], 'camera=()'));
    afirmarVerdadeiro(str_contains($h['Permissions-Policy'], 'microphone=()'));
});

teste('Cabeçalhos: páginas dinâmicas não vão para o cache (botão voltar após logout)', function () {
    afirmarVerdadeiro(str_contains(Cabecalhos::lista(false)['Cache-Control'], 'no-store'));
});

teste('Cabeçalhos: HSTS só é enviado em HTTPS', function () {
    afirmarFalso(array_key_exists('Strict-Transport-Security', Cabecalhos::lista(false)));
    afirmarVerdadeiro(str_contains(Cabecalhos::lista(true)['Strict-Transport-Security'], 'max-age='));
});

teste('Cabeçalhos: nenhum valor tem quebra de linha (injeção de cabeçalho)', function () {
    foreach ([false, true] as $https) {
        foreach (Cabecalhos::lista($https) as $nome => $valor) {
            afirmarFalso(preg_match('/[\r\n]/', $nome . $valor) === 1, $nome);
        }
    }
});

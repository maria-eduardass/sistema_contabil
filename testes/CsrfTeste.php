<?php
declare(strict_types=1);

use App\Core\Csrf;
use App\Core\HttpException;

teste('Csrf: gera token hexadecimal de 64 caracteres e o mantém na sessão', function () {
    $_SESSION = [];
    $token = Csrf::token();
    afirmarIgual(1, preg_match('/^[0-9a-f]{64}$/', $token));
    afirmarIgual($token, Csrf::token());
});

teste('Csrf: aceita o token correto', function () {
    $_SESSION = [];
    afirmarVerdadeiro(Csrf::validar(Csrf::token()));
});

teste('Csrf: rejeita token ausente, vazio, adulterado ou de tamanho diferente', function () {
    $_SESSION = [];
    $token = Csrf::token();
    afirmarFalso(Csrf::validar(null));
    afirmarFalso(Csrf::validar(''));
    afirmarFalso(Csrf::validar(strrev($token)));
    afirmarFalso(Csrf::validar(substr($token, 0, 10)));
    afirmarFalso(Csrf::validar($token . 'x'));
});

teste('Csrf: sem token na sessão nada é válido (inclusive string vazia)', function () {
    $_SESSION = [];
    afirmarFalso(Csrf::validar(''));
    afirmarFalso(Csrf::validar('abc'));
});

teste('Csrf: regenerar troca o token e invalida o antigo', function () {
    $_SESSION = [];
    $antigo = Csrf::token();
    Csrf::regenerar();
    afirmarFalso(Csrf::validar($antigo));
    afirmarVerdadeiro(Csrf::validar(Csrf::token()));
});

teste('Csrf: campo() gera input hidden com o token escapado', function () {
    $_SESSION = [];
    $html = Csrf::campo();
    afirmarVerdadeiro(str_contains($html, 'type="hidden"'));
    afirmarVerdadeiro(str_contains($html, 'name="csrf_token"'));
    afirmarVerdadeiro(str_contains($html, 'value="' . Csrf::token() . '"'));
});

teste('Csrf: exigir() lança 403 para token inválido e passa para o válido', function () {
    $_SESSION = [];
    $token = Csrf::token();
    Csrf::exigir($token); // não deve lançar
    try {
        Csrf::exigir('invalido');
        throw new AssertionError('Deveria lançar HttpException');
    } catch (HttpException $e) {
        afirmarIgual(403, $e->codigoHttp());
    }
});

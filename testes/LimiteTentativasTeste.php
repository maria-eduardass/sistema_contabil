<?php
declare(strict_types=1);

use App\Services\LimiteTentativas;

teste('Limite de login: 4 falhas ainda não bloqueiam', function () {
    $estado = [];
    $limite = new LimiteTentativas($estado);
    for ($i = 0; $i < 4; $i++) {
        $limite->registrarFalha(1000);
    }
    afirmarFalso($limite->bloqueado(1000));
});

teste('Limite de login: a 5ª falha bloqueia por 5 minutos', function () {
    $estado = [];
    $limite = new LimiteTentativas($estado);
    for ($i = 0; $i < 5; $i++) {
        $limite->registrarFalha(1000);
    }
    afirmarVerdadeiro($limite->bloqueado(1000));
    afirmarIgual(300, $limite->segundosRestantes(1000));
    afirmarIgual(100, $limite->segundosRestantes(1200));
});

teste('Limite de login: libera quando o prazo termina e zera o contador', function () {
    $estado = [];
    $limite = new LimiteTentativas($estado);
    for ($i = 0; $i < 5; $i++) {
        $limite->registrarFalha(1000);
    }
    afirmarVerdadeiro($limite->bloqueado(1299));
    afirmarFalso($limite->bloqueado(1300));
    // Recomeça do zero: mais 4 falhas não bloqueiam.
    for ($i = 0; $i < 4; $i++) {
        $limite->registrarFalha(1300);
    }
    afirmarFalso($limite->bloqueado(1300));
});

teste('Limite de login: sucesso (zerar) apaga as falhas acumuladas', function () {
    $estado = [];
    $limite = new LimiteTentativas($estado);
    for ($i = 0; $i < 4; $i++) {
        $limite->registrarFalha(1000);
    }
    $limite->zerar();
    $limite->registrarFalha(1000);
    afirmarFalso($limite->bloqueado(1000));
});

teste('Limite de login: o estado vive no array informado (sessão) e sobrevive a nova instância', function () {
    $estado = [];
    $a = new LimiteTentativas($estado);
    for ($i = 0; $i < 5; $i++) {
        $a->registrarFalha(1000);
    }
    $b = new LimiteTentativas($estado);
    afirmarVerdadeiro($b->bloqueado(1001));
});

teste('Limite de login: durante o bloqueio, novas falhas não estendem o prazo', function () {
    $estado = [];
    $limite = new LimiteTentativas($estado);
    for ($i = 0; $i < 5; $i++) {
        $limite->registrarFalha(1000);
    }
    $limite->registrarFalha(1100);
    afirmarIgual(200, $limite->segundosRestantes(1100));
});

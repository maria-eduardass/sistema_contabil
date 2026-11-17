<?php
declare(strict_types=1);

use App\Core\Validator;

// --- CNPJ ---
teste('Validator: aceita CNPJs válidos do seed', function () {
    foreach (['11222333000181', '11444777000161', '00000000000191'] as $cnpj) {
        afirmarVerdadeiro(Validator::cnpj($cnpj), $cnpj);
    }
});

teste('Validator: rejeita CNPJ com dígito verificador errado', function () {
    afirmarFalso(Validator::cnpj('11222333000182'));
    afirmarFalso(Validator::cnpj('11222333000191'));
});

teste('Validator: rejeita CNPJ com dígitos repetidos, tamanho errado ou letras', function () {
    afirmarFalso(Validator::cnpj('11111111111111'));
    afirmarFalso(Validator::cnpj('00000000000000'));
    afirmarFalso(Validator::cnpj('1122233300018'));
    afirmarFalso(Validator::cnpj('112223330001811'));
    afirmarFalso(Validator::cnpj('1122233300018A'));
    afirmarFalso(Validator::cnpj(''));
});

teste('Validator: normalizarCnpj remove pontuação', function () {
    afirmarIgual('11222333000181', Validator::normalizarCnpj('11.222.333/0001-81'));
    afirmarIgual('', Validator::normalizarCnpj('abc'));
});

// --- E-mail ---
teste('Validator: e-mail válido e inválido', function () {
    afirmarVerdadeiro(Validator::email('contador@fiscalpulse.test'));
    afirmarFalso(Validator::email('sem-arroba'));
    afirmarFalso(Validator::email('a@'));
    afirmarFalso(Validator::email(''));
    afirmarFalso(Validator::email("a@b.com\nBcc: x@y.com")); // injeção de cabeçalho
});

teste('Validator: e-mail acima de 150 caracteres é rejeitado', function () {
    afirmarFalso(Validator::email(str_repeat('a', 145) . '@b.com'));
});

// --- Valor monetário (formato brasileiro) ---
teste('Validator: converte valor BR para centavos', function () {
    afirmarIgual(123456, Validator::valorParaCentavos('1.234,56'));
    afirmarIgual(123456, Validator::valorParaCentavos('1234,56'));
    afirmarIgual(100000, Validator::valorParaCentavos('1000'));
    afirmarIgual(1050, Validator::valorParaCentavos('10,5'));
    afirmarIgual(0, Validator::valorParaCentavos('0'));
    afirmarIgual(0, Validator::valorParaCentavos('0,00'));
    afirmarIgual(9999999999, Validator::valorParaCentavos('99.999.999,99'));
});

teste('Validator: rejeita valor mal formatado, negativo, ambíguo ou acima do máximo', function () {
    foreach (['', 'abc', '-10', '1234.56', '1.23', '1,234', '10,', ',50', '1..000,00', '100.000.000,00', '1e3', ' 10'] as $ruim) {
        afirmarIgual(null, Validator::valorParaCentavos($ruim), "'$ruim'");
    }
});

// --- Datas ---
teste('Validator: data real no formato Y-m-d', function () {
    afirmarVerdadeiro(Validator::data('2026-11-17') instanceof DateTimeImmutable);
    afirmarVerdadeiro(Validator::data('2024-02-29') instanceof DateTimeImmutable); // bissexto
    afirmarIgual(null, Validator::data('2026-02-31'));
    afirmarIgual(null, Validator::data('2025-02-29'));
    afirmarIgual(null, Validator::data('17/11/2026'));
    afirmarIgual(null, Validator::data(''));
});

teste('Validator: data de pagamento não pode ser futura (hoje pode)', function () {
    $hoje = new DateTimeImmutable('2026-11-17 15:30:00');
    afirmarVerdadeiro(Validator::dataNaoFutura('2026-11-17', $hoje));
    afirmarVerdadeiro(Validator::dataNaoFutura('2026-11-16', $hoje));
    afirmarFalso(Validator::dataNaoFutura('2026-11-18', $hoje));
    afirmarFalso(Validator::dataNaoFutura('lixo', $hoje));
});

teste('Validator: competência YYYY-MM vira o dia 01 e rejeita mês inválido', function () {
    afirmarIgual('2026-11-01', Validator::competencia('2026-11'));
    afirmarIgual('2026-01-01', Validator::competencia('2026-01'));
    afirmarIgual(null, Validator::competencia('2026-13'));
    afirmarIgual(null, Validator::competencia('2026-00'));
    afirmarIgual(null, Validator::competencia('2026-1'));
    afirmarIgual(null, Validator::competencia('11/2026'));
});

// --- Nota, texto, senha ---
teste('Validator: nota só aceita inteiros de 1 a 5', function () {
    afirmarIgual(1, Validator::nota('1'));
    afirmarIgual(5, Validator::nota('5'));
    foreach (['0', '6', '-1', '3.5', 'abc', '', '05', '1 '] as $ruim) {
        afirmarIgual(null, Validator::nota($ruim), "'$ruim'");
    }
});

teste('Validator: texto respeita mínimo e máximo após trim, contando caracteres UTF-8', function () {
    afirmarVerdadeiro(Validator::texto('  Padaria  ', 3, 10));
    afirmarFalso(Validator::texto('   ab ', 3, 10));
    afirmarFalso(Validator::texto(str_repeat('a', 11), 1, 10));
    afirmarVerdadeiro(Validator::texto('ção', 3, 3)); // 3 caracteres, 5 bytes
    afirmarVerdadeiro(Validator::texto('', 0, 10)); // opcional
});

teste('Validator: senha exige 8+ caracteres com letra e número', function () {
    afirmarVerdadeiro(Validator::senhaForte('Cliente@123'));
    afirmarVerdadeiro(Validator::senhaForte('abcdefg1'));
    afirmarFalso(Validator::senhaForte('abc123'));       // curta
    afirmarFalso(Validator::senhaForte('abcdefghij'));   // sem número
    afirmarFalso(Validator::senhaForte('12345678'));     // sem letra
    afirmarFalso(Validator::senhaForte(''));
});

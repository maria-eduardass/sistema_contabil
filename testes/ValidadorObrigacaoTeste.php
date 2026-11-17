<?php
declare(strict_types=1);

use App\Services\ValidadorObrigacao;

const TIPOS_TESTE = [1, 2, 3];

function entradaObrigacaoTeste(array $sobrescrever = []): array
{
    return $sobrescrever + [
        'tipo_obrigacao_id' => '2',
        'competencia' => '2026-11',
        'data_vencimento' => '2026-11-20',
        'valor' => '1.234,56',
        'observacao' => '  Guia enviada por e-mail ',
    ];
}

function validarObrigacaoTeste(array $entrada, string $hoje = '2026-11-17'): array
{
    return ValidadorObrigacao::validar($entrada, TIPOS_TESTE, new DateTimeImmutable($hoje));
}

teste('ValidadorObrigacao: entrada válida devolve dados normalizados (valor em centavos)', function () {
    $r = validarObrigacaoTeste(entradaObrigacaoTeste());
    afirmarIgual([], $r['erros']);
    afirmarIgual([
        'tipo_obrigacao_id' => 2,
        'competencia' => '2026-11-01',
        'data_vencimento' => '2026-11-20',
        'valor_centavos' => 123456,
        'observacao' => 'Guia enviada por e-mail',
        'status' => 'pendente',
    ], $r['dados']);
});

teste('ValidadorObrigacao: formulário vazio acusa erro em tipo, competência e vencimento', function () {
    $r = validarObrigacaoTeste([]);
    afirmarIgual(['tipo_obrigacao_id', 'competencia', 'data_vencimento'], array_keys($r['erros']));
});

teste('ValidadorObrigacao: tipo precisa existir na lista (rejeita inexistente, zero e texto)', function () {
    foreach (['99', '0', 'abc', '2 OR 1=1', '-1'] as $ruim) {
        afirmarVerdadeiro(isset(validarObrigacaoTeste(entradaObrigacaoTeste(['tipo_obrigacao_id' => $ruim]))['erros']['tipo_obrigacao_id']), $ruim);
    }
});

teste('ValidadorObrigacao: competência e vencimento inválidos', function () {
    afirmarVerdadeiro(isset(validarObrigacaoTeste(entradaObrigacaoTeste(['competencia' => '2026-13']))['erros']['competencia']));
    afirmarVerdadeiro(isset(validarObrigacaoTeste(entradaObrigacaoTeste(['data_vencimento' => '2026-02-31']))['erros']['data_vencimento']));
    afirmarVerdadeiro(isset(validarObrigacaoTeste(entradaObrigacaoTeste(['data_vencimento' => '20/11/2026']))['erros']['data_vencimento']));
});

teste('ValidadorObrigacao: vencimento anterior ao mês da competência é rejeitado; no dia 1º é aceito', function () {
    $antes = validarObrigacaoTeste(entradaObrigacaoTeste(['competencia' => '2026-11', 'data_vencimento' => '2026-10-31']));
    afirmarVerdadeiro(isset($antes['erros']['data_vencimento']));
    $dia1 = validarObrigacaoTeste(entradaObrigacaoTeste(['competencia' => '2026-11', 'data_vencimento' => '2026-11-01']));
    afirmarFalso(isset($dia1['erros']['data_vencimento']));
});

teste('ValidadorObrigacao: valor no formato brasileiro, vazio vale zero, lixo é rejeitado', function () {
    afirmarIgual(123456, validarObrigacaoTeste(entradaObrigacaoTeste(['valor' => '1.234,56']))['dados']['valor_centavos']);
    afirmarIgual(50, validarObrigacaoTeste(entradaObrigacaoTeste(['valor' => '0,5']))['dados']['valor_centavos']);
    afirmarIgual(0, validarObrigacaoTeste(entradaObrigacaoTeste(['valor' => '']))['dados']['valor_centavos']);
    afirmarFalso(isset(validarObrigacaoTeste(entradaObrigacaoTeste(['valor' => '']))['erros']['valor']));
    foreach (['abc', '-5', '1234.56', '100.000.000,00'] as $ruim) {
        afirmarVerdadeiro(isset(validarObrigacaoTeste(entradaObrigacaoTeste(['valor' => $ruim]))['erros']['valor']), $ruim);
    }
});

teste('ValidadorObrigacao: observação é opcional (vazia vira null) e limitada a 255 caracteres', function () {
    afirmarIgual(null, validarObrigacaoTeste(entradaObrigacaoTeste(['observacao' => '   ']))['dados']['observacao']);
    afirmarFalso(isset(validarObrigacaoTeste(entradaObrigacaoTeste(['observacao' => str_repeat('a', 255)]))['erros']['observacao']));
    afirmarVerdadeiro(isset(validarObrigacaoTeste(entradaObrigacaoTeste(['observacao' => str_repeat('a', 256)]))['erros']['observacao']));
});

teste('ValidadorObrigacao: status inicial é atrasado só se o vencimento já passou (hoje ainda é pendente)', function () {
    afirmarIgual('atrasado', validarObrigacaoTeste(entradaObrigacaoTeste(['data_vencimento' => '2026-11-16']))['dados']['status']);
    afirmarIgual('pendente', validarObrigacaoTeste(entradaObrigacaoTeste(['data_vencimento' => '2026-11-17']))['dados']['status']);
    afirmarIgual('pendente', validarObrigacaoTeste(entradaObrigacaoTeste(['data_vencimento' => '2026-11-18']))['dados']['status']);
});

teste('ValidadorObrigacao: campos que o usuário não pode definir (status, empresa, pagamento) são ignorados', function () {
    $r = validarObrigacaoTeste(entradaObrigacaoTeste(['status' => 'pago', 'empresa_id' => '99', 'data_pagamento' => '2026-11-01']));
    afirmarIgual('pendente', $r['dados']['status']);
    afirmarIgual(
        ['tipo_obrigacao_id', 'competencia', 'data_vencimento', 'valor_centavos', 'observacao', 'status'],
        array_keys($r['dados'])
    );
});

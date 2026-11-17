<?php
declare(strict_types=1);

use App\Services\ValidadorEmpresa;

function entradaEmpresaTeste(array $sobrescrever = []): array
{
    return $sobrescrever + [
        'razao_social' => '  Padaria Nova Ltda ',
        'cnpj' => '11.222.333/0001-81',
        'regime_tributario' => 'simples',
        'email_contato' => '  Contato@Padaria.TEST ',
    ];
}

teste('ValidadorEmpresa: entrada válida devolve dados normalizados e nenhum erro', function () {
    $r = ValidadorEmpresa::validar(entradaEmpresaTeste());
    afirmarIgual([], $r['erros']);
    afirmarIgual([
        'razao_social' => 'Padaria Nova Ltda',
        'cnpj' => '11222333000181',
        'regime_tributario' => 'simples',
        'email_contato' => 'contato@padaria.test',
    ], $r['dados']);
});

teste('ValidadorEmpresa: formulário vazio acusa erro em cada um dos 4 campos', function () {
    $r = ValidadorEmpresa::validar([]);
    afirmarIgual(['razao_social', 'cnpj', 'regime_tributario', 'email_contato'], array_keys($r['erros']));
});

teste('ValidadorEmpresa: razão social curta demais ou longa demais', function () {
    afirmarVerdadeiro(isset(ValidadorEmpresa::validar(entradaEmpresaTeste(['razao_social' => 'ab']))['erros']['razao_social']));
    afirmarVerdadeiro(isset(ValidadorEmpresa::validar(entradaEmpresaTeste(['razao_social' => str_repeat('a', 151)]))['erros']['razao_social']));
    afirmarFalso(isset(ValidadorEmpresa::validar(entradaEmpresaTeste(['razao_social' => str_repeat('a', 150)]))['erros']['razao_social']));
});

teste('ValidadorEmpresa: CNPJ inválido é rejeitado; com ou sem máscara é aceito', function () {
    afirmarVerdadeiro(isset(ValidadorEmpresa::validar(entradaEmpresaTeste(['cnpj' => '11.222.333/0001-82']))['erros']['cnpj']));
    afirmarVerdadeiro(isset(ValidadorEmpresa::validar(entradaEmpresaTeste(['cnpj' => 'abc']))['erros']['cnpj']));
    afirmarFalso(isset(ValidadorEmpresa::validar(entradaEmpresaTeste(['cnpj' => '11222333000181']))['erros']['cnpj']));
});

teste('ValidadorEmpresa: regime fora da lista é rejeitado (inclui tentativa de injeção)', function () {
    afirmarVerdadeiro(isset(ValidadorEmpresa::validar(entradaEmpresaTeste(['regime_tributario' => 'outro']))['erros']['regime_tributario']));
    afirmarVerdadeiro(isset(ValidadorEmpresa::validar(entradaEmpresaTeste(['regime_tributario' => "mei' OR '1'='1"]))['erros']['regime_tributario']));
    foreach (['mei', 'simples', 'presumido', 'real'] as $regime) {
        afirmarFalso(isset(ValidadorEmpresa::validar(entradaEmpresaTeste(['regime_tributario' => $regime]))['erros']['regime_tributario']));
    }
});

teste('ValidadorEmpresa: e-mail inválido é rejeitado', function () {
    afirmarVerdadeiro(isset(ValidadorEmpresa::validar(entradaEmpresaTeste(['email_contato' => 'sem-arroba']))['erros']['email_contato']));
});

teste('ValidadorEmpresa: dados só trazem campos válidos mesmo com campos extras na entrada', function () {
    $r = ValidadorEmpresa::validar(entradaEmpresaTeste(['ativa' => '0', 'id' => '99']));
    afirmarIgual(['razao_social', 'cnpj', 'regime_tributario', 'email_contato'], array_keys($r['dados']));
});

teste('ValidadorEmpresa: sem conferir o dígito, qualquer CNPJ de 14 números vale (cadastro escolar)', function () {
    $entrada = entradaEmpresaTeste(['cnpj' => '11.111.111/1111-11']);
    afirmarVerdadeiro(isset(ValidadorEmpresa::validar($entrada)['erros']['cnpj'])); // padrão: confere o dígito
    $r = ValidadorEmpresa::validar($entrada, false);
    afirmarFalso(isset($r['erros']['cnpj']));
    afirmarIgual('11111111111111', $r['dados']['cnpj']);
});

teste('ValidadorEmpresa: sem conferir o dígito, o formato (14 números) continua obrigatório', function () {
    foreach (['123', '1234567890123', 'abc', ''] as $ruim) {
        afirmarVerdadeiro(isset(ValidadorEmpresa::validar(entradaEmpresaTeste(['cnpj' => $ruim]), false)['erros']['cnpj']), "'$ruim'");
    }
});

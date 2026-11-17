<?php
declare(strict_types=1);

use App\Services\ValidadorCadastro;

function entradaCadastroTeste(array $sobrescrever = []): array
{
    return $sobrescrever + [
        'nome' => '  Maria Souza ',
        'email' => '  Maria@Padaria.TEST ',
        'senha' => 'Senha@123',
        'senha_confirmacao' => 'Senha@123',
        'razao_social' => '  Padaria da Maria Ltda ',
        'cnpj' => '45.723.174/0001-10',
        'regime_tributario' => 'simples',
    ];
}

teste('Cadastro (validação): entrada válida devolve dados normalizados e nenhum erro', function () {
    $r = ValidadorCadastro::validar(entradaCadastroTeste());
    afirmarIgual([], $r['erros']);
    afirmarIgual([
        'nome' => 'Maria Souza',
        'email' => 'maria@padaria.test',
        'senha' => 'Senha@123',
        'razao_social' => 'Padaria da Maria Ltda',
        'cnpj' => '45723174000110',
        'regime_tributario' => 'simples',
        'email_contato' => 'maria@padaria.test',
    ], $r['dados']);
});

teste('Cadastro (validação): o e-mail de contato da empresa é o próprio e-mail da conta', function () {
    $r = ValidadorCadastro::validar(entradaCadastroTeste(['email' => 'Dono@Loja.TEST']));
    afirmarIgual('dono@loja.test', $r['dados']['email_contato']);
});

teste('Cadastro (validação): formulário vazio acusa erro em todos os campos', function () {
    $erros = ValidadorCadastro::validar([])['erros'];
    foreach (['nome', 'email', 'senha', 'razao_social', 'cnpj', 'regime_tributario'] as $campo) {
        afirmarVerdadeiro(isset($erros[$campo]), "faltou erro em $campo");
    }
});

teste('Cadastro (validação): a confirmação precisa ser igual à senha', function () {
    $r = ValidadorCadastro::validar(entradaCadastroTeste(['senha_confirmacao' => 'Outra@123']));
    afirmarVerdadeiro(isset($r['erros']['senha_confirmacao']));
    afirmarFalso(isset($r['erros']['senha']));
    afirmarVerdadeiro(isset(ValidadorCadastro::validar(entradaCadastroTeste(['senha_confirmacao' => '']))['erros']['senha_confirmacao']));
});

teste('Cadastro (validação): senha fraca e e-mail inválido são rejeitados, com a regra do cadastro de usuário', function () {
    afirmarVerdadeiro(isset(ValidadorCadastro::validar(entradaCadastroTeste(['senha' => 'abc', 'senha_confirmacao' => 'abc']))['erros']['senha']));
    afirmarVerdadeiro(isset(ValidadorCadastro::validar(entradaCadastroTeste(['email' => 'sem-arroba']))['erros']['email']));
});

teste('Cadastro (validação): CNPJ inválido, razão social curta e regime fora da lista são rejeitados', function () {
    $erros = ValidadorCadastro::validar(entradaCadastroTeste(['cnpj' => '11.111.111/1111-11', 'razao_social' => 'ab', 'regime_tributario' => 'outro']))['erros'];
    afirmarVerdadeiro(isset($erros['cnpj']) && isset($erros['razao_social']) && isset($erros['regime_tributario']));
});

teste('Cadastro (validação): o e-mail inválido não gera erro duplicado em "email_contato"', function () {
    $erros = ValidadorCadastro::validar(entradaCadastroTeste(['email' => 'ruim']))['erros'];
    afirmarFalso(isset($erros['email_contato']), 'o campo email_contato não existe no formulário');
});

teste('Cadastro (validação): campos que a pessoa não pode escolher (perfil, empresa, ativo) são ignorados', function () {
    $r = ValidadorCadastro::validar(entradaCadastroTeste(['perfil' => 'contador', 'empresa_id' => '1', 'ativo' => '0', 'ativa' => '0']));
    afirmarEqualsChaves($r['dados']);
});

function afirmarEqualsChaves(array $dados): void
{
    $chaves = array_keys($dados);
    sort($chaves);
    afirmarIgual(['cnpj', 'email', 'email_contato', 'nome', 'razao_social', 'regime_tributario', 'senha'], $chaves);
}

teste('Cadastro (validação): a senha não sofre trim (espaços fazem parte dela)', function () {
    $r = ValidadorCadastro::validar(entradaCadastroTeste(['senha' => ' Senha@123 ', 'senha_confirmacao' => ' Senha@123 ']));
    afirmarIgual(' Senha@123 ', $r['dados']['senha']);
    afirmarIgual([], $r['erros']);
});

teste('Cadastro (validação): valores que não são texto (arrays) não derrubam o validador', function () {
    $r = ValidadorCadastro::validar(['nome' => ['x'], 'email' => ['y'], 'senha' => ['z'], 'senha_confirmacao' => ['z'], 'cnpj' => ['1'], 'razao_social' => ['r'], 'regime_tributario' => ['mei']]);
    afirmarVerdadeiro(count($r['erros']) >= 6);
});

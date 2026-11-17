<?php
declare(strict_types=1);

use App\Services\ValidadorCadastro;
use App\Services\ValidadorEmpresa;
use App\Services\ValidadorUsuario;

/*
 * O cadastro PÚBLICO é deliberadamente permissivo (projeto escolar): a senha e os números (CNPJ, CPF,
 * telefone) aceitam praticamente qualquer coisa. O que continua valendo são os limites técnicos
 * (72 bytes de senha por causa do bcrypt, 14 dígitos de CNPJ por causa do banco, 30 caracteres nas colunas).
 */

function entradaCadastroTeste(array $sobrescrever = []): array
{
    return $sobrescrever + [
        'nome' => '  Maria Souza ',
        'email' => '  Maria@Padaria.TEST ',
        'cpf' => '',
        'telefone' => '',
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
        'cpf' => null,
        'telefone' => null,
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

teste('Cadastro (validação): formulário vazio acusa erro nos campos obrigatórios (CPF e telefone são opcionais)', function () {
    $erros = ValidadorCadastro::validar([])['erros'];
    foreach (['nome', 'email', 'senha', 'razao_social', 'cnpj', 'regime_tributario'] as $campo) {
        afirmarVerdadeiro(isset($erros[$campo]), "faltou erro em $campo");
    }
    afirmarFalso(isset($erros['cpf']) || isset($erros['telefone']));
});

// --- CPF e telefone: opcionais e livres ---
teste('Cadastro (validação): CPF e telefone aceitam QUALQUER texto, com trim', function () {
    $r = ValidadorCadastro::validar(entradaCadastroTeste(['cpf' => '  123  ', 'telefone' => ' abc (00) xyz ']));
    afirmarIgual([], $r['erros']);
    afirmarIgual('123', $r['dados']['cpf']);
    afirmarIgual('abc (00) xyz', $r['dados']['telefone']);
    foreach (['000.000.000-00', '11111111111', 'sem cpf', '99999-9999', '+55 (11) 91234-5678', '???'] as $livre) {
        afirmarIgual([], ValidadorCadastro::validar(entradaCadastroTeste(['cpf' => $livre, 'telefone' => $livre]))['erros'], $livre);
    }
});

teste('Cadastro (validação): CPF e telefone vazios ou só espaços viram null (campo opcional)', function () {
    $r = ValidadorCadastro::validar(entradaCadastroTeste(['cpf' => '   ', 'telefone' => '']));
    afirmarIgual(null, $r['dados']['cpf']);
    afirmarIgual(null, $r['dados']['telefone']);
});

teste('Cadastro (validação): CPF e telefone aceitam até 30 caracteres (limite da coluna) e recusam 31', function () {
    afirmarIgual([], ValidadorCadastro::validar(entradaCadastroTeste(['cpf' => str_repeat('1', 30), 'telefone' => str_repeat('á', 30)]))['erros']);
    $erros = ValidadorCadastro::validar(entradaCadastroTeste(['cpf' => str_repeat('1', 31), 'telefone' => str_repeat('2', 31)]))['erros'];
    afirmarVerdadeiro(isset($erros['cpf']) && isset($erros['telefone']));
});

// --- Senha: qualquer coisa não vazia ---
teste('Cadastro (validação): a senha pode ser qualquer coisa (curta, só números, só letras)', function () {
    foreach (['1', 'abc', '12345678', 'abcdefgh', 'a', '    '] as $fraca) {
        $r = ValidadorCadastro::validar(entradaCadastroTeste(['senha' => $fraca, 'senha_confirmacao' => $fraca]));
        afirmarIgual([], $r['erros'], "'$fraca'");
        afirmarIgual($fraca, $r['dados']['senha']);
    }
});

teste('Cadastro (validação): senha vazia continua recusada', function () {
    $r = ValidadorCadastro::validar(entradaCadastroTeste(['senha' => '', 'senha_confirmacao' => '']));
    afirmarVerdadeiro(isset($r['erros']['senha']));
});

teste('Cadastro (validação): senha acima de 72 bytes continua recusada (o bcrypt ignoraria o excedente)', function () {
    afirmarVerdadeiro(isset(ValidadorCadastro::validar(entradaCadastroTeste(['senha' => str_repeat('a', 73), 'senha_confirmacao' => str_repeat('a', 73)]))['erros']['senha']));
    afirmarIgual([], ValidadorCadastro::validar(entradaCadastroTeste(['senha' => str_repeat('a', 72), 'senha_confirmacao' => str_repeat('a', 72)]))['erros']);
});

teste('Cadastro (validação): a confirmação precisa ser igual à senha', function () {
    $r = ValidadorCadastro::validar(entradaCadastroTeste(['senha_confirmacao' => 'Outra@123']));
    afirmarVerdadeiro(isset($r['erros']['senha_confirmacao']));
    afirmarFalso(isset($r['erros']['senha']));
    afirmarVerdadeiro(isset(ValidadorCadastro::validar(entradaCadastroTeste(['senha_confirmacao' => '']))['erros']['senha_confirmacao']));
});

teste('Cadastro (validação): a senha não sofre trim (espaços fazem parte dela)', function () {
    $r = ValidadorCadastro::validar(entradaCadastroTeste(['senha' => ' Senha@123 ', 'senha_confirmacao' => ' Senha@123 ']));
    afirmarIgual(' Senha@123 ', $r['dados']['senha']);
    afirmarIgual([], $r['erros']);
});

// --- CNPJ: 14 dígitos quaisquer (sem conferir o dígito verificador) ---
teste('Cadastro (validação): CNPJ aceita qualquer sequência de 14 dígitos, com ou sem máscara', function () {
    foreach (['11111111111111', '12345678901234', '00.000.000/0000-00', '22.222.222/2222-22', '45.723.174/0001-10'] as $cnpj) {
        $r = ValidadorCadastro::validar(entradaCadastroTeste(['cnpj' => $cnpj]));
        afirmarIgual([], $r['erros'], $cnpj);
        afirmarIgual(14, strlen($r['dados']['cnpj']));
    }
});

teste('Cadastro (validação): CNPJ com menos ou mais de 14 números, ou sem números, é recusado', function () {
    foreach (['123', '1234567890123', '123456789012345', 'abc', ''] as $ruim) {
        $erros = ValidadorCadastro::validar(entradaCadastroTeste(['cnpj' => $ruim]))['erros'];
        afirmarVerdadeiro(isset($erros['cnpj']), "'$ruim'");
        afirmarVerdadeiro(str_contains($erros['cnpj'], '14'), 'a mensagem diz quantos números são');
    }
});

teste('Cadastro (validação): o cadastro feito pelo CONTADOR continua conferindo o dígito do CNPJ e exigindo senha forte', function () {
    afirmarVerdadeiro(isset(ValidadorEmpresa::validar(['razao_social' => 'Empresa X', 'cnpj' => '11111111111111', 'regime_tributario' => 'mei', 'email_contato' => 'a@b.test'])['erros']['cnpj']));
    afirmarVerdadeiro(isset(ValidadorUsuario::validar(['nome' => 'Ana', 'email' => 'a@b.test', 'senha' => 'abc'])['erros']['senha']));
});

// --- Demais campos (inalterados) ---
teste('Cadastro (validação): e-mail inválido, razão social curta e regime fora da lista são rejeitados', function () {
    $erros = ValidadorCadastro::validar(entradaCadastroTeste(['email' => 'sem-arroba', 'razao_social' => 'ab', 'regime_tributario' => 'outro']))['erros'];
    afirmarVerdadeiro(isset($erros['email']) && isset($erros['razao_social']) && isset($erros['regime_tributario']));
});

teste('Cadastro (validação): o e-mail inválido não gera erro duplicado em "email_contato"', function () {
    afirmarFalso(isset(ValidadorCadastro::validar(entradaCadastroTeste(['email' => 'ruim']))['erros']['email_contato']));
});

teste('Cadastro (validação): campos que a pessoa não pode escolher (perfil, empresa, ativo) são ignorados', function () {
    $r = ValidadorCadastro::validar(entradaCadastroTeste(['perfil' => 'contador', 'empresa_id' => '1', 'ativo' => '0', 'ativa' => '0']));
    $chaves = array_keys($r['dados']);
    sort($chaves);
    afirmarIgual(['cnpj', 'cpf', 'email', 'email_contato', 'nome', 'razao_social', 'regime_tributario', 'senha', 'telefone'], $chaves);
});

teste('Cadastro (validação): valores que não são texto (arrays) não derrubam o validador', function () {
    $r = ValidadorCadastro::validar(['nome' => ['x'], 'email' => ['y'], 'cpf' => ['1'], 'telefone' => ['2'], 'senha' => ['z'], 'senha_confirmacao' => ['z'], 'cnpj' => ['1'], 'razao_social' => ['r'], 'regime_tributario' => ['mei']]);
    afirmarVerdadeiro(count($r['erros']) >= 6);
    afirmarIgual(null, $r['dados']['cpf']);
});

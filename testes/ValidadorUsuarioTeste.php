<?php
declare(strict_types=1);

use App\Services\ValidadorUsuario;

function entradaUsuarioTeste(array $sobrescrever = []): array
{
    return $sobrescrever + ['nome' => '  Maria Silva ', 'email' => '  Maria@Cliente.TEST ', 'senha' => 'Senha@123'];
}

teste('ValidadorUsuario: entrada válida devolve dados normalizados e nenhum erro', function () {
    $r = ValidadorUsuario::validar(entradaUsuarioTeste());
    afirmarIgual([], $r['erros']);
    afirmarIgual(['nome' => 'Maria Silva', 'email' => 'maria@cliente.test', 'senha' => 'Senha@123'], $r['dados']);
});

teste('ValidadorUsuario: formulário vazio acusa erro nos 3 campos', function () {
    afirmarIgual(['nome', 'email', 'senha'], array_keys(ValidadorUsuario::validar([])['erros']));
});

teste('ValidadorUsuario: nome muito curto ou muito longo', function () {
    afirmarVerdadeiro(isset(ValidadorUsuario::validar(entradaUsuarioTeste(['nome' => 'A']))['erros']['nome']));
    afirmarVerdadeiro(isset(ValidadorUsuario::validar(entradaUsuarioTeste(['nome' => str_repeat('a', 121)]))['erros']['nome']));
});

teste('ValidadorUsuario: e-mail inválido é rejeitado', function () {
    afirmarVerdadeiro(isset(ValidadorUsuario::validar(entradaUsuarioTeste(['email' => 'sem-arroba']))['erros']['email']));
});

teste('ValidadorUsuario: senha fraca (curta, sem número, sem letra) é rejeitada', function () {
    foreach (['abc123', 'abcdefghij', '12345678', ''] as $fraca) {
        afirmarVerdadeiro(isset(ValidadorUsuario::validar(entradaUsuarioTeste(['senha' => $fraca]))['erros']['senha']), "'$fraca'");
    }
});

teste('ValidadorUsuario: senha acima de 72 bytes é rejeitada (o bcrypt ignoraria o excedente)', function () {
    $setentaEDois = str_repeat('a', 71) . '1';
    afirmarFalso(isset(ValidadorUsuario::validar(entradaUsuarioTeste(['senha' => $setentaEDois]))['erros']['senha']));
    afirmarVerdadeiro(isset(ValidadorUsuario::validar(entradaUsuarioTeste(['senha' => $setentaEDois . 'x']))['erros']['senha']));
});

teste('ValidadorUsuario: a senha não é alterada por trim (espaços fazem parte dela)', function () {
    afirmarIgual(' Senha@123 ', ValidadorUsuario::validar(entradaUsuarioTeste(['senha' => ' Senha@123 ']))['dados']['senha']);
});

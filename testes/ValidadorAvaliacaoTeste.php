<?php
declare(strict_types=1);

use App\Services\ValidadorAvaliacao;

teste('ValidadorAvaliacao: nota 1 a 5 com comentário é válida e normalizada', function () {
    $r = ValidadorAvaliacao::validar(['nota' => '4', 'comentario' => '  Muito claro o termômetro. ']);
    afirmarIgual([], $r['erros']);
    afirmarIgual(['nota' => 4, 'comentario' => 'Muito claro o termômetro.'], $r['dados']);
});

teste('ValidadorAvaliacao: aceita todas as notas de 1 a 5', function () {
    foreach (['1', '2', '3', '4', '5'] as $nota) {
        afirmarIgual([], ValidadorAvaliacao::validar(['nota' => $nota])['erros'], $nota);
    }
});

teste('ValidadorAvaliacao: nota fora de 1 a 5, vazia ou malformada é rejeitada', function () {
    foreach (['0', '6', '-1', '10', '3.5', 'abc', '', '5 OR 1=1', ' 5'] as $ruim) {
        afirmarVerdadeiro(isset(ValidadorAvaliacao::validar(['nota' => $ruim])['erros']['nota']), "'$ruim'");
    }
    afirmarVerdadeiro(isset(ValidadorAvaliacao::validar([])['erros']['nota']));
    afirmarVerdadeiro(isset(ValidadorAvaliacao::validar(['nota' => ['5']])['erros']['nota']));
});

teste('ValidadorAvaliacao: comentário é opcional (vazio vira null)', function () {
    afirmarIgual(null, ValidadorAvaliacao::validar(['nota' => '5', 'comentario' => '   '])['dados']['comentario']);
    afirmarIgual(null, ValidadorAvaliacao::validar(['nota' => '5'])['dados']['comentario']);
});

teste('ValidadorAvaliacao: comentário aceita até 500 caracteres e recusa 501', function () {
    afirmarFalso(isset(ValidadorAvaliacao::validar(['nota' => '5', 'comentario' => str_repeat('á', 500)])['erros']['comentario']));
    afirmarVerdadeiro(isset(ValidadorAvaliacao::validar(['nota' => '5', 'comentario' => str_repeat('á', 501)])['erros']['comentario']));
});

teste('ValidadorAvaliacao: preserva quebras de linha do comentário', function () {
    afirmarIgual("linha 1\nlinha 2", ValidadorAvaliacao::validar(['nota' => '3', 'comentario' => "linha 1\nlinha 2"])['dados']['comentario']);
});

teste('ValidadorAvaliacao: só devolve nota e comentário (usuário e data não vêm do formulário)', function () {
    $r = ValidadorAvaliacao::validar(['nota' => '5', 'usuario_id' => '1', 'criada_em' => '2000-01-01']);
    afirmarIgual(['nota', 'comentario'], array_keys($r['dados']));
});

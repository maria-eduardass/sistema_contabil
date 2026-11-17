<?php
declare(strict_types=1);

use App\Core\Flash;

teste('Flash: mensagem é entregue uma única vez', function () {
    $_SESSION = [];
    Flash::definir('sucesso', 'Salvo!');
    afirmarIgual([['tipo' => 'sucesso', 'mensagem' => 'Salvo!']], Flash::obter());
    afirmarIgual([], Flash::obter());
});

teste('Flash: guarda várias mensagens na ordem', function () {
    $_SESSION = [];
    Flash::definir('sucesso', 'a');
    Flash::definir('erro', 'b');
    afirmarIgual(['a', 'b'], array_column(Flash::obter(), 'mensagem'));
});

teste('Flash: sem mensagens devolve lista vazia', function () {
    $_SESSION = [];
    afirmarIgual([], Flash::obter());
});

teste('Flash: tipo desconhecido vira "erro" (nunca vai cru para uma classe CSS)', function () {
    $_SESSION = [];
    Flash::definir('<script>', 'x');
    afirmarIgual('erro', Flash::obter()[0]['tipo']);
});

<?php
declare(strict_types=1);

require_once __DIR__ . '/_ajuda_banco.php';

use App\Models\Avaliacao;

function comRollbackAvaliacaoTeste(callable $fn): void
{
    $pdo = bancoDeTeste();
    $pdo->beginTransaction();
    try {
        $fn($pdo, new Avaliacao($pdo));
    } finally {
        $pdo->rollBack();
    }
}

testeIntegracao('Avaliacao: cria e lista com nome e perfil de quem avaliou (nunca o hash)', function () {
    comRollbackAvaliacaoTeste(function ($pdo, $m) {
        $id = $m->criar(3, 4, 'Bom, mas poderia ter exportação.');
        afirmarVerdadeiro($id > 0);
        $primeira = $m->listarRecentes(10)[0];
        afirmarIgual('Ana da Tech', $primeira['nome']);
        afirmarIgual('cliente', $primeira['perfil']);
        afirmarIgual(4, (int) $primeira['nota']);
        afirmarIgual('Bom, mas poderia ter exportação.', $primeira['comentario']);
        afirmarFalso(array_key_exists('senha_hash', $primeira));
    });
});

testeIntegracao('Avaliacao: comentário nulo é aceito', function () {
    comRollbackAvaliacaoTeste(function ($pdo, $m) {
        $m->criar(1, 5, null);
        afirmarIgual(null, $m->listarRecentes(1)[0]['comentario']);
    });
});

testeIntegracao('Avaliacao: o mesmo usuário pode avaliar de novo e as duas ficam registradas', function () {
    comRollbackAvaliacaoTeste(function ($pdo, $m) {
        $antes = count($m->listarRecentes(100));
        $m->criar(2, 3, 'segunda opinião');
        $m->criar(2, 4, 'terceira opinião');
        afirmarIgual($antes + 2, count($m->listarRecentes(100)));
    });
});

testeIntegracao('Avaliacao: lista a mais recente primeiro e respeita o limite', function () {
    comRollbackAvaliacaoTeste(function ($pdo, $m) {
        $m->criar(1, 1, 'primeira');
        $m->criar(1, 2, 'segunda');
        $m->criar(1, 3, 'terceira');
        afirmarIgual(['terceira', 'segunda'], array_column($m->listarRecentes(2), 'comentario'));
    });
});

testeIntegracao('Avaliacao: o banco rejeita nota fora de 1 a 5 (CHECK), mesmo que a validação falhe', function () {
    comRollbackAvaliacaoTeste(function ($pdo, $m) {
        afirmarExcecao(PDOException::class, fn () => $m->criar(1, 0, 'x'));
        afirmarExcecao(PDOException::class, fn () => $m->criar(1, 6, 'x'));
    });
});

testeIntegracao('Avaliacao: o banco rejeita usuário inexistente (chave estrangeira)', function () {
    comRollbackAvaliacaoTeste(function ($pdo, $m) {
        afirmarExcecao(PDOException::class, fn () => $m->criar(99999, 5, 'x'));
    });
});

testeIntegracao('Avaliacao: distribuição traz as 5 notas, com zero onde ninguém votou', function () {
    comRollbackAvaliacaoTeste(function ($pdo, $m) {
        afirmarIgual([1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 1], $m->distribuicao()); // seed: uma nota 5
        $m->criar(1, 3, null);
        $m->criar(2, 3, null);
        afirmarIgual([1 => 0, 2 => 0, 3 => 2, 4 => 0, 5 => 1], $m->distribuicao());
    });
});

testeIntegracao('Avaliacao: texto com SQL e HTML é gravado literalmente (a escrita é segura, a saída é escapada na view)', function () {
    comRollbackAvaliacaoTeste(function ($pdo, $m) {
        $texto = "'); DROP TABLE avaliacoes; -- <script>alert(1)</script>";
        $m->criar(1, 5, $texto);
        afirmarIgual($texto, $m->listarRecentes(1)[0]['comentario']);
        afirmarVerdadeiro((int) $pdo->query('SELECT COUNT(*) FROM avaliacoes')->fetchColumn() >= 2);
    });
});

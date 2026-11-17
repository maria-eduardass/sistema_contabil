<?php
declare(strict_types=1);

require_once __DIR__ . '/_ajuda_banco.php';

testeIntegracao('Database: a conexão usa modo estrito (texto grande demais dá erro, não é cortado em silêncio)', function () {
    $modo = (string) bancoDeTeste()->query('SELECT @@SESSION.sql_mode')->fetchColumn();
    afirmarVerdadeiro(str_contains($modo, 'STRICT_TRANS_TABLES'), "sql_mode da sessão: $modo");
});

testeIntegracao('Database: um INSERT com texto maior que a coluna é recusado pelo banco', function () {
    $pdo = bancoDeTeste();
    $pdo->beginTransaction();
    try {
        afirmarExcecao(PDOException::class, fn () => $pdo->prepare(
            "INSERT INTO empresas (razao_social, cnpj, regime_tributario, email_contato) VALUES (:r, '45723174000110', 'mei', 'a@b.test')"
        )->execute(['r' => str_repeat('x', 400)])); // a coluna aceita 150
    } finally {
        $pdo->rollBack();
    }
});

testeIntegracao('Database: valor fora da lista de um ENUM é recusado (não vira texto vazio)', function () {
    $pdo = bancoDeTeste();
    $pdo->beginTransaction();
    try {
        afirmarExcecao(PDOException::class, fn () => $pdo->exec(
            "INSERT INTO empresas (razao_social, cnpj, regime_tributario, email_contato) VALUES ('Teste Enum', '45723174000110', 'inexistente', 'a@b.test')"
        ));
    } finally {
        $pdo->rollBack();
    }
});

testeIntegracao('Database: preserva os outros modos que o servidor já usava', function () {
    $modo = (string) bancoDeTeste()->query('SELECT @@SESSION.sql_mode')->fetchColumn();
    afirmarFalso(str_starts_with($modo, ','), 'sem vírgula sobrando no começo');
    afirmarFalso(str_contains($modo, ',,'));
});

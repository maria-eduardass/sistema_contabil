<?php
declare(strict_types=1);

require_once __DIR__ . '/_ajuda_banco.php';

/*
 * Por que esta suíte existe: o MariaDB do XAMPP vem SEM modo estrito. Sem ele, um texto maior que a coluna é cortado
 * em silêncio e um ENUM inválido vira vazio, ou seja, o dado gravado não é o que foi enviado e nenhum erro avisa.
 * A conexão (Database::conexao) liga o modo estrito por sessão; estes testes garantem que isso continua valendo.
 * Os INSERTs usam transação + rollBack para não deixar nada no banco de teste.
 */

// A prova direta: o modo da sessão contém STRICT_TRANS_TABLES.
testeIntegracao('Database: a conexão usa modo estrito (texto grande demais dá erro, não é cortado em silêncio)', function () {
    $modo = (string) bancoDeTeste()->query('SELECT @@SESSION.sql_mode')->fetchColumn();
    afirmarVerdadeiro(str_contains($modo, 'STRICT_TRANS_TABLES'), "sql_mode da sessão: $modo");
});

// O efeito prático: razao_social aceita 150 caracteres; 400 precisa dar erro em vez de ser cortado.
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

// O outro efeito do modo não estrito: regime_tributario inexistente viraria '' (vazio) sem avisar.
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

// O comando acrescenta STRICT_TRANS_TABLES ao modo existente (não o substitui); sem o TRIM sobraria uma vírgula.
testeIntegracao('Database: preserva os outros modos que o servidor já usava', function () {
    $modo = (string) bancoDeTeste()->query('SELECT @@SESSION.sql_mode')->fetchColumn();
    afirmarFalso(str_starts_with($modo, ','), 'sem vírgula sobrando no começo');
    afirmarFalso(str_contains($modo, ',,'));
});

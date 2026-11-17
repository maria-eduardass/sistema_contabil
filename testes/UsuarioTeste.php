<?php
declare(strict_types=1);

require_once __DIR__ . '/_ajuda_banco.php';

use App\Models\Usuario;

testeIntegracao('Usuario: autentica com e-mail e senha corretos e não devolve o hash', function () {
    $u = (new Usuario(bancoDeTeste()))->autenticar('contador@fiscalpulse.test', 'Contador@123');
    afirmarVerdadeiro($u !== null);
    afirmarIgual('contador', $u['perfil']);
    afirmarIgual(null, $u['empresa_id']);
    afirmarFalso(array_key_exists('senha_hash', $u));
});

testeIntegracao('Usuario: cliente vem com a empresa vinculada', function () {
    $u = (new Usuario(bancoDeTeste()))->autenticar('cliente1@fiscalpulse.test', 'Cliente@123');
    afirmarIgual('cliente', $u['perfil']);
    afirmarIgual(1, (int) $u['empresa_id']);
});

testeIntegracao('Usuario: senha errada e e-mail inexistente retornam null', function () {
    $m = new Usuario(bancoDeTeste());
    afirmarIgual(null, $m->autenticar('contador@fiscalpulse.test', 'errada'));
    afirmarIgual(null, $m->autenticar('ninguem@fiscalpulse.test', 'Contador@123'));
});

testeIntegracao('Usuario: usuário inativo não autentica', function () {
    $pdo = bancoDeTeste();
    $pdo->beginTransaction();
    $pdo->exec("UPDATE usuarios SET ativo = 0 WHERE email = 'contador@fiscalpulse.test'");
    $u = (new Usuario($pdo))->autenticar('contador@fiscalpulse.test', 'Contador@123');
    $pdo->rollBack();
    afirmarIgual(null, $u);
});

testeIntegracao('Usuario: payloads de SQL Injection são tratados como texto', function () {
    $m = new Usuario(bancoDeTeste());
    afirmarIgual(null, $m->autenticar("' OR '1'='1", "' OR '1'='1"));
    afirmarIgual(null, $m->autenticar("contador@fiscalpulse.test' --", 'x'));
    afirmarIgual(null, $m->autenticar("x'; DROP TABLE usuarios; --", 'x'));
    // a tabela continua existindo e populada
    afirmarVerdadeiro((int) bancoDeTeste()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() >= 3);
});

testeIntegracao('Usuario: o banco rejeita cliente sem empresa (constraint ck_usuarios_perfil)', function () {
    $pdo = bancoDeTeste();
    afirmarExcecao(PDOException::class, fn () => $pdo->exec(
        "INSERT INTO usuarios (empresa_id, nome, email, senha_hash, perfil) VALUES (NULL, 'X', 'x@x.test', 'h', 'cliente')"
    ));
});

// --- Cadastro de clientes (Tarefa 11) ---
testeIntegracao('Usuario: cria cliente vinculado à empresa e o login funciona em seguida', function () {
    comRollbackTesteUsuario(function ($pdo) {
        $m = new Usuario($pdo);
        $id = $m->criarCliente(2, 'Maria Silva', 'maria@cliente.test', 'Senha@123');
        afirmarVerdadeiro($id > 0);
        $logado = $m->autenticar('maria@cliente.test', 'Senha@123');
        afirmarIgual('cliente', $logado['perfil']);
        afirmarIgual(2, (int) $logado['empresa_id']);
        afirmarIgual(null, $m->autenticar('maria@cliente.test', 'outra-senha1'));
    });
});

testeIntegracao('Usuario: a senha é gravada como hash (nunca em texto puro)', function () {
    comRollbackTesteUsuario(function ($pdo) {
        $id = (new Usuario($pdo))->criarCliente(1, 'Maria', 'maria@cliente.test', 'Senha@123');
        $hash = (string) $pdo->query("SELECT senha_hash FROM usuarios WHERE id = $id")->fetchColumn();
        afirmarVerdadeiro($hash !== 'Senha@123');
        afirmarVerdadeiro(str_starts_with($hash, '$2y$'));
        afirmarVerdadeiro(password_verify('Senha@123', $hash));
    });
});

testeIntegracao('Usuario: emailExiste reconhece e-mails cadastrados sem diferenciar maiúsculas', function () {
    $m = new Usuario(bancoDeTeste());
    afirmarVerdadeiro($m->emailExiste('contador@fiscalpulse.test'));
    afirmarVerdadeiro($m->emailExiste('CONTADOR@fiscalpulse.test'));
    afirmarFalso($m->emailExiste('novo@cliente.test'));
    afirmarFalso($m->emailExiste("' OR '1'='1"));
});

testeIntegracao('Usuario: o banco rejeita e-mail duplicado (UNIQUE)', function () {
    comRollbackTesteUsuario(function ($pdo) {
        $m = new Usuario($pdo);
        afirmarExcecao(PDOException::class, fn () => $m->criarCliente(1, 'Clone', 'contador@fiscalpulse.test', 'Senha@123'));
    });
});

testeIntegracao('Usuario: o banco rejeita cliente de empresa inexistente (chave estrangeira)', function () {
    comRollbackTesteUsuario(function ($pdo) {
        $m = new Usuario($pdo);
        afirmarExcecao(PDOException::class, fn () => $m->criarCliente(99999, 'Fantasma', 'f@cliente.test', 'Senha@123'));
    });
});

testeIntegracao('Usuario: listarPorEmpresa traz só os usuários daquela empresa, sem o hash', function () {
    $m = new Usuario(bancoDeTeste());
    $lista = $m->listarPorEmpresa(1);
    afirmarIgual(['cliente1@fiscalpulse.test'], array_column($lista, 'email'));
    afirmarFalso(array_key_exists('senha_hash', $lista[0]));
    afirmarIgual([], $m->listarPorEmpresa(3));
});

function comRollbackTesteUsuario(callable $fn): void
{
    $pdo = bancoDeTeste();
    $pdo->beginTransaction();
    try {
        $fn($pdo);
    } finally {
        $pdo->rollBack();
    }
}

// --- CPF e telefone (opcionais, texto livre) ---
// Colunas novas de usuarios (migração 001). Os usuários do seed ficam com null; o 31º caractere é recusado pelo banco
// em modo estrito, que é a segunda barreira depois do ValidadorCadastro.
testeIntegracao('Usuario: CPF e telefone são colunas opcionais (os usuários do seed têm null)', function () {
    $m = new Usuario(bancoDeTeste());
    foreach ($m->listarPorEmpresa(1) as $u) {
        afirmarIgual(null, $u['cpf']);
        afirmarIgual(null, $u['telefone']);
    }
});

testeIntegracao('Usuario: criarCliente grava CPF e telefone como a pessoa digitou (texto livre)', function () {
    comRollbackTesteUsuario(function ($pdo) {
        $id = (new Usuario($pdo))->criarCliente(3, 'Maria', 'maria@cliente.test', 'x', 'qualquer coisa 123', '(00) abc');
        $linha = $pdo->query("SELECT cpf, telefone FROM usuarios WHERE id = $id")->fetch();
        afirmarIgual(['qualquer coisa 123', '(00) abc'], [$linha['cpf'], $linha['telefone']]);
    });
});

testeIntegracao('Usuario: criarCliente sem CPF/telefone continua funcionando (ficam null)', function () {
    comRollbackTesteUsuario(function ($pdo) {
        $id = (new Usuario($pdo))->criarCliente(3, 'Maria', 'maria@cliente.test', 'Senha@123');
        $linha = $pdo->query("SELECT cpf, telefone FROM usuarios WHERE id = $id")->fetch();
        afirmarIgual([null, null], [$linha['cpf'], $linha['telefone']]);
    });
});

testeIntegracao('Usuario: listarPorEmpresa devolve CPF e telefone (para o contador) e continua sem o hash', function () {
    comRollbackTesteUsuario(function ($pdo) {
        $m = new Usuario($pdo);
        $m->criarCliente(3, 'Bia', 'bia@cliente.test', 'x', '123', '999');
        $lista = $m->listarPorEmpresa(3);
        afirmarIgual(['123', '999'], [$lista[0]['cpf'], $lista[0]['telefone']]);
        afirmarFalso(array_key_exists('senha_hash', $lista[0]));
    });
});

testeIntegracao('Usuario: o banco recusa CPF ou telefone maiores que 30 caracteres (modo estrito)', function () {
    comRollbackTesteUsuario(function ($pdo) {
        $m = new Usuario($pdo);
        afirmarExcecao(PDOException::class, fn () => $m->criarCliente(3, 'A', 'a@cliente.test', 'x', str_repeat('1', 31), null));
        afirmarExcecao(PDOException::class, fn () => $m->criarCliente(3, 'B', 'b@cliente.test', 'x', null, str_repeat('2', 31)));
    });
});

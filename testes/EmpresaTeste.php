<?php
declare(strict_types=1);

require_once __DIR__ . '/_ajuda_banco.php';

use App\Models\Empresa;

function dadosEmpresaTeste(array $sobrescrever = []): array
{
    return $sobrescrever + [
        'razao_social' => 'Oficina do Zé ME',
        'cnpj' => '45723174000110',
        'regime_tributario' => 'mei',
        'email_contato' => 'ze@oficina.test',
    ];
}

/** Executa $fn dentro de uma transação que sempre é desfeita, para um teste não afetar o outro. */
function comRollbackTeste(callable $fn): void
{
    $pdo = bancoDeTeste();
    $pdo->beginTransaction();
    try {
        $fn($pdo);
    } finally {
        $pdo->rollBack();
    }
}

testeIntegracao('Empresa: lista as 3 empresas do seed ordenadas por razão social', function () {
    $nomes = array_column((new Empresa(bancoDeTeste()))->listar(), 'razao_social');
    afirmarIgual(['Clínica Bem Viver S/S', 'Padaria Pão Quente ME', 'Tech Soluções Ltda'], $nomes);
});

testeIntegracao('Empresa: busca por parte do nome (sem diferenciar maiúsculas) e por CNPJ com máscara', function () {
    $m = new Empresa(bancoDeTeste());
    afirmarIgual(['Padaria Pão Quente ME'], array_column($m->listar('padaria'), 'razao_social'));
    afirmarIgual(['Tech Soluções Ltda'], array_column($m->listar('11.444.777'), 'razao_social'));
});

testeIntegracao('Empresa: curingas % e _ digitados na busca são tratados como texto', function () {
    $m = new Empresa(bancoDeTeste());
    afirmarIgual([], $m->listar('%'));
    afirmarIgual([], $m->listar('_'));
});

testeIntegracao('Empresa: busca com SQL Injection não retorna dados extras nem apaga tabelas', function () {
    $m = new Empresa(bancoDeTeste());
    afirmarIgual([], $m->listar("' OR 'a'='a"));
    afirmarIgual([], $m->listar("x'; DROP TABLE empresas; --"));
    // Com dígitos, só vale a busca legítima por CNPJ ("11" aparece em 2 CNPJs), nunca "todas as linhas".
    afirmarIgual(['Padaria Pão Quente ME', 'Tech Soluções Ltda'], array_column($m->listar("' OR '1'='1"), 'razao_social'));
    afirmarIgual(3, count($m->listar()));
});

testeIntegracao('Empresa: cria e busca por id', function () {
    comRollbackTeste(function ($pdo) {
        $m = new Empresa($pdo);
        $id = $m->criar(dadosEmpresaTeste());
        $e = $m->buscar($id);
        afirmarIgual('Oficina do Zé ME', $e['razao_social']);
        afirmarIgual('45723174000110', $e['cnpj']);
        afirmarIgual('1', (string) $e['ativa']);
    });
});

testeIntegracao('Empresa: buscar id inexistente retorna null', function () {
    afirmarIgual(null, (new Empresa(bancoDeTeste()))->buscar(999999));
});

testeIntegracao('Empresa: cnpjExiste, com opção de ignorar a própria empresa na edição', function () {
    $m = new Empresa(bancoDeTeste());
    afirmarVerdadeiro($m->cnpjExiste('11222333000181'));
    afirmarFalso($m->cnpjExiste('45723174000110'));
    afirmarFalso($m->cnpjExiste('11222333000181', 1)); // empresa 1 é a dona desse CNPJ
    afirmarVerdadeiro($m->cnpjExiste('11222333000181', 2));
});

testeIntegracao('Empresa: o banco rejeita CNPJ duplicado (UNIQUE) e CNPJ fora do padrão (CHECK)', function () {
    comRollbackTeste(function ($pdo) {
        $m = new Empresa($pdo);
        afirmarExcecao(PDOException::class, fn () => $m->criar(dadosEmpresaTeste(['cnpj' => '11222333000181'])));
    });
    comRollbackTeste(function ($pdo) {
        $m = new Empresa($pdo);
        afirmarExcecao(PDOException::class, fn () => $m->criar(dadosEmpresaTeste(['cnpj' => 'abc'])));
    });
});

testeIntegracao('Empresa: atualiza os dados cadastrais', function () {
    comRollbackTeste(function ($pdo) {
        $m = new Empresa($pdo);
        $m->atualizar(1, dadosEmpresaTeste(['razao_social' => 'Padaria Renomeada', 'cnpj' => '11222333000181']));
        afirmarIgual('Padaria Renomeada', $m->buscar(1)['razao_social']);
        afirmarIgual('mei', $m->buscar(1)['regime_tributario']);
    });
});

testeIntegracao('Empresa: inativar não apaga a empresa nem as obrigações dela', function () {
    comRollbackTeste(function ($pdo) {
        $m = new Empresa($pdo);
        $antes = (int) $pdo->query('SELECT COUNT(*) FROM obrigacoes WHERE empresa_id = 1')->fetchColumn();
        $m->inativar(1);
        afirmarIgual('0', (string) $m->buscar(1)['ativa']);
        afirmarIgual($antes, (int) $pdo->query('SELECT COUNT(*) FROM obrigacoes WHERE empresa_id = 1')->fetchColumn());
        afirmarVerdadeiro($antes > 0);
    });
});

testeIntegracao('Empresa: listarAtivas traz só as ativas, por razão social', function () {
    comRollbackTeste(function ($pdo) {
        $m = new Empresa($pdo);
        afirmarIgual(3, count($m->listarAtivas()));
        $m->inativar(1);
        afirmarIgual(['Clínica Bem Viver S/S', 'Tech Soluções Ltda'], array_column($m->listarAtivas(), 'razao_social'));
    });
});

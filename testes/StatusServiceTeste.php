<?php
declare(strict_types=1);

require_once __DIR__ . '/_ajuda_banco.php';

use App\Models\Obrigacao;
use App\Services\RegraDeNegocio;
use App\Services\StatusService;

// Ids do seed (ver database.sql): 1 e 2 = Padaria atrasadas; 3 = Padaria pendente (+3d);
// 4 = Tech pendente (+2d); 5 = Tech paga; 6 = Clínica pendente (+25d); 7 = Clínica paga.

function comRollbackStatusTeste(callable $fn): void
{
    $pdo = bancoDeTeste();
    $pdo->beginTransaction();
    try {
        $fn($pdo, new StatusService(new Obrigacao($pdo)), new DateTimeImmutable('today'));
    } finally {
        $pdo->rollBack();
    }
}

function statusTeste(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('SELECT status, data_pagamento FROM obrigacoes WHERE id = :id');
    $stmt->execute(['id' => $id]);
    return $stmt->fetch();
}

function definirVencimentoTeste(PDO $pdo, int $id, string $data): void
{
    $pdo->prepare('UPDATE obrigacoes SET data_vencimento = :d WHERE id = :id')->execute(['d' => $data, 'id' => $id]);
}

/** Confere que $fn lança RegraDeNegocio com o código HTTP esperado. */
function afirmarRegra(int $codigoEsperado, callable $fn): void
{
    try {
        $fn();
    } catch (RegraDeNegocio $e) {
        afirmarIgual($codigoEsperado, $e->codigoHttp);
        return;
    }
    throw new AssertionError("Esperava RegraDeNegocio $codigoEsperado, nada foi lançado");
}

// --- Sincronização automática de atrasos ---
testeIntegracao('Status: pendente vencida vira atrasada; a conta devolve quantas mudaram', function () {
    comRollbackStatusTeste(function ($pdo, $svc, $hoje) {
        definirVencimentoTeste($pdo, 3, $hoje->modify('-3 days')->format('Y-m-d'));
        afirmarIgual(1, $svc->sincronizar($hoje));
        afirmarIgual('atrasado', statusTeste($pdo, 3)['status']);
    });
});

testeIntegracao('Status: sincronizar é idempotente (rodar de novo não muda nada)', function () {
    comRollbackStatusTeste(function ($pdo, $svc, $hoje) {
        definirVencimentoTeste($pdo, 3, $hoje->modify('-3 days')->format('Y-m-d'));
        $svc->sincronizar($hoje);
        afirmarIgual(0, $svc->sincronizar($hoje));
        afirmarIgual('atrasado', statusTeste($pdo, 3)['status']);
    });
});

testeIntegracao('Status: vence hoje continua pendente (atraso começa no dia seguinte)', function () {
    comRollbackStatusTeste(function ($pdo, $svc, $hoje) {
        definirVencimentoTeste($pdo, 3, $hoje->format('Y-m-d'));
        afirmarIgual(0, $svc->sincronizar($hoje));
        afirmarIgual('pendente', statusTeste($pdo, 3)['status']);
    });
});

testeIntegracao('Status: obrigação paga nunca vira atrasada, mesmo com vencimento passado', function () {
    comRollbackStatusTeste(function ($pdo, $svc, $hoje) {
        definirVencimentoTeste($pdo, 5, $hoje->modify('-60 days')->format('Y-m-d'));
        $svc->sincronizar($hoje);
        afirmarIgual('pago', statusTeste($pdo, 5)['status']);
    });
});

testeIntegracao('Status: obrigações no prazo não são tocadas', function () {
    comRollbackStatusTeste(function ($pdo, $svc, $hoje) {
        afirmarIgual(0, $svc->sincronizar($hoje)); // seed: nada pendente está vencido
        afirmarIgual('pendente', statusTeste($pdo, 4)['status']);
        afirmarIgual('pendente', statusTeste($pdo, 6)['status']);
    });
});

// --- Pagamento ---
testeIntegracao('Pagar: atrasada vira paga e guarda a data do pagamento', function () {
    comRollbackStatusTeste(function ($pdo, $svc, $hoje) {
        $data = $hoje->modify('-1 day')->format('Y-m-d');
        $svc->pagar(1, $data, $hoje);
        $s = statusTeste($pdo, 1);
        afirmarIgual('pago', $s['status']);
        afirmarIgual($data, $s['data_pagamento']);
    });
});

testeIntegracao('Pagar: pendente também pode ser paga, inclusive com a data de hoje', function () {
    comRollbackStatusTeste(function ($pdo, $svc, $hoje) {
        $svc->pagar(4, $hoje->format('Y-m-d'), $hoje);
        afirmarIgual('pago', statusTeste($pdo, 4)['status']);
    });
});

testeIntegracao('Pagar: segunda tentativa dá 409 e NÃO sobrescreve a data original', function () {
    comRollbackStatusTeste(function ($pdo, $svc, $hoje) {
        $primeira = $hoje->modify('-2 days')->format('Y-m-d');
        $svc->pagar(1, $primeira, $hoje);
        afirmarRegra(409, fn () => $svc->pagar(1, $hoje->format('Y-m-d'), $hoje));
        afirmarIgual($primeira, statusTeste($pdo, 1)['data_pagamento']);
    });
});

testeIntegracao('Pagar: data futura é rejeitada (422) e nada é alterado', function () {
    comRollbackStatusTeste(function ($pdo, $svc, $hoje) {
        afirmarRegra(422, fn () => $svc->pagar(1, $hoje->modify('+1 day')->format('Y-m-d'), $hoje));
        afirmarIgual('atrasado', statusTeste($pdo, 1)['status']);
        afirmarIgual(null, statusTeste($pdo, 1)['data_pagamento']);
    });
});

testeIntegracao('Pagar: data inválida ou com SQL Injection é rejeitada (422)', function () {
    comRollbackStatusTeste(function ($pdo, $svc, $hoje) {
        foreach (['', 'ontem', '2026-02-31', '17/11/2026', "2026-01-01'; DROP TABLE obrigacoes; --"] as $ruim) {
            afirmarRegra(422, fn () => $svc->pagar(1, $ruim, $hoje));
        }
        afirmarVerdadeiro((int) $pdo->query('SELECT COUNT(*) FROM obrigacoes')->fetchColumn() === 7);
    });
});

testeIntegracao('Pagar: obrigação inexistente dá 404', function () {
    comRollbackStatusTeste(function ($pdo, $svc, $hoje) {
        afirmarRegra(404, fn () => $svc->pagar(99999, $hoje->format('Y-m-d'), $hoje));
    });
});

// --- Exclusão ---
testeIntegracao('Excluir: remove obrigação em aberto', function () {
    comRollbackStatusTeste(function ($pdo, $svc) {
        $svc->excluir(6);
        afirmarIgual(0, (int) $pdo->query('SELECT COUNT(*) FROM obrigacoes WHERE id = 6')->fetchColumn());
    });
});

testeIntegracao('Excluir: obrigação paga é protegida (409) porque é histórico', function () {
    comRollbackStatusTeste(function ($pdo, $svc) {
        afirmarRegra(409, fn () => $svc->excluir(5));
        afirmarIgual(1, (int) $pdo->query('SELECT COUNT(*) FROM obrigacoes WHERE id = 5')->fetchColumn());
    });
});

testeIntegracao('Excluir: obrigação inexistente dá 404', function () {
    comRollbackStatusTeste(function ($pdo, $svc) {
        afirmarRegra(404, fn () => $svc->excluir(99999));
    });
});

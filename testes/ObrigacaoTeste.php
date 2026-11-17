<?php
declare(strict_types=1);

require_once __DIR__ . '/_ajuda_banco.php';

use App\Models\Obrigacao;
use App\Models\TipoObrigacao;

function dadosObrigacaoTeste(array $sobrescrever = []): array
{
    return $sobrescrever + [
        'empresa_id' => 3,
        'tipo_obrigacao_id' => 2,           // DCTF (a empresa 3 ainda não tem)
        'competencia' => '2026-10-01',
        'data_vencimento' => '2026-11-20',
        'valor_centavos' => 123456,
        'observacao' => 'Teste',
        'status' => 'pendente',
    ];
}

/** Acha, entre as obrigações da empresa 3, a de determinado tipo (a Clínica já tem outras no seed). */
function obrigacaoCriadaTeste(Obrigacao $m, string $sigla): array
{
    foreach ($m->listarPorEmpresa(3) as $o) {
        if ($o['tipo_sigla'] === $sigla) {
            return $o;
        }
    }
    throw new AssertionError("Obrigação $sigla não encontrada");
}

function comRollbackObrigacaoTeste(callable $fn): void
{
    $pdo = bancoDeTeste();
    $pdo->beginTransaction();
    try {
        $fn($pdo);
    } finally {
        $pdo->rollBack();
    }
}

testeIntegracao('TipoObrigacao: lista os 5 tipos do seed ordenados por sigla', function () {
    $siglas = array_column((new TipoObrigacao(bancoDeTeste()))->listar(), 'sigla');
    afirmarIgual(['DAS', 'DCTF', 'DEFIS', 'EFD-CONTRIB', 'FGTS'], $siglas);
});

testeIntegracao('Obrigacao: valor 1.234,56 é gravado exatamente (sem erro de ponto flutuante)', function () {
    comRollbackObrigacaoTeste(function ($pdo) {
        $m = new Obrigacao($pdo);
        $id = $m->criar(dadosObrigacaoTeste());
        afirmarVerdadeiro($id > 0);
        afirmarIgual('1234.56', (string) $pdo->query("SELECT valor FROM obrigacoes WHERE id = $id")->fetchColumn());
        $criada = obrigacaoCriadaTeste($m, 'DCTF');
        afirmarIgual(123456, (int) $criada['valor_centavos']);
        afirmarIgual('pendente', $criada['status']);
        afirmarIgual('Teste', $criada['observacao']);
    });
});

testeIntegracao('Obrigacao: grava centavos pequenos e o valor máximo', function () {
    comRollbackObrigacaoTeste(function ($pdo) {
        $m = new Obrigacao($pdo);
        $m->criar(dadosObrigacaoTeste(['tipo_obrigacao_id' => 2, 'valor_centavos' => 5]));
        $m->criar(dadosObrigacaoTeste(['tipo_obrigacao_id' => 3, 'valor_centavos' => 9999999999]));
        afirmarIgual(5, (int) obrigacaoCriadaTeste($m, 'DCTF')['valor_centavos']);
        afirmarIgual(9999999999, (int) obrigacaoCriadaTeste($m, 'EFD-CONTRIB')['valor_centavos']);
    });
});

testeIntegracao('Obrigacao: observação vazia (null) é aceita', function () {
    comRollbackObrigacaoTeste(function ($pdo) {
        $m = new Obrigacao($pdo);
        $m->criar(dadosObrigacaoTeste(['observacao' => null]));
        afirmarIgual(null, obrigacaoCriadaTeste($m, 'DCTF')['observacao']);
    });
});

testeIntegracao('Obrigacao: existe() detecta empresa + tipo + competência já cadastrados', function () {
    $m = new Obrigacao(bancoDeTeste());
    $competencia = date('Y-m-01');
    afirmarVerdadeiro($m->existe(3, 4, $competencia));   // seed: Clínica / FGTS / mês atual
    afirmarFalso($m->existe(3, 2, $competencia));
    afirmarFalso($m->existe(2, 4, $competencia));
});

testeIntegracao('Obrigacao: o banco rejeita duplicidade (UNIQUE) com o código 1062', function () {
    comRollbackObrigacaoTeste(function ($pdo) {
        $m = new Obrigacao($pdo);
        $m->criar(dadosObrigacaoTeste());
        try {
            $m->criar(dadosObrigacaoTeste());
            throw new AssertionError('Deveria rejeitar duplicidade');
        } catch (PDOException $e) {
            afirmarIgual(1062, $e->errorInfo[1]);
        }
    });
});

testeIntegracao('Obrigacao: o banco rejeita tipo ou empresa inexistentes (chave estrangeira)', function () {
    comRollbackObrigacaoTeste(function ($pdo) {
        $m = new Obrigacao($pdo);
        afirmarExcecao(PDOException::class, fn () => $m->criar(dadosObrigacaoTeste(['tipo_obrigacao_id' => 999])));
        afirmarExcecao(PDOException::class, fn () => $m->criar(dadosObrigacaoTeste(['empresa_id' => 999])));
    });
});

testeIntegracao('Obrigacao: o banco rejeita valor negativo e "pago" sem data de pagamento (CHECK)', function () {
    comRollbackObrigacaoTeste(function ($pdo) {
        afirmarExcecao(PDOException::class, fn () => $pdo->exec(
            "UPDATE obrigacoes SET valor = -1 WHERE id = 1"
        ));
        afirmarExcecao(PDOException::class, fn () => $pdo->exec(
            "UPDATE obrigacoes SET status = 'pago', data_pagamento = NULL WHERE id = 1"
        ));
    });
});

testeIntegracao('Obrigacao: listarPorEmpresa traz só as da empresa, em aberto primeiro por vencimento', function () {
    $m = new Obrigacao(bancoDeTeste());
    $padaria = $m->listarPorEmpresa(1);
    afirmarIgual(3, count($padaria));
    afirmarIgual(['DAS', 'FGTS', 'DCTF'], array_column($padaria, 'tipo_sigla')); // vence: -20d, -5d, +3d
    afirmarIgual(['atrasado', 'atrasado', 'pendente'], array_column($padaria, 'status'));

    $tech = $m->listarPorEmpresa(2); // 1 pendente + 1 paga: a paga vai para o fim
    afirmarIgual(['pendente', 'pago'], array_column($tech, 'status'));
    afirmarIgual([], $m->listarPorEmpresa(999));
});

testeIntegracao('Obrigacao: parâmetros de busca não permitem SQL Injection', function () {
    $m = new Obrigacao(bancoDeTeste());
    afirmarIgual(0, $m->existe(1, 1, "2026-01-01' OR '1'='1") ? 1 : 0);
    afirmarIgual(3, count($m->listarPorEmpresa(1)));
});

// --- Dados para o painel do contador (Tarefa 15) ---
testeIntegracao('Obrigacao: listarEmAbertoDeEmpresasAtivas agrupa por empresa e ignora pagas', function () {
    $porEmpresa = (new Obrigacao(bancoDeTeste()))->listarEmAbertoDeEmpresasAtivas();
    afirmarIgual([1, 2, 3], array_keys($porEmpresa));
    afirmarIgual(3, count($porEmpresa[1]));
    afirmarIgual(1, count($porEmpresa[2]));   // a paga da Tech não entra
    afirmarIgual(1, count($porEmpresa[3]));   // a paga da Clínica não entra
    foreach ($porEmpresa as $linhas) {
        foreach ($linhas as $l) {
            afirmarVerdadeiro($l['status'] !== 'pago');
        }
    }
});

testeIntegracao('Obrigacao: listarEmAbertoDeEmpresasAtivas traz os campos que o termômetro precisa, em ordem de vencimento', function () {
    $padaria = (new Obrigacao(bancoDeTeste()))->listarEmAbertoDeEmpresasAtivas()[1];
    afirmarIgual(['DAS', 'FGTS', 'DCTF'], array_column($padaria, 'tipo_sigla'));
    afirmarIgual(185000, (int) $padaria[0]['valor_centavos']);
    foreach (['id', 'tipo_sigla', 'data_vencimento', 'valor_centavos', 'status'] as $campo) {
        afirmarVerdadeiro(array_key_exists($campo, $padaria[0]), $campo);
    }
});

testeIntegracao('Obrigacao: listarEmAbertoDeEmpresasAtivas ignora empresas inativas', function () {
    comRollbackObrigacaoTeste(function ($pdo) {
        $pdo->exec('UPDATE empresas SET ativa = 0 WHERE id = 3');
        afirmarIgual([1, 2], array_keys((new Obrigacao($pdo))->listarEmAbertoDeEmpresasAtivas()));
    });
});

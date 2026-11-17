<?php
declare(strict_types=1);

use App\Services\SimulacaoTermometro;

/*
 * "E se eu cadastrar esta obrigação?": calcula o termômetro ANTES e DEPOIS de uma guia hipotética, com as mesmas
 * regras do termômetro real (TermometroEmpresa), para a tela de cadastro mostrar o efeito antes de salvar.
 * "Hoje" fixo: 2026-11-17. As linhas têm o formato de Obrigacao::listarPorEmpresa.
 */

function linhaSimTeste(int $id, string $status, string $vencimento, string $valorCentavos, string $sigla = 'DAS'): array
{
    return ['id' => $id, 'tipo_sigla' => $sigla, 'data_vencimento' => $vencimento, 'valor_centavos' => $valorCentavos, 'status' => $status];
}

function simularTeste(array $linhas, string $vencimento, string $valor = '', string $hoje = '2026-11-17'): array
{
    return SimulacaoTermometro::simular($linhas, $vencimento, $valor, new DateTimeImmutable($hoje));
}

/** Empresa tranquila: uma guia a vencer em 2 dias (risco 26). */
function empresaTranquilaSimTeste(): array
{
    return [linhaSimTeste(1, 'pendente', '2026-11-19', '320000', 'EFD-CONTRIB')];
}

teste('Simulação: guia ATRASADA numa empresa tranquila leva o termômetro ao crítico', function () {
    $r = simularTeste(empresaTranquilaSimTeste(), '2026-11-07', '1.000,00'); // 10 dias de atraso: risco 80
    afirmarIgual([], $r['erros']);
    afirmarIgual(26, $r['antes']['score']);
    afirmarIgual('baixo', $r['antes']['faixa']);
    afirmarIgual(87, $r['depois']['score']); // 80 + 25% de 26 = 86,5 -> 87
    afirmarIgual('critico', $r['depois']['faixa']);
});

teste('Simulação: informa o risco, os dias de atraso e a multa da própria guia nova', function () {
    $r = simularTeste(empresaTranquilaSimTeste(), '2026-11-07', '1.000,00');
    afirmarIgual(['status' => 'atrasado', 'dias_atraso' => 10, 'risco' => 80, 'multa_centavos' => 3633], $r['nova']);
    afirmarIgual(3633, $r['depois']['multa_total_centavos']);
    afirmarIgual(0, $r['antes']['multa_total_centavos']);
});

teste('Simulação: guia que vence hoje sobe o termômetro para a faixa de atenção', function () {
    $r = simularTeste([], '2026-11-17', ''); // sem guias antes: 0%; vence hoje: risco 30
    afirmarIgual(0, $r['antes']['score']);
    afirmarIgual(30, $r['depois']['score']);
    afirmarIgual('atencao', $r['depois']['faixa']);
    afirmarIgual('pendente', $r['nova']['status']);
});

teste('Simulação: guia distante (mais de 15 dias) não muda o termômetro', function () {
    $r = simularTeste(empresaTranquilaSimTeste(), '2026-12-05', '500,00');
    afirmarIgual($r['antes']['score'], $r['depois']['score']);
    afirmarIgual(0, $r['nova']['risco']);
});

teste('Simulação: a guia nova é somada às existentes (a pior define a base, as demais agravam)', function () {
    $linhas = [linhaSimTeste(1, 'atrasado', '2026-11-12', '62050', 'FGTS')]; // 5 dias: risco 60
    $r = simularTeste($linhas, '2026-11-16', '100,00'); // 1 dia: risco 44
    afirmarIgual(60, $r['antes']['score']);
    afirmarIgual(71, $r['depois']['score']); // 60 + 25% de 44 = 71
});

teste('Simulação: guias já pagas não pesam, nem no antes nem no depois', function () {
    $linhas = [linhaSimTeste(1, 'pago', '2026-10-01', '900000')];
    $r = simularTeste($linhas, '2026-11-20', '10,00');
    afirmarIgual(0, $r['antes']['score']);
    afirmarIgual(24, $r['depois']['score']); // só a nova: vence em 3 dias, risco 24
});

teste('Simulação: valor vazio vale zero e não gera multa (declaração sem imposto)', function () {
    $r = simularTeste([], '2026-11-07', '');
    afirmarIgual([], $r['erros']);
    afirmarIgual(0, $r['nova']['multa_centavos']);
    afirmarIgual(80, $r['depois']['score']); // o risco existe mesmo sem valor
});

teste('Simulação: vencimento ainda não preenchido ou inválido devolve erro e NÃO calcula o depois', function () {
    foreach (['', 'ontem', '2026-02-31', '17/11/2026'] as $ruim) {
        $r = simularTeste(empresaTranquilaSimTeste(), $ruim, '10,00');
        afirmarVerdadeiro(isset($r['erros']['vencimento']), "'$ruim'");
        afirmarIgual(null, $r['depois']);
        afirmarIgual(26, $r['antes']['score']); // o "antes" sempre aparece
    }
});

teste('Simulação: valor inválido devolve erro e NÃO calcula o depois', function () {
    foreach (['abc', '-5', '1234.56', '100.000.000,00'] as $ruim) {
        $r = simularTeste(empresaTranquilaSimTeste(), '2026-11-07', $ruim);
        afirmarVerdadeiro(isset($r['erros']['valor']), "'$ruim'");
        afirmarIgual(null, $r['depois']);
    }
});

teste('Simulação: é pura — chamar duas vezes dá o mesmo resultado e não altera as linhas recebidas', function () {
    $linhas = empresaTranquilaSimTeste();
    $copia = $linhas;
    $a = simularTeste($linhas, '2026-11-07', '1.000,00');
    $b = simularTeste($linhas, '2026-11-07', '1.000,00');
    afirmarIgual($a, $b);
    afirmarIgual($copia, $linhas);
});

teste('Simulação: guia de status "pendente" já vencida é tratada como atrasada (a data manda)', function () {
    afirmarIgual('atrasado', simularTeste([], '2026-11-01', '10,00')['nova']['status']);
});

teste('Simulação: o rótulo da faixa acompanha o score (para o selo da tela)', function () {
    $r = simularTeste(empresaTranquilaSimTeste(), '2026-11-07', '1.000,00');
    afirmarIgual('Risco baixo', $r['antes']['faixa_rotulo']);
    afirmarIgual('Risco crítico', $r['depois']['faixa_rotulo']);
});

// --- Mensagem de impacto (mostrada depois de salvar) ---
teste('Impacto: texto quando o termômetro muda de faixa', function () {
    $antes = ['score' => 26, 'faixa_rotulo' => 'Risco baixo'];
    $depois = ['score' => 87, 'faixa_rotulo' => 'Risco crítico'];
    afirmarIgual('O termômetro foi de 26% (Risco baixo) para 87% (Risco crítico).', SimulacaoTermometro::resumoImpacto($antes, $depois));
});

teste('Impacto: texto quando o termômetro não muda', function () {
    $m = ['score' => 0, 'faixa_rotulo' => 'Risco baixo'];
    afirmarIgual('O termômetro continua em 0% (Risco baixo).', SimulacaoTermometro::resumoImpacto($m, $m));
});

teste('Impacto: texto quando o termômetro diminui (ex.: depois de pagar)', function () {
    $antes = ['score' => 100, 'faixa_rotulo' => 'Risco crítico'];
    $depois = ['score' => 24, 'faixa_rotulo' => 'Risco baixo'];
    afirmarIgual('O termômetro foi de 100% (Risco crítico) para 24% (Risco baixo).', SimulacaoTermometro::resumoImpacto($antes, $depois));
});

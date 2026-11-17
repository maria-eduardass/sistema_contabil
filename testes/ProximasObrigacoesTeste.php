<?php
declare(strict_types=1);

use App\Services\ProximasObrigacoes;
use App\Services\TermometroRisco;

function linhaProximaTeste(int $id, string $vencimento, string $status): array
{
    return ['id' => $id, 'tipo_sigla' => 'DAS', 'data_vencimento' => $vencimento, 'valor_centavos' => 1000, 'status' => $status];
}

function proximasTeste(array $linhas, int $limite = 5): array
{
    return ProximasObrigacoes::de($linhas, new DateTimeImmutable('2026-11-17'), $limite);
}

teste('Próximas: ignora as pagas e ordena por vencimento (atrasadas primeiro)', function () {
    $r = proximasTeste([
        linhaProximaTeste(1, '2026-11-25', 'pendente'),
        linhaProximaTeste(2, '2026-11-01', 'pago'),
        linhaProximaTeste(3, '2026-11-12', 'atrasado'),
        linhaProximaTeste(4, '2026-11-20', 'pendente'),
    ]);
    afirmarIgual([3, 4, 1], array_column($r, 'id'));
});

teste('Próximas: informa os dias para vencer (negativo = atrasada, 0 = vence hoje)', function () {
    $r = proximasTeste([
        linhaProximaTeste(1, '2026-11-12', 'atrasado'),
        linhaProximaTeste(2, '2026-11-17', 'pendente'),
        linhaProximaTeste(3, '2026-11-20', 'pendente'),
    ]);
    afirmarIgual([-5, 0, 3], array_column($r, 'dias_para_vencer'));
});

teste('Próximas: respeita o limite pedido', function () {
    $linhas = [];
    for ($i = 1; $i <= 8; $i++) {
        $linhas[] = linhaProximaTeste($i, sprintf('2026-12-%02d', $i), 'pendente');
    }
    afirmarIgual(5, count(proximasTeste($linhas)));
    afirmarIgual(2, count(proximasTeste($linhas, 2)));
    afirmarIgual([1, 2], array_column(proximasTeste($linhas, 2), 'id'));
});

teste('Próximas: sem obrigações em aberto devolve lista vazia', function () {
    afirmarIgual([], proximasTeste([]));
    afirmarIgual([], proximasTeste([linhaProximaTeste(1, '2026-11-01', 'pago')]));
});

teste('Próximas: mantém os demais campos da linha para a tela', function () {
    $r = proximasTeste([linhaProximaTeste(7, '2026-11-20', 'pendente')]);
    afirmarIgual('DAS', $r[0]['tipo_sigla']);
    afirmarIgual(1000, $r[0]['valor_centavos']);
    afirmarIgual('pendente', $r[0]['status']);
});

teste('TermometroRisco::diasParaVencer calcula a diferença em dias de calendário', function () {
    $hoje = new DateTimeImmutable('2026-11-17 18:30:00');
    afirmarIgual(3, TermometroRisco::diasParaVencer('2026-11-20', $hoje));
    afirmarIgual(0, TermometroRisco::diasParaVencer('2026-11-17', $hoje));
    afirmarIgual(-5, TermometroRisco::diasParaVencer('2026-11-12', $hoje));
    afirmarExcecao(InvalidArgumentException::class, fn () => TermometroRisco::diasParaVencer('2026-02-31', $hoje));
});

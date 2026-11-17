<?php
declare(strict_types=1);

use App\Core\Json;
use App\Services\TermometroEmpresa;
use App\Services\TermometroRisco;

// "Hoje" fixo: 2026-11-17. Linhas no mesmo formato de Obrigacao::listarPorEmpresa (valores vêm como texto do banco).
function linhaTermometroTeste(int $id, string $sigla, string $vencimento, string $valorCentavos, string $status): array
{
    return ['id' => $id, 'tipo_sigla' => $sigla, 'data_vencimento' => $vencimento, 'valor_centavos' => $valorCentavos, 'status' => $status];
}

function montarTeste(array $linhas): array
{
    return TermometroEmpresa::montar($linhas, new DateTimeImmutable('2026-11-17'));
}

function padariaTeste(): array
{
    return [
        linhaTermometroTeste(1, 'DAS', '2026-10-28', '185000', 'atrasado'),   // 20 dias de atraso
        linhaTermometroTeste(2, 'FGTS', '2026-11-12', '62050', 'atrasado'),   // 5 dias de atraso
        linhaTermometroTeste(3, 'DCTF', '2026-11-20', '0', 'pendente'),       // vence em 3 dias
    ];
}

teste('TermometroEmpresa: caso Padaria do seed = score 100, crítico, multa total R$ 145,70', function () {
    $r = montarTeste(padariaTeste());
    afirmarIgual(100, $r['score']);
    afirmarIgual('critico', $r['faixa']);
    afirmarIgual('Risco crítico', $r['faixa_rotulo']);
    afirmarIgual(14570, $r['multa_total_centavos']);
});

teste('TermometroEmpresa: cada item traz dias de atraso, risco e multa estimada (multa + juros)', function () {
    $itens = montarTeste(padariaTeste())['itens'];
    afirmarIgual(3, count($itens));
    afirmarIgual(
        ['id' => 1, 'tipo_sigla' => 'DAS', 'data_vencimento' => '2026-10-28', 'valor_centavos' => 185000, 'dias_atraso' => 20, 'risco' => 100, 'multa_centavos' => 13443],
        $itens[0]
    );
    afirmarIgual(['dias_atraso' => 5, 'risco' => 60, 'multa_centavos' => 1127], array_intersect_key($itens[1], array_flip(['dias_atraso', 'risco', 'multa_centavos'])));
    afirmarIgual(['dias_atraso' => 0, 'risco' => 24, 'multa_centavos' => 0], array_intersect_key($itens[2], array_flip(['dias_atraso', 'risco', 'multa_centavos'])));
});

teste('TermometroEmpresa: o total de multa é a soma das multas dos itens', function () {
    $r = montarTeste(padariaTeste());
    afirmarIgual(array_sum(array_column($r['itens'], 'multa_centavos')), $r['multa_total_centavos']);
});

teste('TermometroEmpresa: empresa só com obrigação a vencer não tem multa (caso Tech)', function () {
    $r = montarTeste([
        linhaTermometroTeste(4, 'EFD-CONTRIB', '2026-11-19', '320000', 'pendente'),
        linhaTermometroTeste(5, 'DCTF', '2026-11-07', '0', 'pago'),
    ]);
    afirmarIgual(26, $r['score']);
    afirmarIgual('baixo', $r['faixa']);
    afirmarIgual(0, $r['multa_total_centavos']);
    afirmarIgual(1, count($r['itens'])); // a paga não aparece
});

teste('TermometroEmpresa: tudo pago ou muito distante dá score 0 (caso Clínica)', function () {
    $r = montarTeste([
        linhaTermometroTeste(6, 'FGTS', '2026-12-12', '98000', 'pendente'),
        linhaTermometroTeste(7, 'DAS', '2026-11-09', '150000', 'pago'),
    ]);
    afirmarIgual(0, $r['score']);
    afirmarIgual('Risco baixo', $r['faixa_rotulo']);
});

teste('TermometroEmpresa: sem obrigações o resultado é zerado', function () {
    $r = montarTeste([]);
    afirmarIgual(0, $r['score']);
    afirmarIgual('baixo', $r['faixa']);
    afirmarIgual(0, $r['multa_total_centavos']);
    afirmarIgual([], $r['itens']);
});

teste('TermometroEmpresa: obrigação paga em atraso não gera multa nem risco', function () {
    $r = montarTeste([linhaTermometroTeste(1, 'DAS', '2026-01-10', '500000', 'pago')]);
    afirmarIgual(0, $r['score']);
    afirmarIgual(0, $r['multa_total_centavos']);
});

teste('TermometroEmpresa: atrasada de valor zero pesa no risco mas não gera multa', function () {
    $r = montarTeste([linhaTermometroTeste(1, 'DCTF', '2026-11-12', '0', 'atrasado')]);
    afirmarIgual(60, $r['score']);
    afirmarIgual(0, $r['multa_total_centavos']);
});

teste('TermometroEmpresa: status "pendente" já vencida é tratada como atrasada (a data manda)', function () {
    $r = montarTeste([linhaTermometroTeste(1, 'DAS', '2026-11-07', '100000', 'pendente')]); // 10 dias
    afirmarIgual(80, $r['score']);
    afirmarIgual(3633, $r['multa_total_centavos']);
});

teste('TermometroEmpresa: expõe os limites das faixas para o velocímetro desenhar as cores', function () {
    afirmarIgual(['atencao' => 30, 'alto' => 60, 'critico' => 80], montarTeste([])['limites']);
    afirmarIgual(TermometroRisco::LIMITES_FAIXAS, montarTeste([])['limites']);
});

// --- Codificação JSON segura ---
teste('Json: escapa < > & aspas e apóstrofo (nenhum HTML cru mesmo se o JSON for inserido numa página)', function () {
    $texto = Json::codificar(['x' => '<script>alert("a")</script> & \'b\'']);
    foreach (['<', '>', '&', "'"] as $perigoso) {
        afirmarFalso(str_contains($texto, $perigoso), "contém $perigoso");
    }
});

teste('Json: ida e volta preserva os dados e mantém acentos legíveis', function () {
    $dados = ['rotulo' => 'Risco crítico', 'score' => 100, 'itens' => [['a' => 1]]];
    $texto = Json::codificar($dados);
    afirmarIgual($dados, json_decode($texto, true));
    afirmarVerdadeiro(str_contains($texto, 'crítico'));
});

teste('TermometroRisco: constante de limites bate com as faixas do score', function () {
    afirmarIgual(['atencao' => 30, 'alto' => 60, 'critico' => 80], TermometroRisco::LIMITES_FAIXAS);
    afirmarIgual('atencao', TermometroRisco::faixaDoScore(TermometroRisco::LIMITES_FAIXAS['atencao']));
    afirmarIgual('baixo', TermometroRisco::faixaDoScore(TermometroRisco::LIMITES_FAIXAS['atencao'] - 1));
    afirmarIgual('critico', TermometroRisco::faixaDoScore(TermometroRisco::LIMITES_FAIXAS['critico']));
});

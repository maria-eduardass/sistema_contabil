<?php
declare(strict_types=1);

use App\Services\MensagemCobranca;

function guiaCobrancaTeste(array $sobrescrever = []): array
{
    return $sobrescrever + [
        'tipo_sigla' => 'DAS',
        'tipo_nome' => 'Documento de Arrecadação do Simples',
        'competencia' => '2026-10-01',
        'data_vencimento' => '2026-10-28',
        'valor_centavos' => 185000,
    ];
}

function msgCobrancaTeste(array $guia, string $empresa = 'Padaria Pão Quente ME', string $hoje = '2026-11-17'): string
{
    return MensagemCobranca::montar($empresa, $guia, new DateTimeImmutable($hoje));
}

teste('Cobrança: guia ATRASADA traz empresa, guia, vencimento, dias e valor atualizado com multa', function () {
    $m = msgCobrancaTeste(guiaCobrancaTeste()); // 20 dias de atraso
    foreach (['Padaria Pão Quente ME', 'DAS', '10/2026', '28/10/2026', '20 dias', 'R$ 1.850,00', 'R$ 1.984,43'] as $trecho) {
        afirmarVerdadeiro(str_contains($m, $trecho), "faltou: $trecho\n$m");
    }
    afirmarVerdadeiro(str_contains($m, 'multa e juros'));
});

teste('Cobrança: o valor atualizado é o original + multa + juros do MultaService', function () {
    // R$ 1.850,00 / 20 dias = multa 122,10 + juros 12,33 = 134,43 -> 1.984,43
    afirmarVerdadeiro(str_contains(msgCobrancaTeste(guiaCobrancaTeste()), 'R$ 1.984,43'));
    // R$ 620,50 / 5 dias = 10,24 + 1,03 = 11,27 -> 631,77
    $m = msgCobrancaTeste(guiaCobrancaTeste(['tipo_sigla' => 'FGTS', 'data_vencimento' => '2026-11-12', 'valor_centavos' => 62050]));
    afirmarVerdadeiro(str_contains($m, 'R$ 631,77'));
});

teste('Cobrança: 1 dia de atraso fica no singular', function () {
    $m = msgCobrancaTeste(guiaCobrancaTeste(['data_vencimento' => '2026-11-16']));
    afirmarVerdadeiro(str_contains($m, '1 dia'));
    afirmarFalso(str_contains($m, '1 dias'));
});

teste('Cobrança: guia A VENCER é um lembrete (sem multa) com o valor e o prazo', function () {
    $m = msgCobrancaTeste(guiaCobrancaTeste(['data_vencimento' => '2026-11-20', 'valor_centavos' => 62050]));
    afirmarVerdadeiro(str_contains($m, '20/11/2026'));
    afirmarVerdadeiro(str_contains($m, 'em 3 dias'));
    afirmarVerdadeiro(str_contains($m, 'R$ 620,50'));
    afirmarFalso(str_contains($m, 'multa e juros'), 'lembrete não deve cobrar multa');
    afirmarFalso(str_contains($m, 'atrasad'), 'lembrete não deve falar em atraso');
});

teste('Cobrança: prazo em linguagem natural — hoje, amanhã e em N dias', function () {
    afirmarVerdadeiro(str_contains(msgCobrancaTeste(guiaCobrancaTeste(['data_vencimento' => '2026-11-17'])), 'vence hoje'));
    afirmarVerdadeiro(str_contains(msgCobrancaTeste(guiaCobrancaTeste(['data_vencimento' => '2026-11-18'])), 'vence amanhã'));
    afirmarVerdadeiro(str_contains(msgCobrancaTeste(guiaCobrancaTeste(['data_vencimento' => '2026-11-27'])), 'em 10 dias'));
});

teste('Cobrança: guia sem valor a pagar não inventa quantia nem multa', function () {
    $m = msgCobrancaTeste(guiaCobrancaTeste(['valor_centavos' => 0, 'tipo_sigla' => 'DCTF']));
    afirmarVerdadeiro(str_contains($m, 'DCTF'));
    afirmarVerdadeiro(str_contains($m, 'sem valor a pagar'));
    afirmarFalso(str_contains($m, 'R$ 0,00'));
    afirmarFalso(str_contains($m, 'multa e juros'));
});

teste('Cobrança: nome da empresa com quebra de linha vira uma linha só (sem forjar mensagem)', function () {
    $m = msgCobrancaTeste(guiaCobrancaTeste(), "Padaria\nTeste\r\nLtda");
    afirmarVerdadeiro(str_contains($m, 'Padaria Teste Ltda'));
});

teste('Cobrança: aceita valor vindo do banco como texto', function () {
    afirmarVerdadeiro(str_contains(msgCobrancaTeste(guiaCobrancaTeste(['valor_centavos' => '185000'])), 'R$ 1.850,00'));
});

teste('Cobrança: guia paga não gera mensagem (exceção)', function () {
    afirmarExcecao(InvalidArgumentException::class, fn () => msgCobrancaTeste(guiaCobrancaTeste(['status' => 'pago'])));
});

teste('Cobrança: data de vencimento inválida lança exceção', function () {
    afirmarExcecao(InvalidArgumentException::class, fn () => msgCobrancaTeste(guiaCobrancaTeste(['data_vencimento' => 'ontem'])));
});

// --- Link do WhatsApp ---
teste('Cobrança: link do WhatsApp usa wa.me com o texto codificado', function () {
    $link = MensagemCobranca::linkWhatsApp('Olá!');
    afirmarIgual('https://wa.me/?text=Ol%C3%A1%21', $link);
});

teste('Cobrança: o texto do link volta idêntico ao decodificar (ida e volta)', function () {
    $m = msgCobrancaTeste(guiaCobrancaTeste());
    $link = MensagemCobranca::linkWhatsApp($m);
    afirmarVerdadeiro(str_starts_with($link, 'https://wa.me/?text='));
    afirmarIgual($m, rawurldecode(substr($link, strlen('https://wa.me/?text='))));
});

teste('Cobrança: caracteres que quebrariam a URL (& # ? espaço, quebra de linha, aspas) saem codificados', function () {
    $link = MensagemCobranca::linkWhatsApp("A & B #1 ?x=1\n\"c\" <i>");
    $texto = substr($link, strlen('https://wa.me/?text='));
    foreach (['&', '#', '?', ' ', "\n", '"', '<', '>'] as $perigoso) {
        afirmarFalso(str_contains($texto, $perigoso), "sobrou [$perigoso] sem codificar");
    }
    afirmarIgual("A & B #1 ?x=1\n\"c\" <i>", rawurldecode($texto));
});

teste('Cobrança: só o domínio fixo wa.me recebe a mensagem (dado do usuário nunca vira host)', function () {
    foreach (['http://evil.example/', '@evil.example', '//evil.example'] as $nome) {
        $link = MensagemCobranca::linkWhatsApp(msgCobrancaTeste(guiaCobrancaTeste(), $nome));
        afirmarIgual('wa.me', parse_url($link, PHP_URL_HOST));
        afirmarIgual('https', parse_url($link, PHP_URL_SCHEME));
    }
});

// --- Enriquecimento da lista de obrigações (botão "Cobrar no WhatsApp" por linha) ---
function linhaListaCobrancaTeste(int $id, string $status, string $vencimento, string $valor, string $sigla = 'DAS'): array
{
    return [
        'id' => $id, 'status' => $status, 'tipo_sigla' => $sigla, 'tipo_nome' => 'Guia ' . $sigla,
        'competencia' => '2026-10-01', 'data_vencimento' => $vencimento, 'valor_centavos' => $valor,
    ];
}

teste('Cobrança (lista): atrasada ganha link do WhatsApp e valor atualizado com multa', function () {
    $r = MensagemCobranca::enriquecerLista([linhaListaCobrancaTeste(1, 'atrasado', '2026-10-28', '185000')], 'Padaria Pão Quente ME', new DateTimeImmutable('2026-11-17'));
    afirmarVerdadeiro(str_starts_with($r[0]['link_whatsapp'], 'https://wa.me/?text='));
    afirmarVerdadeiro(str_contains(rawurldecode($r[0]['link_whatsapp']), 'R$ 1.984,43'));
    afirmarIgual(198443, $r[0]['valor_atualizado_centavos']);
});

teste('Cobrança (lista): a vencer ganha link (lembrete) mas não valor atualizado', function () {
    $r = MensagemCobranca::enriquecerLista([linhaListaCobrancaTeste(3, 'pendente', '2026-11-20', '62050')], 'Padaria', new DateTimeImmutable('2026-11-17'));
    afirmarVerdadeiro(str_contains(rawurldecode($r[0]['link_whatsapp']), 'vence em 3 dias'));
    afirmarIgual(null, $r[0]['valor_atualizado_centavos']);
});

teste('Cobrança (lista): guia paga não tem link nem valor atualizado', function () {
    $r = MensagemCobranca::enriquecerLista([linhaListaCobrancaTeste(5, 'pago', '2026-11-07', '100')], 'Tech', new DateTimeImmutable('2026-11-17'));
    afirmarIgual(null, $r[0]['link_whatsapp']);
    afirmarIgual(null, $r[0]['valor_atualizado_centavos']);
});

teste('Cobrança (lista): mantém a ordem e os campos originais; lista vazia continua vazia', function () {
    $linhas = [linhaListaCobrancaTeste(1, 'atrasado', '2026-10-28', '1000'), linhaListaCobrancaTeste(2, 'pago', '2026-11-01', '1000', 'FGTS')];
    $r = MensagemCobranca::enriquecerLista($linhas, 'X', new DateTimeImmutable('2026-11-17'));
    afirmarIgual([1, 2], array_column($r, 'id'));
    afirmarIgual('FGTS', $r[1]['tipo_sigla']);
    afirmarIgual([], MensagemCobranca::enriquecerLista([], 'X', new DateTimeImmutable('2026-11-17')));
});

teste('Cobrança (lista): atrasada pelo vencimento mesmo com status "pendente" desatualizado', function () {
    $r = MensagemCobranca::enriquecerLista([linhaListaCobrancaTeste(1, 'pendente', '2026-11-16', '100000')], 'X', new DateTimeImmutable('2026-11-17'));
    afirmarIgual(100363, $r[0]['valor_atualizado_centavos']);
});

<?php
declare(strict_types=1);

use App\Core\View;

/*
 * Telas das obrigações: lista (com o termômetro embutido), formulário (com a prévia do termômetro) e o atalho no painel
 * do cliente. Renderizam a view direto, sem servidor e sem banco. Cobrem o que muda conforme QUEM olha:
 *  - dono da empresa: cria, paga e exclui as próprias guias; NÃO vê a ferramenta de cobrança (WhatsApp, valor com multa).
 *  - contador: vê tudo isso, de qualquer empresa.
 */

function empresaViewTeste(array $sobrescrever = []): array
{
    return $sobrescrever + ['id' => 7, 'razao_social' => 'Padaria Teste ME', 'cnpj' => '12345678000195', 'regime_tributario' => 'mei', 'email_contato' => 'a@b.test', 'ativa' => 1];
}

function guiaViewTeste(int $id, string $status, array $extra = []): array
{
    return $extra + [
        'id' => $id, 'tipo_sigla' => 'DAS', 'tipo_nome' => 'Documento de Arrecadação do Simples', 'observacao' => null,
        'competencia' => '2026-10-01', 'data_vencimento' => '2026-10-28', 'valor_centavos' => 185000, 'status' => $status,
        'data_pagamento' => $status === 'pago' ? '2026-11-05' : null,
    ];
}

function listaObrigacoesTeste(array $obrigacoes, bool $ehContador, bool $podeAgir = true, array $empresa = []): string
{
    $_SESSION = [];
    return View::renderizar('obrigacoes/index', [
        'titulo' => 'Obrigações', 'empresa' => $empresa ?: empresaViewTeste(), 'obrigacoes' => $obrigacoes,
        'ehContador' => $ehContador, 'podeAgir' => $podeAgir,
    ], '');
}

// --- Lista: o que o DONO enxerga ---
teste('Obrigações (dono): vê o botão Nova obrigação, e Pagar e Excluir nas guias em aberto', function () {
    $html = listaObrigacoesTeste([guiaViewTeste(1, 'atrasado'), guiaViewTeste(2, 'pendente')], false);
    afirmarVerdadeiro(preg_match('~href="index\.php\?rota=obrigacoes/criar&amp;empresa_id=7"[^>]*>.*?Nova obrigação~s', $html) === 1);
    afirmarIgual(2, substr_count($html, 'rota=obrigacoes/pagar'));
    afirmarIgual(2, substr_count($html, 'rota=obrigacoes/excluir'));
});

teste('Obrigações (dono): NÃO vê a ferramenta de cobrança (WhatsApp, valor com multa)', function () {
    $html = listaObrigacoesTeste([guiaViewTeste(1, 'atrasado')], false);
    afirmarFalso(str_contains($html, 'WhatsApp'));
    afirmarFalso(str_contains($html, 'wa.me'));
    afirmarFalso(str_contains($html, 'com multa e juros'));
});

teste('Obrigações: guia paga não tem ação de pagar nem de excluir (é histórico)', function () {
    $html = listaObrigacoesTeste([guiaViewTeste(1, 'pago')], false);
    afirmarFalso(str_contains($html, 'rota=obrigacoes/pagar'));
    afirmarFalso(str_contains($html, 'rota=obrigacoes/excluir'));
    afirmarVerdadeiro(str_contains($html, '>Pago<'));
});

teste('Obrigações: cada formulário de pagar/excluir leva o token CSRF e o id da guia', function () {
    $html = listaObrigacoesTeste([guiaViewTeste(41, 'atrasado')], false);
    afirmarVerdadeiro(preg_match('~rota=obrigacoes/pagar".*?name="csrf_token".*?name="id" value="41"~s', $html) === 1);
    afirmarVerdadeiro(preg_match('~rota=obrigacoes/excluir".*?name="csrf_token".*?name="id" value="41"~s', $html) === 1);
});

// --- Lista: o que o CONTADOR enxerga ---
teste('Obrigações (contador): vê o botão de WhatsApp nas guias que têm link, além de pagar e excluir', function () {
    $guia = guiaViewTeste(1, 'atrasado', ['link_whatsapp' => 'https://wa.me/?text=Ol%C3%A1', 'valor_atualizado_centavos' => 198443]);
    $html = listaObrigacoesTeste([$guia], true);
    afirmarVerdadeiro(str_contains($html, 'Cobrar no WhatsApp'));
    afirmarVerdadeiro(str_contains($html, 'href="https://wa.me/?text=Ol%C3%A1"'));
    afirmarVerdadeiro(str_contains($html, 'R$ 1.984,43'));
    afirmarVerdadeiro(str_contains($html, 'rota=obrigacoes/pagar'));
});

// --- Empresa inativa ---
teste('Obrigações: empresa inativa não aceita ações (sem Nova obrigação, pagar ou excluir), mas a lista aparece', function () {
    $html = listaObrigacoesTeste([guiaViewTeste(1, 'atrasado')], false, false, empresaViewTeste(['ativa' => 0]));
    afirmarFalso(str_contains($html, 'Nova obrigação'));
    afirmarFalso(str_contains($html, 'rota=obrigacoes/pagar'));
    afirmarVerdadeiro(str_contains($html, 'DAS'));
});

// --- Lista com o termômetro embutido ---
teste('Obrigações: a lista traz o termômetro da empresa no topo (para ver o efeito de criar, pagar ou excluir)', function () {
    $html = listaObrigacoesTeste([guiaViewTeste(1, 'atrasado')], false);
    afirmarVerdadeiro(str_contains($html, 'data-termometro'));
    afirmarVerdadeiro(str_contains($html, 'rota=termometro/json&amp;empresa_id=7'));
    afirmarVerdadeiro(strpos($html, 'data-termometro') < strpos($html, '<table'), 'o termômetro vem antes da tabela');
});

teste('Obrigações (dono): sem nenhuma guia, a tela convida a cadastrar a primeira', function () {
    $html = listaObrigacoesTeste([], false);
    afirmarVerdadeiro(str_contains($html, 'Nenhuma obrigação cadastrada'));
    afirmarVerdadeiro(preg_match('~href="[^"]*obrigacoes/criar[^"]*"[^>]*>[^<]*(<i[^>]*></i>)?[^<]*primeira~s', $html) === 1);
});

teste('Obrigações: observação e nomes vindos do banco saem escapados (XSS)', function () {
    $html = listaObrigacoesTeste([guiaViewTeste(1, 'pendente', ['observacao' => '"><img src=x onerror=alert(1)>'])], false, true, empresaViewTeste(['razao_social' => '<script>alert(1)</script>']));
    afirmarFalso(str_contains($html, '<img src=x'));
    afirmarFalso(str_contains($html, '<script>alert(1)'));
    afirmarVerdadeiro(str_contains($html, '&lt;script&gt;alert(1)'));
});

// --- Formulário com prévia ---
function formObrigacaoTeste(array $valores = [], array $erros = []): string
{
    $_SESSION = [];
    return View::renderizar('obrigacoes/form', [
        'titulo' => 'Nova obrigação', 'empresa' => empresaViewTeste(),
        'valores' => $valores + ['tipo_obrigacao_id' => '', 'competencia' => '', 'data_vencimento' => '', 'valor' => '', 'observacao' => ''],
        'erros' => $erros,
        'tipos' => [['id' => 1, 'sigla' => 'DAS', 'nome' => 'Simples'], ['id' => 2, 'sigla' => 'DCTF', 'nome' => 'Débitos']],
    ], '');
}

teste('Formulário: aponta a prévia do termômetro para a empresa certa e tem o espaço onde ela aparece', function () {
    $html = formObrigacaoTeste();
    afirmarVerdadeiro(preg_match('~data-simular-url="index\.php\?rota=termometro/simular&amp;empresa_id=7"~', $html) === 1);
    afirmarVerdadeiro(str_contains($html, 'data-simulacao'));
});

teste('Formulário: tem atalhos de data para simular atraso e prazo (sem JavaScript o formulário continua funcionando)', function () {
    $html = formObrigacaoTeste();
    preg_match_all('~data-atalho="(-?\d+)"~', $html, $m);
    afirmarVerdadeiro(count($m[1]) >= 4, 'pelo menos 4 atalhos');
    afirmarVerdadeiro(in_array('-1', $m[1], true) && in_array('0', $m[1], true) && in_array('7', $m[1], true));
    afirmarVerdadeiro(str_contains($html, 'name="data_vencimento"') && str_contains($html, 'type="submit"'));
});

teste('Formulário: continua com CSRF, empresa oculta e cancelar volta para a lista', function () {
    $html = formObrigacaoTeste();
    afirmarVerdadeiro(str_contains($html, 'name="csrf_token"'));
    afirmarVerdadeiro(preg_match('~name="empresa_id" value="7"~', $html) === 1);
    afirmarVerdadeiro(str_contains($html, 'rota=obrigacoes/index&amp;empresa_id=7'));
});

teste('Formulário: valores digitados voltam escapados e o erro aparece junto do campo', function () {
    $html = formObrigacaoTeste(['observacao' => '"><script>alert(1)</script>'], ['competencia' => 'Já existe uma obrigação deste tipo.']);
    afirmarFalso(str_contains($html, '<script>alert(1)'));
    afirmarVerdadeiro(str_contains($html, '&lt;script&gt;alert(1)'));
    afirmarVerdadeiro(str_contains($html, 'Já existe uma obrigação deste tipo.'));
});

// --- Atalho no painel do cliente ---
teste('Painel do cliente: tem o botão Nova obrigação apontando para a PRÓPRIA empresa', function () {
    $_SESSION = [];
    $html = View::renderizar('dashboard/cliente', [
        'titulo' => 'Minha empresa', 'usuario' => ['id' => 2, 'nome' => 'Ana', 'perfil' => 'cliente', 'empresa_id' => 9],
        'empresa' => empresaViewTeste(['id' => 9]), 'proximas' => [],
        'financeiro' => App\Services\ResumoFinanceiro::calcular([], new DateTimeImmutable('2026-11-17')), 'mes' => '11/2026',
    ], '');
    afirmarVerdadeiro(preg_match('~href="index\.php\?rota=obrigacoes/criar&amp;empresa_id=9"[^>]*>.*?Nova obrigação~s', $html) === 1);
});

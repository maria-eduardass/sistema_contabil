<?php
declare(strict_types=1);

use App\Core\Env;
use App\Core\Logger;
use App\Core\Request;

teste('e() escapa tags e aspas contra XSS', function () {
    afirmarIgual('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', e('<script>alert("x")</script>'));
    afirmarIgual('a&#039;b', e("a'b"));
});

teste('e() aceita null e devolve string vazia', function () {
    afirmarIgual('', e(null));
});

teste('url() monta a rota e codifica os parâmetros', function () {
    afirmarIgual('index.php?rota=empresas/ver&id=3', url('empresas/ver', ['id' => 3]));
    afirmarIgual('index.php?rota=inicio/index', url('inicio/index'));
    afirmarIgual('index.php?rota=a/b&q=x%26y', url('a/b', ['q' => 'x&y']));
});

teste('Request usa inicio/index quando não há rota', function () {
    afirmarIgual('inicio/index', (new Request('GET', [], []))->rota());
});

teste('Request ignora valores em array e normaliza o método', function () {
    $req = new Request('post', ['rota' => ['x']], ['campo' => ['a']]);
    afirmarIgual('POST', $req->metodo());
    afirmarIgual('inicio/index', $req->rota());
    afirmarIgual(null, $req->post('campo'));
});

teste('Env lê chaves, ignora comentários e remove aspas', function () {
    $arquivo = tempnam(sys_get_temp_dir(), 'env');
    file_put_contents($arquivo, "# comentario\nDB_NAME=\"meu_banco\"\nDB_PASS=\nINVALIDA\n");
    Env::carregar($arquivo);
    unlink($arquivo);
    afirmarIgual('meu_banco', Env::get('DB_NAME'));
    afirmarIgual('', Env::get('DB_PASS'));
    afirmarIgual('padrao', Env::get('NAO_EXISTE', 'padrao'));
    Env::carregar(RAIZ . '/.env'); // restaura o estado real para os demais testes
});

teste('Logger grava uma única linha mesmo com quebra de linha na mensagem', function () {
    $arquivo = tempnam(sys_get_temp_dir(), 'log');
    Logger::erro(new RuntimeException("falha\nlinha forjada"), $arquivo);
    $linhas = file($arquivo, FILE_IGNORE_NEW_LINES);
    unlink($arquivo);
    afirmarIgual(1, count($linhas));
    afirmarVerdadeiro(str_contains($linhas[0], 'RuntimeException: falha | linha forjada'));
});

teste('afirmarExcecao reconhece a classe esperada', function () {
    afirmarExcecao(InvalidArgumentException::class, fn () => throw new InvalidArgumentException('x'));
});

teste('Request: getInt/postInt aceitam só inteiros positivos simples', function () {
    $req = new Request('GET', ['id' => '12', 'a' => '0', 'b' => '-3', 'c' => '1.5', 'd' => '12abc', 'e' => '012', 'f' => '99999999999'], ['id' => '7', 'x' => ['1']]);
    afirmarIgual(12, $req->getInt('id'));
    afirmarIgual(7, $req->postInt('id'));
    foreach (['a', 'b', 'c', 'd', 'e', 'f', 'inexistente'] as $chave) {
        afirmarIgual(null, $req->getInt($chave), $chave);
    }
    afirmarIgual(null, $req->postInt('x'));
});

teste('formatarCnpj() aplica a máscara e preserva valores fora do padrão', function () {
    afirmarIgual('11.222.333/0001-81', formatarCnpj('11222333000181'));
    afirmarIgual('abc', formatarCnpj('abc'));
});

teste('formatarCentavos() mostra R$ com milhar e vírgula, sem erro de arredondamento', function () {
    afirmarIgual('R$ 1.234,56', formatarCentavos(123456));
    afirmarIgual('R$ 0,00', formatarCentavos(0));
    afirmarIgual('R$ 0,05', formatarCentavos(5));
    afirmarIgual('R$ 1.850,00', formatarCentavos(185000));
    afirmarIgual('R$ 99.999.999,99', formatarCentavos(9999999999));
});

teste('formatarData() e formatarCompetencia() convertem para o padrão brasileiro', function () {
    afirmarIgual('17/11/2026', formatarData('2026-11-17'));
    afirmarIgual('11/2026', formatarCompetencia('2026-11-01'));
    afirmarIgual('lixo', formatarData('lixo'));
    afirmarIgual('lixo', formatarCompetencia('lixo'));
});

teste('formatarDataHora() converte timestamp do banco para dd/mm/aaaa hh:mm', function () {
    afirmarIgual('17/11/2026 14:05', formatarDataHora('2026-11-17 14:05:33'));
    afirmarIgual('lixo', formatarDataHora('lixo'));
});

// --- Selos e ícones (Bootstrap Icons) ---
teste('seloStatus() mostra ícone + rótulo para Pago, Pendente e Atrasado (nunca só cor)', function () {
    $pago = seloStatus('pago');
    afirmarVerdadeiro(str_contains($pago, 'selo-status-pago') && str_contains($pago, '>Pago<') && str_contains($pago, 'bi-check-circle-fill'));
    $pendente = seloStatus('pendente');
    afirmarVerdadeiro(str_contains($pendente, 'selo-status-pendente') && str_contains($pendente, '>Pendente<') && str_contains($pendente, 'bi-clock-history'));
    $atrasado = seloStatus('atrasado');
    afirmarVerdadeiro(str_contains($atrasado, 'selo-status-atrasado') && str_contains($atrasado, '>Atrasado<') && str_contains($atrasado, 'bi-exclamation-triangle-fill'));
    foreach ([$pago, $pendente, $atrasado] as $html) {
        afirmarVerdadeiro(str_contains($html, 'aria-hidden="true"'), 'o ícone é decorativo: o rótulo é lido');
    }
});

teste('seloStatus() com valor desconhecido escapa o texto (nada do banco vira HTML)', function () {
    $html = seloStatus('<script>alert(1)</script>');
    afirmarFalso(str_contains($html, '<script>'));
    afirmarVerdadeiro(str_contains($html, '&lt;script&gt;'));
    afirmarVerdadeiro(str_contains($html, 'selo-status-desconhecido'));
});

teste('seloFaixa() usa um ícone diferente por faixa e o rótulo recebido', function () {
    $esperado = ['baixo' => 'bi-check-circle-fill', 'atencao' => 'bi-exclamation-circle-fill', 'alto' => 'bi-exclamation-triangle-fill', 'critico' => 'bi-x-octagon-fill'];
    foreach ($esperado as $faixa => $icone) {
        $html = seloFaixa($faixa, 'Rótulo ' . $faixa);
        afirmarVerdadeiro(str_contains($html, "selo-faixa-$faixa") && str_contains($html, $icone) && str_contains($html, "Rótulo $faixa"), $faixa);
    }
    afirmarFalso(str_contains(seloFaixa('x', '<b>'), '<b>'));
});

teste('icone() só aceita nomes simples de ícone (sem injetar atributos)', function () {
    afirmarIgual('<i class="bi bi-whatsapp" aria-hidden="true"></i>', icone('whatsapp'));
    afirmarIgual('<i class="bi bi-box-arrow-right" aria-hidden="true"></i>', icone('box-arrow-right'));
    foreach (['x" onload="alert(1)', 'A', '', 'a b', '<i>'] as $ruim) {
        afirmarIgual('', icone($ruim), $ruim);
    }
});

teste('iniciais() devolve a primeira letra do nome, em maiúscula e com acento', function () {
    afirmarIgual('C', iniciais('carla Contadora'));
    afirmarIgual('Á', iniciais('álvaro'));
    afirmarIgual('?', iniciais('   '));
});

teste('plural() concorda com a quantidade (0 e 1 no singular em português: 0 guias, 1 guia)', function () {
    afirmarIgual('1 guia', plural(1, 'guia', 'guias'));
    afirmarIgual('2 guias', plural(2, 'guia', 'guias'));
    afirmarIgual('0 guias', plural(0, 'guia', 'guias'));
    afirmarIgual('12 empresas ativas', plural(12, 'empresa ativa', 'empresas ativas'));
});

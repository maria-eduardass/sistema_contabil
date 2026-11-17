<?php
declare(strict_types=1);

use App\Core\View;
use App\Services\ValidadorEmpresa;

function formCadastroTeste(array $valores = [], array $erros = [], array $extra = [], string $layout = ''): string
{
    $_SESSION = [];
    $valores += ['nome' => '', 'email' => '', 'razao_social' => '', 'cnpj' => '', 'regime_tributario' => ''];

    return View::renderizar('cadastro/form', $extra + [
        'titulo' => 'Criar conta', 'valores' => $valores, 'erros' => $erros, 'regimes' => ValidadorEmpresa::REGIMES,
    ], $layout);
}

teste('Login: tem o botão "Criar conta" apontando para o cadastro', function () {
    $_SESSION = [];
    $html = View::renderizar('auth/login', ['titulo' => 'Entrar', 'erro' => null, 'email' => ''], '');
    afirmarVerdadeiro(preg_match('~<a [^>]*href="index\.php\?rota=cadastro/novo"[^>]*>.*?Criar conta~s', $html) === 1);
});

teste('Login: o botão "Criar conta" não atrapalha o formulário de entrada (continua com e-mail, senha e CSRF)', function () {
    $_SESSION = [];
    $html = View::renderizar('auth/login', ['titulo' => 'Entrar', 'erro' => null, 'email' => ''], '');
    foreach (['name="email"', 'name="senha"', 'name="csrf_token"', 'rota=auth/login'] as $trecho) {
        afirmarVerdadeiro(str_contains($html, $trecho), $trecho);
    }
});

teste('Cadastro (tela): tem todos os campos, o token CSRF e envia por POST para cadastro/salvar', function () {
    $html = formCadastroTeste();
    foreach (['name="nome"', 'name="email"', 'name="senha"', 'name="senha_confirmacao"', 'name="razao_social"', 'name="cnpj"', 'name="regime_tributario"', 'name="csrf_token"'] as $campo) {
        afirmarVerdadeiro(str_contains($html, $campo), $campo);
    }
    afirmarVerdadeiro(preg_match('~<form method="post" action="[^"]*rota=cadastro/salvar"~', $html) === 1);
});

teste('Cadastro (tela): lista os 4 regimes tributários e tem o caminho de volta para o login', function () {
    $html = formCadastroTeste();
    foreach (ValidadorEmpresa::REGIMES as $rotulo) {
        afirmarVerdadeiro(str_contains($html, '>' . $rotulo . '<'), $rotulo);
    }
    afirmarVerdadeiro(str_contains($html, 'rota=auth/login'));
});

teste('Cadastro (tela): campos de senha nunca voltam preenchidos, mesmo após um erro', function () {
    $html = formCadastroTeste(['nome' => 'Maria'], ['senha' => 'Fraca'], ['senha' => 'Segredo@123', 'senha_confirmacao' => 'Segredo@123']);
    afirmarFalso(str_contains($html, 'Segredo@123'));
    afirmarFalso(preg_match('~type="password"[^>]*\svalue=~', $html) === 1);
});

teste('Cadastro (tela): erros aparecem junto do campo e os valores digitados voltam escapados (XSS)', function () {
    $html = formCadastroTeste(['nome' => '"><script>alert(1)</script>', 'razao_social' => '<img src=x onerror=alert(1)>'], ['cnpj' => 'CNPJ inválido.', 'geral' => 'Tente de novo.']);
    afirmarVerdadeiro(str_contains($html, 'CNPJ inválido.') && str_contains($html, 'Tente de novo.'));
    afirmarFalso(str_contains($html, '<script>alert(1)'));
    afirmarFalso(str_contains($html, '<img src=x'));
    afirmarVerdadeiro(str_contains($html, '&lt;script&gt;alert(1)'));
});

teste('Cadastro (tela): o regime escolhido continua selecionado após um erro', function () {
    $html = formCadastroTeste(['regime_tributario' => 'presumido']);
    afirmarVerdadeiro(preg_match('~<option value="presumido" selected>~', $html) === 1);
    afirmarFalso(preg_match('~<option value="mei" selected>~', $html) === 1);
});

teste('Cadastro (tela): na casca pública a caixa fica mais larga (auth-caixa-larga) só quando pedido', function () {
    $larga = formCadastroTeste([], [], ['auth' => 'larga'], 'layout/principal');
    afirmarVerdadeiro(str_contains($larga, 'auth-caixa-larga'));
    afirmarVerdadeiro(str_contains($larga, 'auth-wrap'));
    $normal = formCadastroTeste([], [], [], 'layout/principal');
    afirmarFalso(str_contains($normal, 'auth-caixa-larga'));
});

teste('Cadastro (tela): sem estilo, handler ou script inline (compatível com a CSP)', function () {
    $html = formCadastroTeste([], [], ['auth' => 'larga'], 'layout/principal');
    afirmarFalso(preg_match('/\sstyle\s*=/i', $html) === 1);
    afirmarFalso(preg_match('/\son[a-z]+\s*=/i', $html) === 1);
    afirmarFalso(preg_match('/<script(?![^>]*\ssrc=)[^>]*>/i', $html) === 1);
});

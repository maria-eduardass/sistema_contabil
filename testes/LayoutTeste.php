<?php
declare(strict_types=1);

use App\Core\View;

/** Renderiza uma página de erro dentro do layout, como a pessoa (ou o visitante) a veria. */
function layoutTeste(?array $usuario, string $rota = ''): string
{
    $_SESSION = [];
    if ($usuario !== null) {
        $_SESSION['usuario'] = $usuario;
        $_SESSION['ultimo_acesso'] = time();
    }
    View::compartilhar('rotaAtual', $rota);
    try {
        return View::renderizar('erros/erro', ['titulo' => 'Teste', 'codigo' => 404, 'mensagem' => 'Página não encontrada.']);
    } finally {
        View::compartilhar('rotaAtual', '');
        $_SESSION = [];
    }
}

function contadorLayoutTeste(string $nome = 'Carla Contadora'): array
{
    return ['id' => 1, 'nome' => $nome, 'perfil' => 'contador', 'empresa_id' => null];
}

function clienteLayoutTeste(): array
{
    return ['id' => 2, 'nome' => 'João da Padaria', 'perfil' => 'cliente', 'empresa_id' => 1];
}

/** Rótulos dos links do menu, e quais deles estão marcados como ativos. */
function menuTeste(string $html): array
{
    preg_match_all('~<a class="(nav-link(?: active)?)" [^>]*>(.*?)</a>~s', $html, $m, PREG_SET_ORDER);
    $todos = [];
    $ativos = [];
    foreach ($m as $item) {
        $rotulo = trim(preg_replace('/\s+/', ' ', strip_tags($item[2])));
        $todos[] = $rotulo;
        if (str_contains($item[1], 'active')) {
            $ativos[] = $rotulo;
        }
    }
    return ['todos' => $todos, 'ativos' => $ativos];
}

// --- Visitante (login e páginas de erro públicas) ---
teste('Layout: visitante vê a página centrada, sem menu lateral nem modal de avaliação', function () {
    $html = layoutTeste(null);
    afirmarVerdadeiro(str_contains($html, 'auth-wrap'));
    afirmarFalso(str_contains($html, 'id="menuLateral"'));
    afirmarFalso(str_contains($html, 'id="modalAvaliacao"'));
    afirmarFalso(str_contains($html, 'avaliacao.js'));
});

// --- Estrutura do app logado ---
teste('Layout: logado tem menu lateral fixo (offcanvas no celular), botão de abrir e rodapé do usuário', function () {
    $html = layoutTeste(contadorLayoutTeste());
    afirmarVerdadeiro(str_contains($html, 'id="menuLateral"'));
    afirmarVerdadeiro(str_contains($html, 'offcanvas-lg'));
    afirmarVerdadeiro(str_contains($html, 'data-bs-toggle="offcanvas"') && str_contains($html, 'data-bs-target="#menuLateral"'));
    afirmarFalso(str_contains($html, 'auth-wrap'));
});

teste('Layout: carrega Bootstrap Icons com integridade (SRI) e o modal de avaliação para quem está logado', function () {
    $html = layoutTeste(clienteLayoutTeste());
    afirmarVerdadeiro(str_contains($html, 'bootstrap-icons@1.11.3/font/bootstrap-icons.min.css'));
    afirmarVerdadeiro(preg_match('~bootstrap-icons[^>]*integrity="sha384-[A-Za-z0-9+/=]+"~', $html) === 1);
    afirmarVerdadeiro(str_contains($html, 'id="modalAvaliacao"'));
    afirmarVerdadeiro(str_contains($html, 'avaliacao.js'));
});

teste('Layout: botão "Avaliar o sistema" abre o modal e "Sair" é um POST com token CSRF', function () {
    $html = layoutTeste(contadorLayoutTeste());
    afirmarVerdadeiro(preg_match('~data-bs-target="#modalAvaliacao"[^>]*>.*?Avaliar o sistema~s', $html) === 1);
    afirmarVerdadeiro(preg_match('~<form method="post" action="[^"]*auth/logout"[^>]*>.*?name="csrf_token".*?Sair~s', $html) === 1);
});

teste('Layout: ícones do Bootstrap Icons aparecem no menu e são decorativos (aria-hidden)', function () {
    $html = layoutTeste(contadorLayoutTeste());
    afirmarVerdadeiro(substr_count($html, 'class="bi bi-') >= 4);
    afirmarFalso(preg_match('~<i class="bi [^"]*"(?![^>]*aria-hidden)~', $html) === 1);
});

// --- Menu por perfil ---
teste('Layout: contador vê Painel, Empresas e Avaliações e os filtros', function () {
    afirmarIgual(['Painel', 'Empresas', 'Avaliações', 'Contas pagas', 'Contas em aberto'], menuTeste(layoutTeste(contadorLayoutTeste()))['todos']);
});

teste('Layout: cliente vê Painel, Minhas obrigações e os filtros (da própria empresa)', function () {
    $html = layoutTeste(clienteLayoutTeste());
    afirmarIgual(['Painel', 'Minhas obrigações', 'Contas pagas', 'Contas em aberto'], menuTeste($html)['todos']);
    afirmarVerdadeiro(preg_match('~obrigacoes/index&amp;empresa_id=1~', $html) === 1);
    afirmarFalso(str_contains($html, 'empresas/index'));
    afirmarFalso(str_contains($html, 'avaliacoes/index'));
});

// --- Item ativo ---
teste('Layout: o item ativo acompanha a página (Painel, Empresas, Avaliações)', function () {
    $casos = [
        'dashboard/contador' => 'Painel', 'dashboard/index' => 'Painel',
        'empresas/index' => 'Empresas', 'empresas/ver' => 'Empresas', 'empresas/editar' => 'Empresas',
        'usuarios/criar' => 'Empresas', 'obrigacoes/index' => 'Empresas', 'obrigacoes/criar' => 'Empresas',
        'avaliacoes/index' => 'Avaliações',
    ];
    foreach ($casos as $rota => $ativo) {
        afirmarIgual([$ativo], menuTeste(layoutTeste(contadorLayoutTeste(), $rota))['ativos'], $rota);
    }
});

teste('Layout: para o cliente, obrigações marca "Minhas obrigações" e o painel marca "Painel"', function () {
    afirmarIgual(['Minhas obrigações'], menuTeste(layoutTeste(clienteLayoutTeste(), 'obrigacoes/index'))['ativos']);
    afirmarIgual(['Painel'], menuTeste(layoutTeste(clienteLayoutTeste(), 'dashboard/cliente'))['ativos']);
});

teste('Layout: sem rota conhecida nenhum item fica ativo', function () {
    afirmarIgual([], menuTeste(layoutTeste(contadorLayoutTeste(), ''))['ativos']);
    afirmarIgual([], menuTeste(layoutTeste(contadorLayoutTeste(), 'x/y'))['ativos']);
});

teste('Layout: o item ativo declara aria-current="page" (leitores de tela)', function () {
    $html = layoutTeste(contadorLayoutTeste(), 'empresas/index');
    afirmarVerdadeiro(preg_match('~<a class="nav-link active" [^>]*aria-current="page"~', $html) === 1);
});

// --- Usuário no rodapé do menu ---
teste('Layout: avatar com a inicial do nome (maiúscula e com acento) e perfil legível', function () {
    afirmarVerdadeiro(str_contains(layoutTeste(contadorLayoutTeste('carla Contadora')), '>C</div>'));
    afirmarVerdadeiro(str_contains(layoutTeste(contadorLayoutTeste('álvaro')), '>Á</div>'));
    afirmarVerdadeiro(str_contains(layoutTeste(contadorLayoutTeste()), 'Contador'));
    afirmarVerdadeiro(str_contains(layoutTeste(clienteLayoutTeste()), 'Cliente'));
});

teste('Layout: nome do usuário com HTML é escapado (XSS na barra lateral)', function () {
    $html = layoutTeste(contadorLayoutTeste('<img src=x onerror=alert(1)>'));
    afirmarFalso(str_contains($html, '<img src=x'));
    afirmarVerdadeiro(str_contains($html, '&lt;img src=x onerror=alert(1)&gt;'));
});

teste('Layout: o menu e a página não usam estilo ou script inline (compatível com a CSP)', function () {
    foreach ([null, contadorLayoutTeste(), clienteLayoutTeste()] as $usuario) {
        $html = layoutTeste($usuario, 'dashboard/index');
        afirmarFalso(preg_match('/\sstyle\s*=/i', $html) === 1, 'style inline');
        afirmarFalso(preg_match('/\son[a-z]+\s*=/i', $html) === 1, 'handler inline');
        afirmarFalso(preg_match('/<script(?![^>]*\ssrc=)[^>]*>/i', $html) === 1, 'script inline');
    }
});

teste('Layout: na tela de filtro só o item do filtro fica ativo (Contas pagas)', function () {
    View::compartilhar('filtro', 'pagas');
    $html = layoutTeste(contadorLayoutTeste(), 'obrigacoes/situacao');
    View::compartilhar('filtro', '');
    afirmarIgual(1, substr_count($html, 'nav-link active'));
    afirmarVerdadeiro(preg_match('~nav-link active".*?Contas pagas~s', $html) === 1);
});

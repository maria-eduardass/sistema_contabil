<?php
/**
 * Layout único. Visitante (login, erros públicos): página centrada. Logado: menu lateral fixo
 * (vira gaveta no celular) + área de conteúdo. Sem estilo/script inline: a CSP não permite.
 *
 * @var string $conteudo
 * @var string|null $rotaAtual  compartilhada pelo Router (View::compartilhar)
 */

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\View;

$usuario = Auth::usuario();
$rota = (string) ($rotaAtual ?? '');
$filtro = (string) ($filtro ?? '');
$itens = [];
if ($usuario !== null) {
    // [rótulo, ícone, destino, prefixos de rota que deixam o item "ativo"]
    $itens[] = ['Painel', 'speedometer2', url('dashboard/index'), ['dashboard/']];
    if ($usuario['perfil'] === 'contador') {
        $itens[] = ['Empresas', 'buildings', url('empresas/index'), ['empresas/', 'usuarios/', 'obrigacoes/']];
        $itens[] = ['Avaliações', 'star-fill', url('avaliacoes/index'), ['avaliacoes/']];
    } else {
        $itens[] = ['Minhas obrigações', 'receipt-cutoff', url('obrigacoes/index', ['empresa_id' => (int) $usuario['empresa_id']]), ['obrigacoes/']];
    }
    // Filtros: só pagas / só em aberto. Ficam ativos pelo ?filtro= (passado pelo controller), não pelo prefixo da rota.
    $itens[] = ['Contas pagas', 'check-circle', url('obrigacoes/situacao', ['filtro' => 'pagas']), ['@pagas']];
    $itens[] = ['Contas em aberto', 'hourglass-split', url('obrigacoes/situacao', ['filtro' => 'abertas']), ['@abertas']];
}
$estaAtivo = static function (array $prefixos) use ($rota, $filtro): bool {
    foreach ($prefixos as $prefixo) {
        if ($prefixo[0] === '@') { // item de filtro
            if ($rota === 'obrigacoes/situacao' && $filtro === substr($prefixo, 1)) {
                return true;
            }
            continue;
        }
        if ($rota === 'obrigacoes/situacao') {
            return false; // a tela de filtro só acende o próprio item
        }
        if (str_starts_with($rota, $prefixo)) {
            return true;
        }
    }
    return false;
};
$mensagens = Flash::obter();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($titulo ?? 'Início') . ' · FiscalPulse') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="<?= e($usuario === null ? 'corpo-publico' : 'corpo-app') ?>">
<?php if ($usuario !== null) : ?>
<div class="app-shell">
    <?php /* offcanvas-lg: a partir de 992px o menu é uma barra fixa; abaixo disso vira uma gaveta que abre pelo
             botão da barra superior (Bootstrap cuida do foco e do fundo escurecido). O visual vem do app.css. */ ?>
    <aside class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="menuLateral" aria-label="Menu principal">
        <div class="sidebar-topo">
            <a class="marca" href="<?= e(url('dashboard/index')) ?>">
                <span class="marca-icone"><?= icone('activity') ?></span>
                <span class="marca-nome">FiscalPulse</span>
            </a>
            <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#menuLateral" aria-label="Fechar menu"></button>
        </div>

        <nav class="sidebar-nav" aria-label="Navegação">
            <ul class="nav flex-column gap-1">
                <?php foreach ($itens as [$rotulo, $icone, $destino, $prefixos]) : ?>
                    <?php /* aria-current="page" no item ativo: além da cor, o leitor de tela anuncia onde a pessoa está. */ ?>
                    <?php $ativo = $estaAtivo($prefixos); ?>
                    <li class="nav-item">
                        <a class="<?= e('nav-link' . ($ativo ? ' active' : '')) ?>" href="<?= e($destino) ?>" aria-current="<?= e($ativo ? 'page' : 'false') ?>">
                            <?= icone($icone) ?><span class="nav-rotulo"><?= e($rotulo) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="sidebar-rodape">
            <button class="btn btn-avaliar" type="button" data-bs-toggle="modal" data-bs-target="#modalAvaliacao">
                <?= icone('chat-heart') ?>Avaliar o sistema
            </button>
            <div class="usuario-card">
                <?php /* A inicial é só decoração (aria-hidden); o nome completo vem logo ao lado, escapado por e(). */ ?>
                <div class="usuario-avatar" aria-hidden="true"><?= e(iniciais($usuario['nome'])) ?></div>
                <div class="usuario-dados">
                    <div class="usuario-nome"><?= e($usuario['nome']) ?></div>
                    <div class="usuario-perfil"><?= e($usuario['perfil'] === 'contador' ? 'Contador' : 'Cliente') ?></div>
                </div>
            </div>
            <?php /* Sair é POST com token CSRF (e não um link): um site malicioso não consegue deslogar a pessoa. */ ?>
            <form method="post" action="<?= e(url('auth/logout')) ?>" class="m-0">
                <?= Csrf::campo() ?>
                <button class="btn btn-sair" type="submit"><?= icone('box-arrow-right') ?>Sair</button>
            </form>
        </div>
    </aside>

    <div class="app-main">
        <header class="topbar d-lg-none">
            <button class="btn-menu" type="button" data-bs-toggle="offcanvas" data-bs-target="#menuLateral" aria-controls="menuLateral" aria-label="Abrir menu"><?= icone('list') ?></button>
            <span class="topbar-marca"><?= icone('activity') ?>FiscalPulse</span>
        </header>
        <main class="conteudo">
<?php else : ?>
<main class="auth-wrap">
    <?php /* "auth" => "larga" (passado pelo controller) alarga a caixa, para o formulário de cadastro. */ ?>
    <div class="<?= e('auth-caixa' . (($auth ?? '') === 'larga' ? ' auth-caixa-larga' : '')) ?>">
<?php endif; ?>
            <?php foreach ($mensagens as $msg) : ?>
                <div class="<?= e('alert alert-dismissible ' . ($msg['tipo'] === 'sucesso' ? 'alert-success' : 'alert-danger')) ?>" role="alert">
                    <?= e($msg['mensagem']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            <?php endforeach; ?>
            <?= $conteudo /* já escapado pela view que o gerou */ ?>
<?php if ($usuario !== null) : ?>
        </main>
        <footer class="rodape">FiscalPulse · MVP acadêmico</footer>
    </div>
</div>
<?php else : ?>
    </div>
</main>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<?php if ($usuario !== null) : ?>
<?php View::parcial('partials/avaliacao_modal'); ?>
<script src="assets/js/avaliacao.js"></script>
<?php endif; ?>
<script src="assets/js/app.js"></script>
<?php foreach (($scripts ?? []) as $src) : ?>
<script src="<?= e($src) ?>"></script>
<?php endforeach; ?>
</body>
</html>

<?php /** @var string $conteudo */ ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($titulo ?? 'Início') . ' · FiscalPulse') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= e(url('dashboard/index')) ?>">FiscalPulse</a>
        <?php if ($logado = \App\Core\Auth::usuario()) : ?>
            <?php if ($logado['perfil'] === 'contador') : ?>
                <a class="nav-link text-light ms-3" href="<?= e(url('empresas/index')) ?>">Empresas</a>
                <a class="nav-link text-light me-auto ms-3" href="<?= e(url('avaliacoes/index')) ?>">Avaliações</a>
            <?php endif; ?>
            <form method="post" action="<?= e(url('auth/logout')) ?>" class="d-flex align-items-center gap-3 m-0">
                <button class="btn btn-warning btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#modalAvaliacao">Avaliar o sistema</button>
                <span class="text-light small"><?= e($logado['nome']) ?></span>
                <?= \App\Core\Csrf::campo() ?>
                <button class="btn btn-outline-light btn-sm" type="submit">Sair</button>
            </form>
        <?php endif; ?>
    </div>
</nav>
<main class="container">
    <?php foreach (\App\Core\Flash::obter() as $msg) : ?>
        <div class="alert alert-<?= $msg['tipo'] === 'sucesso' ? 'success' : 'danger' ?> alert-dismissible" role="alert">
            <?= e($msg['mensagem']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endforeach; ?>
    <?= $conteudo /* já escapado pela view que o gerou */ ?>
</main>
<footer class="container text-center text-muted small py-4">FiscalPulse · MVP acadêmico</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<?php if ($logado) : ?>
<?php \App\Core\View::parcial('partials/avaliacao_modal'); ?>
<script src="assets/js/avaliacao.js"></script>
<?php endif; ?>
<script src="assets/js/app.js"></script>
<?php foreach (($scripts ?? []) as $src) : ?>
<script src="<?= e($src) ?>"></script>
<?php endforeach; ?>
</body>
</html>

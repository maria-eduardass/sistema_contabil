<?php
use App\Core\Csrf;

/** @var ?string $erro @var string $email */
?>
<div class="auth-marca">
    <span class="marca-icone"><?= icone('activity') ?></span>
    <span class="marca-nome">FiscalPulse</span>
</div>
<div class="card">
    <div class="card-body p-4">
        <h1 class="h4 mb-1">Entrar</h1>
        <p class="text-muted mb-4">Gestão contábil e termômetro de risco fiscal.</p>
        <?php if ($erro !== null) : ?>
            <div class="alert alert-danger py-2 d-flex align-items-center gap-2" role="alert"><?= icone('exclamation-triangle-fill') ?><span><?= e($erro) ?></span></div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('auth/login')) ?>" novalidate>
            <?= Csrf::campo() ?>
            <div class="mb-3">
                <label class="form-label" for="email">E-mail</label>
                <input class="form-control form-control-lg" type="email" id="email" name="email" maxlength="150"
                       value="<?= e($email) ?>" autocomplete="username" required autofocus>
            </div>
            <div class="mb-4">
                <label class="form-label" for="senha">Senha</label>
                <input class="form-control form-control-lg" type="password" id="senha" name="senha" maxlength="200"
                       autocomplete="current-password" required>
            </div>
            <button class="btn btn-primary btn-lg w-100" type="submit"><?= icone('box-arrow-in-right') ?> Entrar</button>
        </form>
    </div>
</div>

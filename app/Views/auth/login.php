<?php
use App\Core\Csrf;

/** @var ?string $erro @var string $email */
?>
<div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-3">Entrar no FiscalPulse</h1>
                <?php if ($erro !== null) : ?>
                    <div class="alert alert-danger py-2" role="alert"><?= e($erro) ?></div>
                <?php endif; ?>
                <form method="post" action="<?= e(url('auth/login')) ?>" novalidate>
                    <?= Csrf::campo() ?>
                    <div class="mb-3">
                        <label class="form-label" for="email">E-mail</label>
                        <input class="form-control" type="email" id="email" name="email" maxlength="150"
                               value="<?= e($email) ?>" autocomplete="username" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="senha">Senha</label>
                        <input class="form-control" type="password" id="senha" name="senha" maxlength="200"
                               autocomplete="current-password" required>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Entrar</button>
                </form>
            </div>
        </div>
    </div>
</div>

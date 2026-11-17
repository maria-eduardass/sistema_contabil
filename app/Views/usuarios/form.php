<?php
use App\Core\Csrf;

/** @var array<string,mixed> $empresa @var array<string,string> $valores @var array<string,string> $erros */
$classe = static fn (string $campo): string => 'form-control' . (isset($erros[$campo]) ? ' is-invalid' : '');
$erro = static fn (string $campo): string => isset($erros[$campo])
    ? '<div class="invalid-feedback">' . e($erros[$campo]) . '</div>' : '';
?>
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-1">Novo usuário cliente</h1>
                <p class="text-muted">Empresa: <strong><?= e($empresa['razao_social']) ?></strong></p>
                <form method="post" action="<?= e(url('usuarios/salvar')) ?>" novalidate>
                    <?= Csrf::campo() ?>
                    <input type="hidden" name="empresa_id" value="<?= (int) $empresa['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label" for="nome">Nome</label>
                        <input class="<?= $classe('nome') ?>" id="nome" name="nome" maxlength="120"
                               value="<?= e($valores['nome']) ?>" autocomplete="off" required>
                        <?= $erro('nome') ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="email">E-mail (será o login)</label>
                        <input class="<?= $classe('email') ?>" type="email" id="email" name="email" maxlength="150"
                               value="<?= e($valores['email']) ?>" autocomplete="off" required>
                        <?= $erro('email') ?>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="senha">Senha inicial</label>
                        <input class="<?= $classe('senha') ?>" type="password" id="senha" name="senha" maxlength="72"
                               autocomplete="new-password" required>
                        <div class="form-text">Mínimo de 8 caracteres, com letras e números. Informe-a ao cliente com segurança.</div>
                        <?= $erro('senha') ?>
                    </div>
                    <button class="btn btn-primary" type="submit">Cadastrar</button>
                    <a class="btn btn-link" href="<?= e(url('empresas/ver', ['id' => (int) $empresa['id']])) ?>">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</div>

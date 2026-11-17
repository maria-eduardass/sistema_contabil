<?php
use App\Core\Csrf;

/**
 * Tela pública "Criar conta". As senhas nunca voltam preenchidas depois de um erro; os demais valores
 * voltam escapados por e(). O erro "geral" cobre o bloqueio por excesso de tentativas.
 *
 * @var array<string,string> $valores  nome, email, razao_social, cnpj, regime_tributario
 * @var array<string,string> $erros    por campo (+ "geral")
 * @var array<string,string> $regimes  chave => rótulo
 */
$classe = static fn (string $campo, string $base = 'form-control'): string => $base . (isset($erros[$campo]) ? ' is-invalid' : '');
$erro = static fn (string $campo): string => isset($erros[$campo])
    ? '<div class="invalid-feedback">' . e($erros[$campo]) . '</div>' : '';
?>
<div class="auth-marca">
    <span class="marca-icone"><?= icone('activity') ?></span>
    <span class="marca-nome">FiscalPulse</span>
</div>
<div class="card">
    <div class="card-body p-4">
        <h1 class="h4 mb-1">Criar conta</h1>
        <p class="text-muted mb-4">Cadastre a sua empresa e o seu acesso para acompanhar o risco fiscal.</p>

        <?php if (isset($erros['geral'])) : ?>
            <div class="alert alert-danger py-2 d-flex align-items-center gap-2" role="alert"><?= icone('exclamation-triangle-fill') ?><span><?= e($erros['geral']) ?></span></div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('cadastro/salvar')) ?>" novalidate>
            <?= Csrf::campo() ?>

            <h2 class="h6 text-uppercase text-muted mb-3"><?= icone('person-circle') ?> Seus dados</h2>
            <div class="mb-3">
                <label class="form-label" for="nome">Nome</label>
                <input class="<?= $classe('nome') ?>" id="nome" name="nome" maxlength="120" value="<?= e($valores['nome']) ?>" autocomplete="name" required autofocus>
                <?= $erro('nome') ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="email">E-mail (será o seu login)</label>
                <input class="<?= $classe('email') ?>" type="email" id="email" name="email" maxlength="150" value="<?= e($valores['email']) ?>" autocomplete="username" required>
                <?= $erro('email') ?>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="senha">Senha</label>
                    <input class="<?= $classe('senha') ?>" type="password" id="senha" name="senha" maxlength="72" autocomplete="new-password" required>
                    <div class="form-text">Mínimo de 8 caracteres, com letras e números.</div>
                    <?= $erro('senha') ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="senha_confirmacao">Confirme a senha</label>
                    <input class="<?= $classe('senha_confirmacao') ?>" type="password" id="senha_confirmacao" name="senha_confirmacao" maxlength="72" autocomplete="new-password" required>
                    <?= $erro('senha_confirmacao') ?>
                </div>
            </div>

            <h2 class="h6 text-uppercase text-muted mt-2 mb-3"><?= icone('buildings') ?> Sua empresa</h2>
            <div class="mb-3">
                <label class="form-label" for="razao_social">Razão social</label>
                <input class="<?= $classe('razao_social') ?>" id="razao_social" name="razao_social" maxlength="150" value="<?= e($valores['razao_social']) ?>" required>
                <?= $erro('razao_social') ?>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="cnpj">CNPJ</label>
                    <input class="<?= $classe('cnpj') ?>" id="cnpj" name="cnpj" maxlength="18" inputmode="numeric" placeholder="00.000.000/0000-00" value="<?= e($valores['cnpj']) ?>" required>
                    <?= $erro('cnpj') ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="regime_tributario">Regime tributário</label>
                    <select class="<?= $classe('regime_tributario', 'form-select') ?>" id="regime_tributario" name="regime_tributario" required>
                        <option value="">Selecione…</option>
                        <?php foreach ($regimes as $chave => $rotulo) : ?>
                            <option value="<?= e($chave) ?>" <?= $valores['regime_tributario'] === $chave ? 'selected' : '' ?>><?= e($rotulo) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $erro('regime_tributario') ?>
                </div>
            </div>

            <button class="btn btn-primary btn-lg w-100 mt-2" type="submit"><?= icone('person-plus') ?> Criar conta</button>
        </form>

        <div class="auth-divisor"><span>Já tem uma conta?</span></div>
        <a class="btn btn-outline-secondary w-100" href="<?= e(url('auth/login')) ?>"><?= icone('box-arrow-in-right') ?> Voltar para entrar</a>
    </div>
</div>

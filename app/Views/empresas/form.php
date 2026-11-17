<?php
use App\Core\Csrf;

/**
 * @var string $acao @var array<string,string> $valores @var array<string,string> $erros
 * @var ?int $id @var array<string,string> $regimes
 */
$classe = static fn (string $campo): string => 'form-control' . (isset($erros[$campo]) ? ' is-invalid' : '');
$erro = static fn (string $campo): string => isset($erros[$campo])
    ? '<div class="invalid-feedback">' . e($erros[$campo]) . '</div>' : '';
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-3"><?= e($titulo) ?></h1>
                <form method="post" action="<?= e($acao) ?>" novalidate>
                    <?= Csrf::campo() ?>
                    <?php if ($id !== null) : ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label" for="razao_social">Razão social</label>
                        <input class="<?= $classe('razao_social') ?>" id="razao_social" name="razao_social" maxlength="150"
                               value="<?= e($valores['razao_social']) ?>" required>
                        <?= $erro('razao_social') ?>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="cnpj">CNPJ</label>
                            <input class="<?= $classe('cnpj') ?>" id="cnpj" name="cnpj" maxlength="18" inputmode="numeric"
                                   placeholder="00.000.000/0000-00" value="<?= e($valores['cnpj']) ?>" required>
                            <?= $erro('cnpj') ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="regime_tributario">Regime tributário</label>
                            <select class="<?= str_replace('form-control', 'form-select', $classe('regime_tributario')) ?>"
                                    id="regime_tributario" name="regime_tributario" required>
                                <option value="">Selecione…</option>
                                <?php foreach ($regimes as $chave => $rotulo) : ?>
                                    <option value="<?= e($chave) ?>" <?= $valores['regime_tributario'] === $chave ? 'selected' : '' ?>><?= e($rotulo) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= $erro('regime_tributario') ?>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="email_contato">E-mail de contato</label>
                        <input class="<?= $classe('email_contato') ?>" type="email" id="email_contato" name="email_contato"
                               maxlength="150" value="<?= e($valores['email_contato']) ?>" required>
                        <?= $erro('email_contato') ?>
                    </div>
                    <button class="btn btn-primary" type="submit">Salvar</button>
                    <a class="btn btn-link" href="<?= e(url('empresas/index')) ?>">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</div>

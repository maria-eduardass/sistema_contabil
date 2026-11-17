<?php
use App\Core\Csrf;

/**
 * @var array<string,mixed> $empresa @var array<string,string> $valores @var array<string,string> $erros
 * @var list<array<string,mixed>> $tipos
 */
$classe = static fn (string $campo, string $base = 'form-control'): string => $base . (isset($erros[$campo]) ? ' is-invalid' : '');
$erro = static fn (string $campo): string => isset($erros[$campo])
    ? '<div class="invalid-feedback">' . e($erros[$campo]) . '</div>' : '';
?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-1">Nova obrigação</h1>
                <p class="text-muted">Empresa: <strong><?= e($empresa['razao_social']) ?></strong></p>
                <form method="post" action="<?= e(url('obrigacoes/salvar')) ?>" novalidate>
                    <?= Csrf::campo() ?>
                    <input type="hidden" name="empresa_id" value="<?= (int) $empresa['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label" for="tipo_obrigacao_id">Tipo de obrigação</label>
                        <select class="<?= $classe('tipo_obrigacao_id', 'form-select') ?>" id="tipo_obrigacao_id" name="tipo_obrigacao_id" required>
                            <option value="">Selecione…</option>
                            <?php foreach ($tipos as $t) : ?>
                                <option value="<?= (int) $t['id'] ?>" <?= $valores['tipo_obrigacao_id'] === (string) $t['id'] ? 'selected' : '' ?>>
                                    <?= e($t['sigla'] . ' — ' . $t['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?= $erro('tipo_obrigacao_id') ?>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="competencia">Competência</label>
                            <input class="<?= $classe('competencia') ?>" type="month" id="competencia" name="competencia"
                                   value="<?= e($valores['competencia']) ?>" required>
                            <?= $erro('competencia') ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="data_vencimento">Vencimento</label>
                            <input class="<?= $classe('data_vencimento') ?>" type="date" id="data_vencimento" name="data_vencimento"
                                   value="<?= e($valores['data_vencimento']) ?>" required>
                            <?= $erro('data_vencimento') ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="valor">Valor (R$)</label>
                        <input class="<?= $classe('valor') ?>" id="valor" name="valor" inputmode="decimal" maxlength="14"
                               placeholder="0,00" value="<?= e($valores['valor']) ?>">
                        <div class="form-text">Formato 1.234,56. Deixe em branco para declarações sem valor a pagar.</div>
                        <?= $erro('valor') ?>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="observacao">Observação (opcional)</label>
                        <input class="<?= $classe('observacao') ?>" id="observacao" name="observacao" maxlength="255"
                               value="<?= e($valores['observacao']) ?>">
                        <?= $erro('observacao') ?>
                    </div>
                    <button class="btn btn-primary" type="submit">Salvar</button>
                    <a class="btn btn-link" href="<?= e(url('obrigacoes/index', ['empresa_id' => (int) $empresa['id']])) ?>">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</div>

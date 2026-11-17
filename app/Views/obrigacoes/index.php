<?php
use App\Core\Csrf;

/**
 * @var array<string,mixed> $empresa @var list<array<string,mixed>> $obrigacoes @var bool $ehContador
 */
$hoje = date('Y-m-d');
$selo = ['pendente' => ['warning', 'Pendente'], 'atrasado' => ['danger', 'Atrasada'], 'pago' => ['success', 'Paga']];
?>
<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h1 class="h3 m-0">Obrigações</h1>
        <div class="text-muted"><?= e($empresa['razao_social']) ?> · <?= e(formatarCnpj($empresa['cnpj'])) ?></div>
    </div>
    <?php if ($ehContador && (int) $empresa['ativa'] === 1) : ?>
        <a class="btn btn-primary" href="<?= e(url('obrigacoes/criar', ['empresa_id' => (int) $empresa['id']])) ?>">Nova obrigação</a>
    <?php endif; ?>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Obrigação</th><th>Competência</th><th>Vencimento</th><th class="text-end">Valor</th><th>Situação</th><th>Observação</th><?php if ($ehContador) : ?><th class="text-end">Ações</th><?php endif; ?></tr>
            </thead>
            <tbody>
            <?php foreach ($obrigacoes as $o) : ?>
                <?php [$cor, $rotulo] = $selo[$o['status']] ?? ['secondary', $o['status']]; ?>
                <tr>
                    <td><strong><?= e($o['tipo_sigla']) ?></strong><div class="small text-muted"><?= e($o['tipo_nome']) ?></div></td>
                    <td><?= e(formatarCompetencia($o['competencia'])) ?></td>
                    <td><?= e(formatarData($o['data_vencimento'])) ?></td>
                    <td class="text-end text-nowrap"><?= e(formatarCentavos((int) $o['valor_centavos'])) ?></td>
                    <td>
                        <span class="badge text-bg-<?= e($cor) ?>"><?= e($rotulo) ?></span>
                        <?php if ($o['data_pagamento'] !== null) : ?>
                            <div class="small text-muted">em <?= e(formatarData($o['data_pagamento'])) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="small"><?= e($o['observacao']) ?></td>
                    <?php if ($ehContador) : ?>
                        <td class="text-end text-nowrap">
                            <?php if ($o['status'] !== 'pago') : ?>
                                <form class="d-inline-flex gap-1 align-items-center" method="post" action="<?= e(url('obrigacoes/pagar')) ?>">
                                    <?= Csrf::campo() ?>
                                    <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                                    <input class="form-control form-control-sm" type="date" name="data_pagamento"
                                           value="<?= e($hoje) ?>" max="<?= e($hoje) ?>" aria-label="Data do pagamento" required>
                                    <button class="btn btn-sm btn-success" type="submit">Pagar</button>
                                </form>
                                <form class="d-inline" method="post" action="<?= e(url('obrigacoes/excluir')) ?>"
                                      data-confirmar="Excluir esta obrigação? Essa ação não pode ser desfeita.">
                                    <?= Csrf::campo() ?>
                                    <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Excluir</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if ($obrigacoes === []) : ?>
                <tr><td colspan="<?= $ehContador ? 7 : 6 ?>" class="text-center text-muted py-4">Nenhuma obrigação cadastrada.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<p class="mt-3"><a href="<?= e(url('empresas/ver', ['id' => (int) $empresa['id']])) ?>">&larr; Voltar à empresa</a></p>

<?php
use App\Core\Csrf;

/**
 * @var array<string,mixed> $empresa @var list<array<string,mixed>> $obrigacoes @var bool $ehContador
 * Para o contador cada linha traz também link_whatsapp e valor_atualizado_centavos (MensagemCobranca).
 */
$hoje = date('Y-m-d');
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <h1 class="h3 m-0">Obrigações</h1>
        <div class="text-muted"><?= e($empresa['razao_social']) ?> · <?= e(formatarCnpj($empresa['cnpj'])) ?></div>
    </div>
    <?php if ($ehContador && (int) $empresa['ativa'] === 1) : ?>
        <a class="btn btn-primary" href="<?= e(url('obrigacoes/criar', ['empresa_id' => (int) $empresa['id']])) ?>"><?= icone('plus-lg') ?> Nova obrigação</a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <caption class="visually-hidden">Obrigações fiscais da empresa, com situação e valor</caption>
            <thead>
                <tr><th>Obrigação</th><th>Competência</th><th>Vencimento</th><th class="text-end">Valor</th><th>Situação</th><?php if ($ehContador) : ?><th class="text-end">Ações</th><?php endif; ?></tr>
            </thead>
            <tbody>
            <?php foreach ($obrigacoes as $o) : ?>
                <tr>
                    <td>
                        <strong><?= e($o['tipo_sigla']) ?></strong>
                        <div class="small text-muted"><?= e($o['tipo_nome']) ?></div>
                        <?php if ($o['observacao'] !== null) : ?>
                            <div class="small fst-italic mt-1"><?= icone('chat-left-text') ?> <?= e($o['observacao']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= e(formatarCompetencia($o['competencia'])) ?></td>
                    <td><?= e(formatarData($o['data_vencimento'])) ?></td>
                    <td class="text-end text-nowrap">
                        <?= e(formatarCentavos((int) $o['valor_centavos'])) ?>
                        <?php if (($o['valor_atualizado_centavos'] ?? null) !== null) : ?>
                            <div class="small text-muted">com multa e juros: <strong><?= e(formatarCentavos((int) $o['valor_atualizado_centavos'])) ?></strong></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= seloStatus((string) $o['status']) ?>
                        <?php if ($o['data_pagamento'] !== null) : ?>
                            <div class="small text-muted mt-1">em <?= e(formatarData($o['data_pagamento'])) ?></div>
                        <?php endif; ?>
                    </td>
                    <?php if ($ehContador) : ?>
                        <td>
                            <?php if ($o['status'] !== 'pago') : ?>
                                <div class="acoes-coluna">
                                    <div class="acoes-par">
                                        <?php if (($o['link_whatsapp'] ?? null) !== null) : ?>
                                            <a class="btn btn-sm btn-whatsapp flex-grow-1 justify-content-center" href="<?= e($o['link_whatsapp']) ?>" target="_blank" rel="noopener noreferrer"
                                               aria-label="Cobrar a guia <?= e($o['tipo_sigla']) ?> no WhatsApp (abre em nova aba)"><?= icone('whatsapp') ?>Cobrar no WhatsApp</a>
                                        <?php endif; ?>
                                        <form class="d-inline" method="post" action="<?= e(url('obrigacoes/excluir')) ?>"
                                              data-confirmar="Excluir esta obrigação? Essa ação não pode ser desfeita.">
                                            <?= Csrf::campo() ?>
                                            <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Excluir a guia <?= e($o['tipo_sigla']) ?>"><?= icone('trash') ?></button>
                                        </form>
                                    </div>
                                    <form class="acoes-par" method="post" action="<?= e(url('obrigacoes/pagar')) ?>">
                                        <?= Csrf::campo() ?>
                                        <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                                        <input class="form-control form-control-sm" type="date" name="data_pagamento"
                                               value="<?= e($hoje) ?>" max="<?= e($hoje) ?>" aria-label="Data do pagamento" required>
                                        <button class="btn btn-sm btn-success" type="submit"><?= icone('check-lg') ?> Pagar</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if ($obrigacoes === []) : ?>
                <tr><td colspan="<?= $ehContador ? 6 : 5 ?>" class="text-center text-muted py-4">Nenhuma obrigação cadastrada.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<p class="mt-3"><a href="<?= e(url('empresas/ver', ['id' => (int) $empresa['id']])) ?>"><?= icone('arrow-left') ?> Voltar à empresa</a></p>

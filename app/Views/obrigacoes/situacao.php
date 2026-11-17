<?php
/**
 * Lista só de guias pagas ou só das em aberto (opção do menu lateral). Somente leitura: pagar e excluir
 * continuam na lista da empresa.
 *
 * @var bool $pagas
 * @var list<array<string,mixed>> $obrigacoes
 */
?>
<h1 class="h3 mb-4"><?= e($titulo) ?></h1>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <caption class="visually-hidden">Obrigações fiscais</caption>
            <thead>
                <tr><th>Empresa</th><th>Obrigação</th><th>Competência</th><th>Vencimento</th><th class="text-end">Valor</th><th>Situação</th></tr>
            </thead>
            <tbody>
            <?php foreach ($obrigacoes as $o) : ?>
                <tr>
                    <td><a href="<?= e(url('obrigacoes/index', ['empresa_id' => (int) $o['empresa_id']])) ?>"><?= e($o['razao_social']) ?></a></td>
                    <td><strong><?= e($o['tipo_sigla']) ?></strong></td>
                    <td><?= e(formatarData($o['competencia'])) ?></td>
                    <td><?= e(formatarData($o['data_vencimento'])) ?></td>
                    <td class="text-end text-nowrap"><?= e(formatarCentavos((int) $o['valor_centavos'])) ?></td>
                    <td>
                        <?= seloStatus((string) $o['status']) ?>
                        <?php if ($o['data_pagamento'] !== null) : ?>
                            <div class="small text-muted mt-1">em <?= e(formatarData($o['data_pagamento'])) ?></div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($obrigacoes === []) : ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Nenhuma obrigação aqui.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

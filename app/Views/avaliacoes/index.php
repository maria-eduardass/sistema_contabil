<?php
/**
 * @var array{total:int,media:?float,distribuicao:array<int,int>,maximo:int} $resumo
 * @var list<array<string,mixed>> $avaliacoes
 */
?>
<h1 class="h3 mb-3">Avaliações do sistema</h1>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-muted small">Nota média</div>
                <div class="display-5 fw-bold"><?= $resumo['media'] === null ? '—' : e(number_format($resumo['media'], 1, ',', '')) ?><span class="fs-5 text-muted"> / 5</span></div>
                <div class="text-muted small"><?= (int) $resumo['total'] ?> <?= $resumo['total'] === 1 ? 'avaliação' : 'avaliações' ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small mb-2">Distribuição das notas</div>
                <?php for ($nota = 5; $nota >= 1; $nota--) : ?>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="text-nowrap"><?= $nota ?> ★</span>
                        <progress class="barra-nota" max="<?= max(1, (int) $resumo['maximo']) ?>" value="<?= (int) $resumo['distribuicao'][$nota] ?>"
                                  aria-label="<?= (int) $resumo['distribuicao'][$nota] ?> votos para a nota <?= $nota ?>"></progress>
                        <span class="text-muted small text-end votos"><?= (int) $resumo['distribuicao'][$nota] ?></span>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold">Últimas avaliações</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr><th>Data</th><th>Quem avaliou</th><th class="text-center">Nota</th><th>Comentário</th></tr>
            </thead>
            <tbody>
            <?php foreach ($avaliacoes as $a) : ?>
                <tr>
                    <td class="text-nowrap small"><?= e(formatarDataHora($a['criada_em'])) ?></td>
                    <td><?= e($a['nome']) ?> <span class="badge text-bg-light border"><?= e($a['perfil']) ?></span></td>
                    <td class="text-center"><span class="badge text-bg-warning fs-6"><?= (int) $a['nota'] ?></span></td>
                    <td class="texto-comentario"><?= $a['comentario'] === null ? '<span class="text-muted">—</span>' : e($a['comentario']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($avaliacoes === []) : ?>
                <tr><td colspan="4" class="text-center text-muted py-4">Ainda não há avaliações.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
/**
 * @var array{nome:string} $usuario
 * @var list<array<string,mixed>> $ranking
 * @var array{total_empresas:int,em_risco:int,atrasadas:int,multa_total_centavos:int} $resumo
 */
$cartoes = [
    ['Empresas ativas', (string) $resumo['total_empresas'], ''],
    ['Em risco alto ou crítico', (string) $resumo['em_risco'], $resumo['em_risco'] > 0 ? 'text-danger' : 'text-success'],
    ['Obrigações atrasadas', (string) $resumo['atrasadas'], $resumo['atrasadas'] > 0 ? 'text-danger' : 'text-success'],
    ['Multa estimada (total)', formatarCentavos($resumo['multa_total_centavos']), ''],
];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 m-0">Painel do contador</h1>
    <span class="text-muted small">Olá, <?= e($usuario['nome']) ?></span>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($cartoes as [$rotulo, $valor, $classe]) : ?>
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small"><?= e($rotulo) ?></div>
                    <div class="h4 m-0 <?= e($classe) ?>"><?= e($valor) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold">Ranking de risco fiscal</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>#</th><th>Empresa</th><th class="col-risco">Risco</th><th class="text-center">Atrasadas</th><th class="text-end">Multa estimada</th><th class="text-end">Ações</th></tr>
            </thead>
            <tbody>
            <?php foreach ($ranking as $posicao => $linha) : ?>
                <tr>
                    <td class="text-muted"><?= $posicao + 1 ?></td>
                    <td>
                        <a class="fw-semibold text-decoration-none" href="<?= e(url('empresas/ver', ['id' => $linha['id']])) ?>"><?= e($linha['razao_social']) ?></a>
                        <div class="small text-muted"><?= e(formatarCnpj($linha['cnpj'])) ?> · <?= (int) $linha['em_aberto'] ?> em aberto</div>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <progress class="barra-risco barra-<?= e($linha['faixa']) ?>" max="100" value="<?= (int) $linha['score'] ?>"
                                      aria-label="Risco de <?= (int) $linha['score'] ?> por cento"></progress>
                            <strong><?= (int) $linha['score'] ?>%</strong>
                        </div>
                        <span class="badge selo selo-<?= e($linha['faixa']) ?>"><?= e($linha['faixa_rotulo']) ?></span>
                    </td>
                    <td class="text-center"><?= (int) $linha['atrasadas'] ?></td>
                    <td class="text-end text-nowrap"><?= e(formatarCentavos($linha['multa_total_centavos'])) ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('empresas/ver', ['id' => $linha['id']])) ?>">Termômetro</a>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('obrigacoes/index', ['empresa_id' => $linha['id']])) ?>">Obrigações</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($ranking === []) : ?>
                <tr><td colspan="6" class="text-center text-muted py-4">
                    Nenhuma empresa ativa. <a href="<?= e(url('empresas/criar')) ?>">Cadastre a primeira.</a>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

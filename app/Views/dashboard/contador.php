<?php
use App\Core\View;

/**
 * @var array{nome:string} $usuario
 * @var list<array<string,mixed>> $ranking
 * @var array{total_empresas:int,em_risco:int,atrasadas:int,multa_total_centavos:int} $resumo
 * @var array<string,array> $financeiro
 * @var string $mes
 */
?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
    <div>
        <h1 class="h3 m-0">Painel do contador</h1>
        <div class="text-muted">Olá, <?= e($usuario['nome']) ?>. Veja o dinheiro em jogo e quem precisa de atenção primeiro.</div>
    </div>
    <a class="btn btn-primary" href="<?= e(url('empresas/criar')) ?>"><?= icone('plus-lg') ?> Nova empresa</a>
</div>

<?php View::parcial('partials/resumo_financeiro', ['financeiro' => $financeiro, 'mes' => $mes, 'destaque' => true]); ?>

<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span>Ranking de risco fiscal</span>
        <span class="small fw-normal text-muted">
            <?= e(plural($resumo['total_empresas'], 'empresa ativa', 'empresas ativas')) ?> ·
            <?= e((string) $resumo['em_risco']) ?> em risco alto ou crítico
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <caption class="visually-hidden">Empresas ordenadas do maior para o menor risco fiscal</caption>
            <thead>
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
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <progress class="barra-risco barra-<?= e($linha['faixa']) ?>" max="100" value="<?= (int) $linha['score'] ?>"
                                      aria-label="Risco de <?= (int) $linha['score'] ?> por cento"></progress>
                            <strong><?= (int) $linha['score'] ?>%</strong>
                        </div>
                        <?= seloFaixa($linha['faixa'], $linha['faixa_rotulo']) ?>
                    </td>
                    <td class="text-center"><?= (int) $linha['atrasadas'] ?></td>
                    <td class="text-end text-nowrap"><?= e(formatarCentavos($linha['multa_total_centavos'])) ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('empresas/ver', ['id' => $linha['id']])) ?>"><?= icone('speedometer2') ?> Termômetro</a>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('obrigacoes/index', ['empresa_id' => $linha['id']])) ?>"><?= icone('receipt-cutoff') ?> Obrigações</a>
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

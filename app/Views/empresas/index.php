<?php
use App\Core\Csrf;
use App\Services\ValidadorEmpresa;

/** @var list<array<string,mixed>> $empresas @var string $busca */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 m-0">Empresas</h1>
    <a class="btn btn-primary" href="<?= e(url('empresas/criar')) ?>"><?= icone('plus-lg') ?> Nova empresa</a>
</div>

<form class="row g-2 mb-3" method="get" action="index.php">
    <input type="hidden" name="rota" value="empresas/index">
    <div class="col-sm-8 col-md-6">
        <input class="form-control" type="search" name="busca" value="<?= e($busca) ?>" maxlength="100"
               placeholder="Buscar por razão social ou CNPJ" aria-label="Buscar">
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-secondary" type="submit">Buscar</button>
        <?php if ($busca !== '') : ?><a class="btn btn-link" href="<?= e(url('empresas/index')) ?>">Limpar</a><?php endif; ?>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Razão social</th><th>CNPJ</th><th>Regime</th><th>E-mail</th><th>Situação</th><th class="text-end">Ações</th></tr>
            </thead>
            <tbody>
            <?php foreach ($empresas as $emp) : ?>
                <tr>
                    <td><?= e($emp['razao_social']) ?></td>
                    <td class="text-nowrap"><?= e(formatarCnpj($emp['cnpj'])) ?></td>
                    <td><?= e(ValidadorEmpresa::REGIMES[$emp['regime_tributario']] ?? $emp['regime_tributario']) ?></td>
                    <td><?= e($emp['email_contato']) ?></td>
                    <td>
                        <?php /* Situação com ícone + rótulo (nunca só a cor). Inativa é neutra: a empresa continua no
                                 banco com todo o histórico; só deixa de entrar no ranking e nos totais. */ ?>
                        <?php if ((int) $emp['ativa'] === 1) : ?>
                            <span class="selo-status selo-status-pago"><?= icone('check-circle-fill') ?>Ativa</span>
                        <?php else : ?>
                            <span class="selo-status"><?= icone('dash-circle') ?>Inativa</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php /* acoes-linha quebra de linha quando falta espaço, em vez de empurrar os botões para fora
                                 da tabela (a lateral fixa reduziu a largura útil). */ ?>
                        <div class="acoes-linha">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('empresas/ver', ['id' => $emp['id']])) ?>"><?= icone('eye') ?> Ver</a>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('empresas/editar', ['id' => $emp['id']])) ?>"><?= icone('pencil-square') ?> Editar</a>
                            <?php if ((int) $emp['ativa'] === 1) : ?>
                                <form class="d-inline" method="post" action="<?= e(url('empresas/inativar')) ?>"
                                      data-confirmar="Inativar esta empresa? O histórico será mantido.">
                                    <?= Csrf::campo() ?>
                                    <input type="hidden" name="id" value="<?= (int) $emp['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><?= icone('slash-circle') ?> Inativar</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($empresas === []) : ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Nenhuma empresa encontrada.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

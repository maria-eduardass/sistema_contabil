<?php
use App\Services\ValidadorEmpresa;

/** @var array<string,mixed> $empresa @var bool $ehContador @var list<array<string,mixed>> $usuarios */
?>
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h1 class="h3"><?= e($empresa['razao_social']) ?></h1>
        <dl class="row mb-0">
            <dt class="col-sm-3">CNPJ</dt><dd class="col-sm-9"><?= e(formatarCnpj($empresa['cnpj'])) ?></dd>
            <dt class="col-sm-3">Regime</dt><dd class="col-sm-9"><?= e(ValidadorEmpresa::REGIMES[$empresa['regime_tributario']] ?? '') ?></dd>
            <dt class="col-sm-3">E-mail</dt><dd class="col-sm-9"><?= e($empresa['email_contato']) ?></dd>
            <dt class="col-sm-3">Situação</dt><dd class="col-sm-9"><?= (int) $empresa['ativa'] === 1 ? 'Ativa' : 'Inativa' ?></dd>
        </dl>
        <a class="btn btn-outline-primary btn-sm mt-3" href="<?= e(url('obrigacoes/index', ['empresa_id' => (int) $empresa['id']])) ?>">Ver obrigações</a>
    </div>
</div>

<?php App\Core\View::parcial('partials/termometro', ['empresaId' => (int) $empresa['id']]); ?>

<?php if ($ehContador) : ?>
<div class="card shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 m-0">Usuários com acesso</h2>
            <?php if ((int) $empresa['ativa'] === 1) : ?>
                <a class="btn btn-sm btn-primary" href="<?= e(url('usuarios/criar', ['empresa_id' => (int) $empresa['id']])) ?>">Novo usuário</a>
            <?php endif; ?>
        </div>
        <?php if ($usuarios === []) : ?>
            <p class="text-muted mb-0">Nenhum usuário cadastrado para esta empresa.</p>
        <?php else : ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($usuarios as $u) : ?>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span><?= e($u['nome']) ?> <span class="text-muted">· <?= e($u['email']) ?></span></span>
                        <?= (int) $u['ativo'] === 1 ? '' : '<span class="badge text-bg-secondary">Inativo</span>' ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

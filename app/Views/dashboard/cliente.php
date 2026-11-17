<?php
/**
 * @var array{nome:string,empresa_id:?int} $usuario
 * @var array<string,mixed>|null $empresa
 * @var list<array<string,mixed>> $proximas
 */
$situacao = static function (int $dias): array {
    return match (true) {
        $dias < 0 => ['text-danger fw-semibold', 'Atrasada há ' . -$dias . (-$dias === 1 ? ' dia' : ' dias')],
        $dias === 0 => ['text-warning-emphasis fw-semibold', 'Vence hoje'],
        default => ['text-body-secondary', 'Vence em ' . $dias . ($dias === 1 ? ' dia' : ' dias')],
    };
};
?>
<h1 class="h3 mb-1">Olá, <?= e($usuario['nome']) ?>!</h1>
<p class="text-muted"><?= $empresa !== null ? e($empresa['razao_social']) : '' ?></p>

<?php App\Core\View::parcial('partials/termometro', ['empresaId' => (int) $usuario['empresa_id']]); ?>

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Próximas obrigações</span>
        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('obrigacoes/index', ['empresa_id' => (int) $usuario['empresa_id']])) ?>">Ver todas</a>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr><th>Obrigação</th><th>Vencimento</th><th>Situação</th><th class="text-end">Valor</th></tr>
            </thead>
            <tbody>
            <?php foreach ($proximas as $o) : ?>
                <?php [$classe, $texto] = $situacao((int) $o['dias_para_vencer']); ?>
                <tr>
                    <td><strong><?= e($o['tipo_sigla']) ?></strong><div class="small text-muted"><?= e($o['tipo_nome'] ?? '') ?></div></td>
                    <td><?= e(formatarData($o['data_vencimento'])) ?></td>
                    <td class="<?= e($classe) ?>"><?= e($texto) ?></td>
                    <td class="text-end text-nowrap"><?= e(formatarCentavos((int) $o['valor_centavos'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($proximas === []) : ?>
                <tr><td colspan="4" class="text-center text-muted py-4">Você não tem obrigações em aberto. Tudo em dia!</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
use App\Core\View;

/**
 * @var array{nome:string,empresa_id:?int} $usuario
 * @var array<string,mixed>|null $empresa
 * @var list<array<string,mixed>> $proximas
 * @var array<string,array> $financeiro
 * @var string $mes
 */
$prazo = static function (int $dias): string {
    return match (true) {
        $dias < 0 => 'Atrasada há ' . -$dias . (-$dias === 1 ? ' dia' : ' dias'),
        $dias === 0 => 'Vence hoje',
        default => 'Vence em ' . $dias . ($dias === 1 ? ' dia' : ' dias'),
    };
};
?>
<div class="mb-4">
    <h1 class="h3 m-0">Olá, <?= e($usuario['nome']) ?>!</h1>
    <div class="text-muted"><?= $empresa !== null ? e($empresa['razao_social']) : '' ?></div>
</div>

<?php /* Sem destaque nos cartões: neste painel o número que lidera a tela é o percentual do velocímetro
         (só pode haver um por tela). O mesmo ResumoFinanceiro alimenta o painel do contador. */ ?>
<?php View::parcial('partials/resumo_financeiro', ['financeiro' => $financeiro, 'mes' => $mes, 'destaque' => false]); ?>

<?php View::parcial('partials/termometro', ['empresaId' => (int) $usuario['empresa_id']]); ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Próximas obrigações</span>
        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('obrigacoes/index', ['empresa_id' => (int) $usuario['empresa_id']])) ?>">Ver todas</a>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <caption class="visually-hidden">Guias em aberto, da mais urgente para a mais distante</caption>
            <thead>
                <tr><th>Obrigação</th><th>Vencimento</th><th>Situação</th><th class="text-end">Valor</th></tr>
            </thead>
            <tbody>
            <?php foreach ($proximas as $o) : ?>
                <tr>
                    <td><strong><?= e($o['tipo_sigla']) ?></strong><div class="small text-muted"><?= e($o['tipo_nome'] ?? '') ?></div></td>
                    <td><?= e(formatarData($o['data_vencimento'])) ?></td>
                    <?php /* Selo (estado) + frase de prazo (quantos dias): o selo diz "o que é", o texto diz "há quanto tempo". */ ?>
                    <td><?= seloStatus((string) $o['status']) ?><div class="small text-muted mt-1"><?= e($prazo((int) $o['dias_para_vencer'])) ?></div></td>
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

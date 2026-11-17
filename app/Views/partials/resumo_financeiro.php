<?php
use App\Core\View;

/**
 * Faixa de cartões com o dinheiro em jogo: em atraso, a vencer em 7 dias e pago no mês.
 *
 * @var array{em_atraso:array,a_vencer:array,pago_no_mes:array} $financeiro  de ResumoFinanceiro::calcular
 * @var string $mes      mês de referência (MM/AAAA)
 * @var bool|null $destaque  true: o "em atraso" é o número de destaque da tela (só um por tela)
 */
$atraso = $financeiro['em_atraso'];
$vencer = $financeiro['a_vencer'];
$pago = $financeiro['pago_no_mes'];
$destaque = $destaque ?? false;

$notaAtraso = $atraso['quantidade'] === 0
    ? 'Nenhuma guia em atraso'
    : plural($atraso['quantidade'], 'guia', 'guias') . ' · com multa e juros estimados: ' . formatarCentavos($atraso['total_atualizado_centavos']);
?>
<div class="row g-3 mb-4">
    <div class="<?= e($destaque ? 'col-12 col-lg-6' : 'col-12 col-md-4') ?>">
        <?php View::parcial('partials/metrica', [
            'rotulo' => 'Em atraso', 'valor' => formatarCentavos($atraso['valor_centavos']), 'nota' => $notaAtraso,
            'icone' => 'exclamation-triangle-fill', 'tom' => 'critico', 'hero' => $destaque,
        ]); ?>
    </div>
    <div class="<?= e($destaque ? 'col-6 col-lg-3' : 'col-6 col-md-4') ?>">
        <?php View::parcial('partials/metrica', [
            'rotulo' => 'A vencer em ' . $vencer['dias'] . ' dias', 'valor' => formatarCentavos($vencer['valor_centavos']),
            'nota' => $vencer['quantidade'] === 0 ? 'Nenhuma guia a vencer' : plural($vencer['quantidade'], 'guia', 'guias') . ' até a próxima semana',
            'icone' => 'calendar-event', 'tom' => 'alerta',
        ]); ?>
    </div>
    <div class="<?= e($destaque ? 'col-6 col-lg-3' : 'col-6 col-md-4') ?>">
        <?php View::parcial('partials/metrica', [
            'rotulo' => 'Pago no mês', 'valor' => formatarCentavos($pago['valor_centavos']),
            'nota' => $pago['quantidade'] === 0 ? 'Nenhum pagamento em ' . $mes : plural($pago['quantidade'], 'guia paga', 'guias pagas') . ' em ' . $mes,
            'icone' => 'check-circle-fill', 'tom' => 'bom',
        ]); ?>
    </div>
</div>

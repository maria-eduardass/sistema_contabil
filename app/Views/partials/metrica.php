<?php
/**
 * Cartão de métrica (KPI): rótulo em frase, valor grande, nota de apoio e um ícone com o tom do status.
 *
 * @var string $rotulo  sentença curta, sem dois-pontos
 * @var string $valor   já formatado (ex.: "R$ 2.470,50")
 * @var string $nota    contexto do número
 * @var string $icone   nome do Bootstrap Icons (sem o prefixo bi-)
 * @var string $tom     critico | alerta | bom | serio | neutro (cor do ícone; o texto continua em tinta)
 * @var bool|null $hero o número de destaque da tela (>= 48px); no máximo um por tela
 */
?>
<div class="<?= e('metrica metrica-' . $tom . (($hero ?? false) ? ' metrica-hero' : '')) ?>" role="group" aria-label="<?= e($rotulo) ?>">
    <div class="metrica-topo">
        <span class="metrica-icone"><?= icone($icone) ?></span>
        <span class="metrica-rotulo"><?= e($rotulo) ?></span>
    </div>
    <div class="metrica-valor"><?= e($valor) ?></div>
    <div class="metrica-nota"><?= e($nota) ?></div>
</div>

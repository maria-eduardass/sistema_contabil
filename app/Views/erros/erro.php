<?php /** @var int $codigo @var string $mensagem */ ?>
<div class="text-center py-5">
    <h1 class="display-3 fw-bold text-secondary"><?= (int) $codigo ?></h1>
    <p class="lead"><?= e($mensagem) ?></p>
    <a class="btn btn-primary" href="<?= e(url('inicio/index')) ?>">Voltar ao início</a>
</div>

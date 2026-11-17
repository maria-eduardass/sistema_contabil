<?php use App\Core\Csrf; ?>
<div class="modal fade" id="modalAvaliacao" tabindex="-1" aria-labelledby="tituloAvaliacao" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formAvaliacao" method="post" action="<?= e(url('avaliacoes/salvar')) ?>" novalidate>
                <?= Csrf::campo() ?>
                <div class="modal-header">
                    <h2 class="modal-title h5" id="tituloAvaliacao">Avalie o FiscalPulse</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="alert d-none" role="alert" data-avaliacao-alerta></div>

                    <fieldset class="mb-3">
                        <legend class="fs-6 fw-semibold">Que nota você dá ao sistema?</legend>
                        <div class="btn-group" role="group" aria-label="Nota de 1 a 5">
                            <?php for ($n = 1; $n <= 5; $n++) : ?>
                                <input class="btn-check" type="radio" name="nota" id="nota<?= $n ?>" value="<?= $n ?>" autocomplete="off">
                                <label class="btn btn-outline-warning px-3" for="nota<?= $n ?>"><?= $n ?></label>
                            <?php endfor; ?>
                        </div>
                        <div class="form-text">1 = muito ruim · 5 = excelente</div>
                        <div class="text-danger small" data-erro="nota"></div>
                    </fieldset>

                    <div>
                        <label class="form-label fw-semibold" for="comentarioAvaliacao">Comentário <span class="fw-normal text-muted">(opcional)</span></label>
                        <textarea class="form-control" id="comentarioAvaliacao" name="comentario" rows="3" maxlength="500"
                                  placeholder="O que ficou fácil? O que poderia melhorar?"></textarea>
                        <div class="text-danger small" data-erro="comentario"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link" data-bs-dismiss="modal">Fechar</button>
                    <button type="submit" class="btn btn-primary">Enviar avaliação</button>
                </div>
            </form>
        </div>
    </div>
</div>

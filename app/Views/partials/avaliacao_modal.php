<?php use App\Core\Csrf; ?>
<div class="modal fade" id="modalAvaliacao" tabindex="-1" aria-labelledby="tituloAvaliacao" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formAvaliacao" method="post" action="<?= e(url('avaliacoes/salvar')) ?>" novalidate>
                <?= Csrf::campo() ?>
                <div class="modal-header border-0 pb-0">
                    <h2 class="modal-title h5" id="tituloAvaliacao">Como foi sua experiência?</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Sua opinião ajuda a melhorar o FiscalPulse.</p>
                    <div class="alert d-none" role="alert" data-avaliacao-alerta></div>

                    <fieldset class="mb-4 text-center">
                        <legend class="visually-hidden">Nota de 1 a 5 estrelas</legend>
                        <?php /* Estrelas só com CSS: os botões ficam em ordem inversa (5 a 1) para o "~" pintar as menores. */ ?>
                        <div class="estrelas">
                            <?php for ($n = 5; $n >= 1; $n--) : ?>
                                <input class="estrela-radio" type="radio" name="nota" id="nota<?= $n ?>" value="<?= $n ?>" autocomplete="off">
                                <label class="estrela" for="nota<?= $n ?>" title="<?= $n ?> de 5"><?= icone('star-fill') ?><span class="visually-hidden"><?= $n ?> de 5</span></label>
                            <?php endfor; ?>
                        </div>
                        <div class="estrelas-texto" data-nota-texto aria-live="polite">Toque nas estrelas para avaliar</div>
                        <div class="text-danger small" data-erro="nota"></div>
                    </fieldset>

                    <div>
                        <label class="form-label fw-semibold" for="comentarioAvaliacao">Comentário <span class="fw-normal text-muted">(opcional)</span></label>
                        <textarea class="form-control" id="comentarioAvaliacao" name="comentario" rows="3" maxlength="500"
                                  placeholder="O que ficou fácil? O que poderia melhorar?"></textarea>
                        <div class="d-flex justify-content-between">
                            <div class="text-danger small" data-erro="comentario"></div>
                            <div class="small text-muted ms-auto"><span data-contador>0</span>/500</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-link" data-bs-dismiss="modal">Fechar</button>
                    <button type="submit" class="btn btn-primary"><?= icone('send') ?> Enviar avaliação</button>
                </div>
            </form>
        </div>
    </div>
</div>

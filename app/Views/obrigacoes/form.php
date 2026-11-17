<?php
use App\Core\Csrf;

/**
 * Nova obrigação, com a PRÉVIA do termômetro ao lado (assets/js/obrigacao-form.js).
 *
 * A prévia é um acréscimo: o formulário funciona do mesmo jeito sem JavaScript. Quando o JS está ativo, a cada mudança
 * de vencimento ou valor ele consulta termometro/simular (o servidor calcula com as regras reais) e mostra o termômetro
 * de hoje e o que ele passaria a ser com esta guia. Os atalhos de data servem para testar o efeito sem digitar.
 *
 * @var array<string,mixed> $empresa @var array<string,string> $valores @var array<string,string> $erros
 * @var list<array<string,mixed>> $tipos
 */
$classe = static fn (string $campo, string $base = 'form-control'): string => $base . (isset($erros[$campo]) ? ' is-invalid' : '');
$erro = static fn (string $campo): string => isset($erros[$campo])
    ? '<div class="invalid-feedback">' . e($erros[$campo]) . '</div>' : '';

// Atalhos: [dias a partir de hoje, rótulo]. Negativo = já vencida (para ver o termômetro ficar vermelho).
$atalhos = [[-15, '15 dias atrás'], [-1, 'Ontem'], [0, 'Hoje'], [7, 'Em 7 dias'], [30, 'Em 30 dias']];
?>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-1">Nova obrigação</h1>
                <p class="text-muted">Empresa: <strong><?= e($empresa['razao_social']) ?></strong></p>
                <?php /* data-simular-url: endereço da prévia, já com a empresa. O servidor confere de novo se a pessoa pode ver
                         esta empresa; o data-* é só o endereço, não dá permissão a ninguém. */ ?>
                <form method="post" action="<?= e(url('obrigacoes/salvar')) ?>" novalidate
                      data-simular-url="<?= e(url('termometro/simular', ['empresa_id' => (int) $empresa['id']])) ?>">
                    <?= Csrf::campo() ?>
                    <input type="hidden" name="empresa_id" value="<?= (int) $empresa['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label" for="tipo_obrigacao_id">Tipo de obrigação</label>
                        <select class="<?= $classe('tipo_obrigacao_id', 'form-select') ?>" id="tipo_obrigacao_id" name="tipo_obrigacao_id" required>
                            <option value="">Selecione…</option>
                            <?php foreach ($tipos as $t) : ?>
                                <option value="<?= (int) $t['id'] ?>" <?= $valores['tipo_obrigacao_id'] === (string) $t['id'] ? 'selected' : '' ?>>
                                    <?= e($t['sigla'] . ' — ' . $t['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?= $erro('tipo_obrigacao_id') ?>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="competencia">Competência</label>
                            <input class="<?= $classe('competencia') ?>" type="date" id="competencia" name="competencia"
                                   value="<?= e($valores['competencia']) ?>" required>
                            <?= $erro('competencia') ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="data_vencimento">Vencimento</label>
                            <input class="<?= $classe('data_vencimento') ?>" type="date" id="data_vencimento" name="data_vencimento"
                                   value="<?= e($valores['data_vencimento']) ?>" required>
                            <?= $erro('data_vencimento') ?>
                        </div>
                    </div>

                    <?php /* Atalhos de data (o JS preenche o vencimento e acerta a competência). Sem JS ficam sem efeito, e o campo de
                             data continua valendo; por isso são type="button" (nunca enviam o formulário). */ ?>
                    <div class="atalhos-data mb-3" role="group" aria-label="Atalhos para o vencimento">
                        <span class="small text-muted me-1">Atalhos:</span>
                        <?php foreach ($atalhos as [$dias, $rotulo]) : ?>
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-atalho="<?= (int) $dias ?>"><?= e($rotulo) ?></button>
                        <?php endforeach; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="valor">Valor (R$)</label>
                        <input class="<?= $classe('valor') ?>" id="valor" name="valor" inputmode="decimal" maxlength="14"
                               placeholder="0,00" value="<?= e($valores['valor']) ?>">
                        <div class="form-text">Formato 1.234,56. Deixe em branco para declarações sem valor a pagar.</div>
                        <?= $erro('valor') ?>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="observacao">Observação (opcional)</label>
                        <input class="<?= $classe('observacao') ?>" id="observacao" name="observacao" maxlength="255"
                               value="<?= e($valores['observacao']) ?>">
                        <?= $erro('observacao') ?>
                    </div>
                    <button class="btn btn-primary" type="submit"><?= icone('check-lg') ?> Salvar</button>
                    <a class="btn btn-link" href="<?= e(url('obrigacoes/index', ['empresa_id' => (int) $empresa['id']])) ?>">Cancelar</a>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <?php /* Prévia: o texto de ajuda aparece até o JS ter o que mostrar. aria-live avisa leitores de tela quando a prévia muda.
                 O conteúdo é montado pelo JS com textContent (nunca innerHTML). */ ?>
        <aside class="card simulacao" data-simulacao aria-live="polite" aria-label="Prévia do termômetro">
            <div class="card-body p-4">
                <h2 class="h6 mb-3"><?= icone('speedometer2') ?> Prévia do termômetro</h2>
                <p class="small text-muted mb-0" data-simulacao-ajuda>Escolha o vencimento (ou um atalho) para ver como esta guia muda o seu termômetro de risco fiscal.</p>
                <div data-simulacao-corpo hidden></div>
            </div>
        </aside>
    </div>
</div>

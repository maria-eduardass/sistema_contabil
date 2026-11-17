<?php
use App\Core\Csrf;
use App\Core\View;

/**
 * Lista de obrigações da empresa, com o TERMÔMETRO no topo: criar, pagar ou excluir uma guia aqui muda o ponteiro
 * logo ao voltar para esta tela, então a pessoa vê a ligação entre o que ela faz e o risco.
 *
 * @var array<string,mixed> $empresa
 * @var list<array<string,mixed>> $obrigacoes
 * @var bool $ehContador  true = contador: vê também a cobrança (WhatsApp) e o valor atualizado com multa
 * @var bool $podeAgir    true = pode criar, pagar e excluir (empresa ativa). Vale para o contador e para o dono da empresa;
 *                        a conferência de quem é o dono é feita no controller, antes desta tela existir.
 * Para o contador cada linha traz também link_whatsapp e valor_atualizado_centavos (MensagemCobranca).
 */
// Valor padrão e limite do campo de data do pagamento. O navegador só facilita: quem decide é o
// servidor (StatusService::pagar recusa data futura ou inválida).
$hoje = date('Y-m-d');
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <h1 class="h3 m-0">Obrigações</h1>
        <div class="text-muted"><?= e($empresa['razao_social']) ?> · <?= e(formatarCnpj($empresa['cnpj'])) ?></div>
    </div>
    <?php if ($podeAgir) : ?>
        <a class="btn btn-primary" href="<?= e(url('obrigacoes/criar', ['empresa_id' => (int) $empresa['id']])) ?>"><?= icone('plus-lg') ?> Nova obrigação</a>
    <?php endif; ?>
</div>

<?php View::parcial('partials/termometro', ['empresaId' => (int) $empresa['id']]); ?>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <caption class="visually-hidden">Obrigações fiscais da empresa, com situação e valor</caption>
            <thead>
                <tr><th>Obrigação</th><th>Competência</th><th>Vencimento</th><th class="text-end">Valor</th><th>Situação</th><?php if ($podeAgir) : ?><th class="text-end">Ações</th><?php endif; ?></tr>
            </thead>
            <tbody>
            <?php foreach ($obrigacoes as $o) : ?>
                <tr>
                    <td>
                        <strong><?= e($o['tipo_sigla']) ?></strong>
                        <div class="small text-muted"><?= e($o['tipo_nome']) ?></div>
                        <?php /* A observação mora aqui (e não numa coluna própria): a coluna quase sempre vazia
                                 roubava largura e empurrava os botões de ação para fora da tela. */ ?>
                        <?php if ($o['observacao'] !== null) : ?>
                            <div class="small fst-italic mt-1"><?= icone('chat-left-text') ?> <?= e($o['observacao']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= e(formatarData($o['competencia'])) ?></td>
                    <td><?= e(formatarData($o['data_vencimento'])) ?></td>
                    <td class="text-end text-nowrap">
                        <?= e(formatarCentavos((int) $o['valor_centavos'])) ?>
                        <?php /* valor_atualizado_centavos só existe para o contador e só para guia atrasada com valor
                                 (MensagemCobranca::enriquecerLista). Cliente e guias em dia não recebem a chave. */ ?>
                        <?php if (($o['valor_atualizado_centavos'] ?? null) !== null) : ?>
                            <div class="small text-muted">com multa e juros: <strong><?= e(formatarCentavos((int) $o['valor_atualizado_centavos'])) ?></strong></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= seloStatus((string) $o['status']) ?>
                        <?php if ($o['data_pagamento'] !== null) : ?>
                            <div class="small text-muted mt-1">em <?= e(formatarData($o['data_pagamento'])) ?></div>
                        <?php endif; ?>
                    </td>
                    <?php if ($podeAgir) : ?>
                        <td>
                            <?php /* Guia paga é histórico fiscal: não se paga de novo nem se exclui (o servidor também recusa). */ ?>
                            <?php if ($o['status'] !== 'pago') : ?>
                                <?php /* Ações em duas linhas compactas para caber ao lado das demais colunas:
                                         1) cobrar no WhatsApp (só contador) + excluir; 2) data do pagamento + pagar.
                                         Pagar e excluir são formulários separados (HTML não permite um dentro do outro),
                                         por isso ficam lado a lado, cada um com o seu token CSRF. */ ?>
                                <div class="acoes-coluna">
                                    <div class="acoes-par">
                                        <?php if (($o['link_whatsapp'] ?? null) !== null) : ?>
                                            <?php /* O link é wa.me com o texto já codificado (MensagemCobranca::linkWhatsApp).
                                                     Nova aba com noopener e noreferrer: a página aberta não ganha acesso a esta
                                                     (tabnabbing) nem recebe o endereço do sistema. O aria-label avisa quem usa
                                                     leitor de tela qual guia é e que abre em outra aba. */ ?>
                                            <a class="btn btn-sm btn-whatsapp flex-grow-1 justify-content-center" href="<?= e($o['link_whatsapp']) ?>" target="_blank" rel="noopener noreferrer"
                                               aria-label="Cobrar a guia <?= e($o['tipo_sigla']) ?> no WhatsApp (abre em nova aba)"><?= icone('whatsapp') ?>Cobrar no WhatsApp</a>
                                        <?php endif; ?>
                                        <form class="d-inline" method="post" action="<?= e(url('obrigacoes/excluir')) ?>"
                                              data-confirmar="Excluir esta obrigação? Essa ação não pode ser desfeita.">
                                            <?= Csrf::campo() ?>
                                            <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Excluir a guia <?= e($o['tipo_sigla']) ?>"><?= icone('trash') ?></button>
                                        </form>
                                    </div>
                                    <form class="acoes-par" method="post" action="<?= e(url('obrigacoes/pagar')) ?>">
                                        <?= Csrf::campo() ?>
                                        <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                                        <input class="form-control form-control-sm" type="date" name="data_pagamento"
                                               value="<?= e($hoje) ?>" max="<?= e($hoje) ?>" aria-label="Data do pagamento" required>
                                        <button class="btn btn-sm btn-success" type="submit"><?= icone('check-lg') ?> Pagar</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if ($obrigacoes === []) : ?>
                <tr><td colspan="<?= $podeAgir ? 6 : 5 ?>" class="text-center text-muted py-4">
                    Nenhuma obrigação cadastrada.
                    <?php if ($podeAgir) : ?>
                        <?php /* Estado vazio com o próximo passo: é a primeira coisa que quem acabou de criar a conta vê. */ ?>
                        <div class="mt-2"><a class="btn btn-sm btn-primary" href="<?= e(url('obrigacoes/criar', ['empresa_id' => (int) $empresa['id']])) ?>"><?= icone('plus-lg') ?> Cadastrar a primeira obrigação</a></div>
                    <?php endif; ?>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<p class="mt-3">
    <?php if ($ehContador) : ?>
        <a href="<?= e(url('empresas/ver', ['id' => (int) $empresa['id']])) ?>"><?= icone('arrow-left') ?> Voltar à empresa</a>
    <?php else : ?>
        <a href="<?= e(url('dashboard/index')) ?>"><?= icone('arrow-left') ?> Voltar ao painel</a>
    <?php endif; ?>
</p>

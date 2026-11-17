<?php /** @var int $empresaId */ ?>
<div class="card shadow-sm mb-4" data-termometro data-url="<?= e(url('termometro/json', ['empresa_id' => $empresaId])) ?>">
    <div class="card-body">
        <h2 class="h5 mb-3">Termômetro de Risco Fiscal</h2>
        <div class="row align-items-center g-4">
            <div class="col-md-5 text-center">
                <svg class="termometro-svg" viewBox="0 0 200 118" role="img" aria-label="Velocímetro do risco fiscal">
                    <g data-segmentos></g>
                    <g data-ponteiro transform="rotate(-90 100 100)">
                        <line class="ponteiro" x1="100" y1="100" x2="100" y2="30"></line>
                        <circle class="ponteiro-base" cx="100" cy="100" r="7"></circle>
                    </g>
                </svg>
                <div class="termometro-valor" data-valor aria-live="polite">--</div>
                <div class="termometro-faixa" data-rotulo>Carregando…</div>
            </div>
            <div class="col-md-7">
                <div class="text-muted small">Multa estimada por atraso</div>
                <div class="h3 mb-1" data-multa>--</div>
                <p class="small text-muted">Simulação (multa de 0,33% ao dia, teto de 20%, mais juros de 1% ao mês). Não substitui o cálculo oficial da Receita.</p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Obrigação</th><th>Vencimento</th><th>Situação</th><th class="text-end">Multa est.</th></tr></thead>
                        <tbody data-itens></tbody>
                    </table>
                </div>
            </div>
        </div>
        <noscript><div class="alert alert-warning mt-3 mb-0">Ative o JavaScript para ver o velocímetro.</div></noscript>
    </div>
</div>

<?php /** @var int $empresaId */ ?>
<section class="card cartao-termometro mb-4" data-termometro aria-labelledby="titulo-termometro"
         data-url="<?= e(url('termometro/json', ['empresa_id' => $empresaId])) ?>">
    <div class="card-body p-4">
        <h2 class="h5 mb-4" id="titulo-termometro">Termômetro de Risco Fiscal</h2>

        <div class="row align-items-center g-4">
            <div class="col-md-6 col-xl-5 text-center">
                <svg class="termometro-svg" viewBox="0 0 240 150" role="img" aria-label="Velocímetro do risco fiscal">
                    <g data-segmentos></g>
                    <g data-escala></g>
                    <g data-ponteiro transform="rotate(-90 120 120)">
                        <path class="ponteiro" d="M 116.5 124 L 120 46 L 123.5 124 Z"></path>
                    </g>
                    <circle class="ponteiro-hub" cx="120" cy="120" r="10"></circle>
                    <circle class="ponteiro-miolo" cx="120" cy="120" r="4"></circle>
                </svg>
                <div class="termometro-valor" data-valor aria-live="polite">--</div>
                <div class="termometro-sub">risco de multa e inadimplência</div>
                <div data-selo><span class="text-muted small">Carregando…</span></div>
                <ul class="legenda-zonas" data-legenda aria-label="Faixas de risco"></ul>
            </div>

            <div class="col-md-6 col-xl-7">
                <div class="text-muted small fw-semibold">Multa estimada por atraso</div>
                <div class="multa-valor mb-1" data-multa>--</div>
                <p class="small text-muted">Simulação (multa de 0,33% ao dia, teto de 20%, mais juros de 1% ao mês). Não substitui o cálculo oficial da Receita.</p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <caption class="visually-hidden">Guias em aberto, situação e multa estimada</caption>
                        <thead><tr><th>Obrigação</th><th>Vencimento</th><th>Situação</th><th class="text-end">Multa est.</th></tr></thead>
                        <tbody data-itens></tbody>
                    </table>
                </div>
            </div>
        </div>
        <noscript><div class="alert alert-warning mt-3 mb-0">Ative o JavaScript para ver o velocímetro.</div></noscript>
    </div>
</section>

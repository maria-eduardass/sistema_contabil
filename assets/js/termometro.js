// Velocímetro do Termômetro de Risco Fiscal (JS puro + SVG).
// Busca ?rota=termometro/json e desenha: arco colorido por faixa, ponteiro animado, multa e itens.
// Todo texto vindo do servidor entra por textContent (nunca innerHTML), por isso não há risco de XSS.
(function () {
    'use strict';

    var raiz = document.querySelector('[data-termometro]');
    if (!raiz) { return; }

    var SVG_NS = 'http://www.w3.org/2000/svg';
    var CENTRO = 100, RAIO = 80;
    var moeda = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

    function $(seletor) { return raiz.querySelector(seletor); }

    function dataBR(iso) {
        var p = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso);
        return p ? p[3] + '/' + p[2] + '/' + p[1] : iso;
    }

    // Ponto do arco para um score de 0 a 100 (0 = extrema esquerda, 100 = extrema direita).
    function ponto(score) {
        var rad = (180 - score * 1.8) * Math.PI / 180;
        return { x: CENTRO + RAIO * Math.cos(rad), y: CENTRO - RAIO * Math.sin(rad) };
    }

    function segmento(de, ate, faixa) {
        var a = ponto(de), b = ponto(ate);
        var caminho = document.createElementNS(SVG_NS, 'path');
        caminho.setAttribute('d', 'M ' + a.x.toFixed(2) + ' ' + a.y.toFixed(2) +
            ' A ' + RAIO + ' ' + RAIO + ' 0 0 1 ' + b.x.toFixed(2) + ' ' + b.y.toFixed(2));
        caminho.setAttribute('class', 'seg seg-' + faixa);
        return caminho;
    }

    // Os limites das faixas vêm do servidor: a regra fica num lugar só (TermometroRisco).
    function desenharSegmentos(limites) {
        var grupo = $('[data-segmentos]');
        grupo.textContent = '';
        grupo.appendChild(segmento(0, limites.atencao, 'baixo'));
        grupo.appendChild(segmento(limites.atencao, limites.alto, 'atencao'));
        grupo.appendChild(segmento(limites.alto, limites.critico, 'alto'));
        grupo.appendChild(segmento(limites.critico, 100, 'critico'));
    }

    function posicionar(score) {
        // 0 -> ponteiro para a esquerda (-90°), 100 -> direita (+90°).
        $('[data-ponteiro]').setAttribute('transform', 'rotate(' + ((score - 50) * 1.8) + ' ' + CENTRO + ' ' + CENTRO + ')');
        $('[data-valor]').textContent = Math.round(score) + '%';
    }

    function animar(scoreFinal) {
        var reduzido = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduzido) { posicionar(scoreFinal); return; }
        var inicio = null, duracao = 900;
        function quadro(agora) {
            if (inicio === null) { inicio = agora; }
            var t = Math.min(1, (agora - inicio) / duracao);
            posicionar(scoreFinal * (1 - Math.pow(1 - t, 3))); // desaceleração suave no final
            if (t < 1) { window.requestAnimationFrame(quadro); }
        }
        window.requestAnimationFrame(quadro);
    }

    function celula(linha, texto, classe) {
        var td = document.createElement('td');
        td.textContent = texto;
        if (classe) { td.className = classe; }
        linha.appendChild(td);
    }

    function preencherItens(itens) {
        var corpo = $('[data-itens]');
        corpo.textContent = '';
        if (itens.length === 0) {
            var vazio = document.createElement('tr');
            var td = document.createElement('td');
            td.colSpan = 4;
            td.className = 'text-muted';
            td.textContent = 'Nenhuma obrigação em aberto.';
            vazio.appendChild(td);
            corpo.appendChild(vazio);
            return;
        }
        itens.forEach(function (item) {
            var tr = document.createElement('tr');
            var atrasada = item.dias_atraso > 0;
            celula(tr, item.tipo_sigla);
            celula(tr, dataBR(item.data_vencimento));
            celula(tr, atrasada ? 'Atrasada há ' + item.dias_atraso + (item.dias_atraso === 1 ? ' dia' : ' dias') : 'No prazo',
                atrasada ? 'text-danger' : 'text-success');
            celula(tr, atrasada ? moeda.format(item.multa_centavos / 100) : '—', 'text-end text-nowrap');
            corpo.appendChild(tr);
        });
    }

    function renderizar(dados) {
        raiz.setAttribute('data-faixa', dados.faixa); // o CSS colore o texto conforme a faixa
        desenharSegmentos(dados.limites);
        $('[data-rotulo]').textContent = dados.faixa_rotulo;
        $('[data-multa]').textContent = moeda.format(dados.multa_total_centavos / 100);
        raiz.querySelector('svg').setAttribute('aria-label',
            'Risco fiscal de ' + dados.score + ' por cento, ' + dados.faixa_rotulo);
        preencherItens(dados.itens);
        animar(dados.score);
    }

    function falha() {
        $('[data-rotulo]').textContent = 'Não foi possível carregar o termômetro.';
        $('[data-valor]').textContent = '--';
    }

    fetch(raiz.getAttribute('data-url'), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (resposta) {
            if (!resposta.ok) { throw new Error('HTTP ' + resposta.status); }
            return resposta.json();
        })
        .then(renderizar)
        .catch(falha);
})();

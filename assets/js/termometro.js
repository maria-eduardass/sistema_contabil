// Velocímetro do Termômetro de Risco Fiscal (JS puro + SVG).
// Busca ?rota=termometro/json e desenha: zonas coloridas (paleta de status), escala, ponteiro animado,
// legenda, multa e a tabela de guias (que é também a versão acessível dos dados).
// Todo texto vindo do servidor entra por textContent (nunca innerHTML), por isso não há risco de XSS.
(function () {
    'use strict';

    var raiz = document.querySelector('[data-termometro]');
    if (!raiz) { return; }

    var SVG_NS = 'http://www.w3.org/2000/svg';
    var CENTRO = 120;          // centro do arco no viewBox 240x150
    var RAIO = 92;             // raio do arco das zonas
    var RAIO_ESCALA = 111;     // raio onde ficam os números da escala
    var FOLGA_PX = 2;          // 2px de "ar" entre zonas vizinhas (em vez de borda)
    var moeda = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

    // Ícone de cada faixa: o status nunca depende só da cor (ícone + rótulo sempre juntos).
    var ICONES = { baixo: 'check-circle-fill', atencao: 'exclamation-circle-fill', alto: 'exclamation-triangle-fill', critico: 'x-octagon-fill' };
    var NOMES = { baixo: 'Baixo', atencao: 'Atenção', alto: 'Alto', critico: 'Crítico' };

    function $(seletor) { return raiz.querySelector(seletor); }

    function dataBR(iso) {
        var p = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso);
        return p ? p[3] + '/' + p[2] + '/' + p[1] : iso;
    }

    // Ponto do arco para um score de 0 a 100 (0 = extrema esquerda, 100 = extrema direita), num raio dado.
    function ponto(score, raio) {
        var rad = (180 - score * 1.8) * Math.PI / 180;
        return { x: CENTRO + raio * Math.cos(rad), y: CENTRO - raio * Math.sin(rad) };
    }

    function elemento(nome, atributos) {
        var el = document.createElementNS(SVG_NS, nome);
        Object.keys(atributos).forEach(function (k) { el.setAttribute(k, atributos[k]); });
        return el;
    }

    function icone(nomeIcone) {
        var i = document.createElement('i');
        i.className = 'bi bi-' + nomeIcone;
        i.setAttribute('aria-hidden', 'true');
        return i;
    }

    function zona(de, ate, faixa, apagada) {
        // A folga em pontos de score equivalente a FOLGA_PX no raio do arco (só entre zonas, não nas pontas).
        var folga = (FOLGA_PX / RAIO) * (180 / Math.PI) / 1.8 / 2;
        var a = ponto(de === 0 ? de : de + folga, RAIO);
        var b = ponto(ate === 100 ? ate : ate - folga, RAIO);
        return elemento('path', {
            d: 'M ' + a.x.toFixed(2) + ' ' + a.y.toFixed(2) + ' A ' + RAIO + ' ' + RAIO + ' 0 0 1 ' + b.x.toFixed(2) + ' ' + b.y.toFixed(2),
            'class': 'zona zona-' + faixa + (apagada ? ' zona-apagada' : '')
        });
    }

    // Os limites das faixas vêm do servidor: a regra fica num lugar só (TermometroRisco).
    function limitesDasZonas(l) {
        return [
            { faixa: 'baixo', de: 0, ate: l.atencao },
            { faixa: 'atencao', de: l.atencao, ate: l.alto },
            { faixa: 'alto', de: l.alto, ate: l.critico },
            { faixa: 'critico', de: l.critico, ate: 100 }
        ];
    }

    function desenharZonas(limites, faixaAtual) {
        var grupo = $('[data-segmentos]');
        grupo.textContent = '';
        limitesDasZonas(limites).forEach(function (z) {
            grupo.appendChild(zona(z.de, z.ate, z.faixa, z.faixa !== faixaAtual)); // as demais zonas recuam
        });
    }

    function desenharEscala(limites) {
        var grupo = $('[data-escala]');
        grupo.textContent = '';
        [0, limites.atencao, limites.alto, limites.critico, 100].forEach(function (valor) {
            var p = ponto(valor, RAIO_ESCALA);
            var ancora = p.x < CENTRO - 6 ? 'end' : (p.x > CENTRO + 6 ? 'start' : 'middle');
            var t = elemento('text', { x: p.x.toFixed(1), y: (p.y + 3).toFixed(1), 'text-anchor': ancora, 'class': 'escala-texto' });
            t.textContent = String(valor);
            grupo.appendChild(t);
        });
    }

    function desenharLegenda(limites) {
        var lista = $('[data-legenda]');
        lista.textContent = '';
        limitesDasZonas(limites).forEach(function (z) {
            var li = document.createElement('li');
            var bolinha = document.createElement('span');
            bolinha.className = 'legenda-ponto legenda-' + z.faixa;
            bolinha.setAttribute('aria-hidden', 'true');
            li.appendChild(bolinha);
            li.appendChild(document.createTextNode(NOMES[z.faixa] + ' ' + z.de + '–' + (z.ate === 100 ? 100 : z.ate - 1)));
            lista.appendChild(li);
        });
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

    function selo(classe, nomeIcone, texto) {
        var s = document.createElement('span');
        s.className = 'selo-status ' + classe;
        s.appendChild(icone(nomeIcone));
        s.appendChild(document.createTextNode(texto));
        return s;
    }

    function celula(linha, conteudo, classe) {
        var td = document.createElement('td');
        if (typeof conteudo === 'string') { td.textContent = conteudo; } else { td.appendChild(conteudo); }
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
            celula(tr, atrasada
                ? selo('selo-status-atrasado', 'exclamation-triangle-fill', 'Atrasada há ' + item.dias_atraso + (item.dias_atraso === 1 ? ' dia' : ' dias'))
                : selo('', 'clock-history', 'No prazo'));
            celula(tr, atrasada ? moeda.format(item.multa_centavos / 100) : '—', 'text-end text-nowrap');
            corpo.appendChild(tr);
        });
    }

    function renderizar(dados) {
        raiz.setAttribute('data-faixa', dados.faixa);
        desenharZonas(dados.limites, dados.faixa);
        desenharEscala(dados.limites);
        desenharLegenda(dados.limites);

        var alvo = $('[data-selo]');
        alvo.textContent = '';
        alvo.appendChild(selo('selo-faixa-' + dados.faixa, ICONES[dados.faixa] || 'dash-circle', dados.faixa_rotulo));

        $('[data-multa]').textContent = moeda.format(dados.multa_total_centavos / 100);
        raiz.querySelector('svg').setAttribute('aria-label',
            'Risco fiscal de ' + dados.score + ' por cento, ' + dados.faixa_rotulo);
        preencherItens(dados.itens);
        animar(dados.score);
    }

    function falha() {
        var alvo = $('[data-selo]');
        alvo.textContent = '';
        var aviso = document.createElement('span');
        aviso.className = 'text-danger small';
        aviso.textContent = 'Não foi possível carregar o termômetro.';
        alvo.appendChild(aviso);
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

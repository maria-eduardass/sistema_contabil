// Formulário de nova obrigação: atalhos de data + PRÉVIA do termômetro.
//
// A prévia pergunta ao servidor (termometro/simular) "e se eu cadastrar esta guia?" e mostra o termômetro de hoje e o
// que ele passaria a ser. O cálculo NÃO é feito aqui: o servidor usa as mesmas regras do termômetro real, então esta
// tela nunca promete um número diferente do que aparece depois de salvar (não existe uma segunda cópia da fórmula).
//
// É um acréscimo: sem JavaScript o formulário funciona igual. Todo texto vindo do servidor entra por textContent
// (nunca innerHTML), e o estado visual vem de classes e atributos (sem estilo inline), como a CSP exige.
(function () {
    'use strict';

    var form = document.querySelector('form[data-simular-url]');
    var painel = document.querySelector('[data-simulacao]');
    if (!form || !painel) { return; }

    var urlBase = form.getAttribute('data-simular-url');
    var campoVencimento = form.querySelector('#data_vencimento');
    var campoValor = form.querySelector('#valor');
    var campoCompetencia = form.querySelector('#competencia');
    var ajuda = painel.querySelector('[data-simulacao-ajuda]');
    var corpo = painel.querySelector('[data-simulacao-corpo]');
    var moeda = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

    // Mesmos ícones do velocímetro: o estado nunca depende só da cor (ícone + rótulo sempre juntos).
    var ICONES = { baixo: 'check-circle-fill', atencao: 'exclamation-circle-fill', alto: 'exclamation-triangle-fill', critico: 'x-octagon-fill' };

    var numeroDoPedido = 0;   // descarta respostas antigas que chegarem depois de uma mais nova
    var temporizador = null;  // espera a pessoa parar de digitar antes de consultar

    function doisDigitos(n) { return (n < 10 ? '0' : '') + n; }
    function textoData(d) { return d.getFullYear() + '-' + doisDigitos(d.getMonth() + 1) + '-' + doisDigitos(d.getDate()); }
    function textoMes(d) { return d.getFullYear() + '-' + doisDigitos(d.getMonth() + 1); }

    function criar(tag, classe, texto) {
        var e = document.createElement(tag);
        if (classe) { e.className = classe; }
        if (texto !== undefined) { e.textContent = texto; }
        return e;
    }

    function selo(faixa, rotulo) {
        var s = criar('span', 'selo-status selo-faixa-' + faixa);
        var i = criar('i', 'bi bi-' + (ICONES[faixa] || 'dash-circle'));
        i.setAttribute('aria-hidden', 'true');
        s.appendChild(i);
        s.appendChild(document.createTextNode(rotulo));
        return s;
    }

    function medidor(titulo, r) {
        var bloco = criar('div', 'simulacao-medidor');
        bloco.appendChild(criar('div', 'simulacao-titulo', titulo));
        var linha = criar('div', 'simulacao-linha');
        linha.appendChild(criar('strong', 'simulacao-valor', r.score + '%'));
        linha.appendChild(selo(r.faixa, r.faixa_rotulo));
        bloco.appendChild(linha);
        var barra = criar('progress', 'barra-risco barra-' + r.faixa);
        barra.setAttribute('max', '100');
        barra.setAttribute('value', String(r.score));
        barra.setAttribute('aria-label', titulo + ': ' + r.score + ' por cento');
        bloco.appendChild(barra);
        return bloco;
    }

    function descreverNova(nova) {
        if (nova.dias_atraso > 0) {
            var dias = nova.dias_atraso + (nova.dias_atraso === 1 ? ' dia' : ' dias');
            return 'Esta guia nasce atrasada há ' + dias + (nova.multa_centavos > 0 ? ' · multa e juros estimados: ' + moeda.format(nova.multa_centavos / 100) : '') + '.';
        }
        return nova.risco > 0 ? 'Esta guia ainda está no prazo, mas vence em breve (risco ' + nova.risco + ' de 100).' : 'Esta guia está no prazo e longe do vencimento: não pesa no termômetro.';
    }

    function mostrar(dados) {
        corpo.textContent = '';
        corpo.hidden = false;

        if (dados.depois === null) { // formulário incompleto: mostra só o termômetro de hoje
            ajuda.hidden = false;
            ajuda.textContent = dados.erros.vencimento
                ? 'Escolha o vencimento (ou um atalho) para ver como esta guia muda o seu termômetro.'
                : (dados.erros.valor || 'Confira os dados da guia.');
            corpo.appendChild(medidor('Seu termômetro hoje', dados.antes));
            painel.removeAttribute('data-faixa');
            return;
        }

        ajuda.hidden = false;
        ajuda.textContent = descreverNova(dados.nova);
        var par = criar('div', 'simulacao-par');
        par.appendChild(medidor('Hoje', dados.antes));
        var seta = criar('i', 'bi bi-arrow-right simulacao-seta');
        seta.setAttribute('aria-hidden', 'true');
        par.appendChild(seta);
        par.appendChild(medidor('Com esta obrigação', dados.depois));
        corpo.appendChild(par);
        painel.setAttribute('data-faixa', dados.depois.faixa); // o CSS pinta o cartão com a cor da faixa
    }

    function falhar() {
        ajuda.hidden = false;
        ajuda.textContent = 'Não foi possível calcular a prévia agora. Você ainda pode salvar normalmente.';
        corpo.hidden = true;
    }

    function consultar() {
        var meu = ++numeroDoPedido;
        var url = urlBase + '&vencimento=' + encodeURIComponent(campoVencimento.value) + '&valor=' + encodeURIComponent(campoValor.value);
        fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (resposta) {
                if (!resposta.ok) { throw new Error('HTTP ' + resposta.status); }
                return resposta.json();
            })
            .then(function (dados) { if (meu === numeroDoPedido) { mostrar(dados); } })
            .catch(function () { if (meu === numeroDoPedido) { falhar(); } });
    }

    function agendar(atraso) {
        window.clearTimeout(temporizador);
        temporizador = window.setTimeout(consultar, atraso);
    }

    // Atalhos: preenchem o vencimento (hoje + N dias) e acertam a competência para o mesmo dia dele quando ela está vazia ou
    // vem DEPOIS do vencimento (o servidor recusa vencimento anterior ao mês da competência).
    Array.prototype.forEach.call(form.querySelectorAll('[data-atalho]'), function (botao) {
        botao.addEventListener('click', function () {
            var d = new Date();
            d.setHours(12, 0, 0, 0); // meio-dia evita virar o dia por causa de horário de verão
            d.setDate(d.getDate() + parseInt(botao.getAttribute('data-atalho'), 10));
            campoVencimento.value = textoData(d);
            // Competência nunca pode ser depois do vencimento (o servidor recusa): se estiver vazia ou maior, acompanha o atalho.
            if (campoCompetencia && (campoCompetencia.value === '' || campoCompetencia.value > textoData(d))) {
                campoCompetencia.value = textoData(d);
            }
            agendar(0);
        });
    });

    campoVencimento.addEventListener('input', function () { agendar(250); });
    campoVencimento.addEventListener('change', function () { agendar(0); });
    campoValor.addEventListener('input', function () { agendar(250); });

    agendar(0); // mostra já o termômetro de hoje (e a prévia, se o formulário voltou preenchido após um erro)
})();

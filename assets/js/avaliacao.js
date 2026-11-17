// Modal de avaliação do sistema: envia a nota (1 a 5) por fetch, sem recarregar a página.
// O texto devolvido pelo servidor entra por textContent (nunca innerHTML).
(function () {
    'use strict';

    var form = document.getElementById('formAvaliacao');
    var modal = document.getElementById('modalAvaliacao');
    if (!form || !modal) { return; }

    var alerta = form.querySelector('[data-avaliacao-alerta]');
    var botao = form.querySelector('button[type="submit"]');

    function mostrarAlerta(tipo, texto) {
        alerta.className = 'alert alert-' + tipo;
        alerta.textContent = texto;
    }

    var textoNota = form.querySelector('[data-nota-texto]');
    var contador = form.querySelector('[data-contador]');
    var comentario = form.querySelector('textarea');
    var frases = { 1: 'Muito ruim', 2: 'Ruim', 3: 'Regular', 4: 'Bom', 5: 'Excelente!' };
    var padrao = textoNota.textContent;

    // Frase ao lado das estrelas e contador de caracteres (textContent: nunca innerHTML).
    form.addEventListener('change', function (e) {
        if (e.target.name === 'nota') { textoNota.textContent = frases[e.target.value] || padrao; }
    });
    comentario.addEventListener('input', function () { contador.textContent = String(comentario.value.length); });

    function limpar() {
        alerta.className = 'alert d-none';
        alerta.textContent = '';
        Array.prototype.forEach.call(form.querySelectorAll('[data-erro]'), function (el) { el.textContent = ''; });
    }

    function mostrarErros(erros) {
        Object.keys(erros).forEach(function (campo) {
            var alvo = form.querySelector('[data-erro="' + campo + '"]');
            if (alvo) { alvo.textContent = erros[campo]; }
        });
    }

    form.addEventListener('submit', function (evento) {
        evento.preventDefault();
        limpar();
        botao.disabled = true;

        fetch(form.getAttribute('action'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            body: new URLSearchParams(new FormData(form)) // inclui o csrf_token do formulário
        })
            .then(function (resposta) {
                return resposta.json().then(
                    function (dados) { return { status: resposta.status, dados: dados }; },
                    function () { return { status: resposta.status, dados: null }; } // resposta não era JSON
                );
            })
            .then(function (r) {
                if (r.dados && r.dados.ok) {
                    mostrarAlerta('success', r.dados.mensagem);
                    form.reset();
                    textoNota.textContent = padrao;
                    contador.textContent = '0';
                } else if (r.dados && r.dados.erros) {
                    mostrarErros(r.dados.erros);
                } else {
                    mostrarAlerta('danger', 'Não foi possível enviar. Sua sessão pode ter expirado: recarregue a página.');
                }
            })
            .catch(function () {
                mostrarAlerta('danger', 'Falha de conexão. Tente novamente.');
            })
            .then(function () { botao.disabled = false; });
    });

    // Ao reabrir o modal, começa limpo.
    modal.addEventListener('hidden.bs.modal', limpar);
})();

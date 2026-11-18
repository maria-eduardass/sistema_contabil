// Tela "Criar conta": valida os obrigatórios ANTES de enviar. Se faltar algo, o envio é cancelado (preventDefault), então
// nada do que foi digitado some (nem as senhas, que o servidor nunca devolve). Marca os campos com erro, escreve a mensagem
// ao lado, rola até o primeiro e põe o foco nele. O servidor valida de novo: isto é só conforto, não segurança.
(function () {
    'use strict';

    var form = document.querySelector('form[action*="cadastro/salvar"]');
    if (!form) { return; }

    // campo => nome mostrado na mensagem
    var obrigatorios = {
        nome: 'o nome', email: 'o e-mail', senha: 'a senha', senha_confirmacao: 'a confirmação da senha',
        razao_social: 'a razão social', cnpj: 'o CNPJ', regime_tributario: 'o regime tributário'
    };

    function limparErro(campo) {
        campo.classList.remove('is-invalid');
        var aviso = campo.parentNode.querySelector('.invalid-feedback[data-local]');
        if (aviso) { aviso.remove(); }
    }

    function marcarErro(campo, texto) {
        limparErro(campo);
        campo.classList.add('is-invalid');
        var aviso = document.createElement('div');
        aviso.className = 'invalid-feedback';
        aviso.setAttribute('data-local', '1'); // distingue do erro que veio do servidor
        aviso.textContent = texto; // textContent: nunca innerHTML
        campo.insertAdjacentElement('afterend', aviso);
    }

    function irParaPrimeiroErro() {
        var primeiro = form.querySelector('.is-invalid');
        if (primeiro) {
            primeiro.scrollIntoView({ behavior: 'smooth', block: 'center' });
            primeiro.focus({ preventScroll: true });
        }
    }

    form.addEventListener('submit', function (evento) {
        var faltou = 0;
        Object.keys(obrigatorios).forEach(function (nome) {
            var campo = form.elements[nome];
            if (!campo) { return; }
            if (campo.value.trim() === '') {
                marcarErro(campo, 'Preencha ' + obrigatorios[nome] + '.');
                faltou++;
            } else {
                limparErro(campo);
            }
        });
        var senha = form.elements.senha;
        var conf = form.elements.senha_confirmacao;
        if (senha.value !== '' && conf.value !== '' && senha.value !== conf.value) {
            marcarErro(conf, 'As senhas não coincidem.');
            faltou++;
        }
        if (faltou > 0) {
            evento.preventDefault();
            irParaPrimeiroErro();
        }
    });

    // Ao corrigir um campo, o erro dele some na hora.
    form.addEventListener('input', function (e) {
        if (e.target.classList && e.target.classList.contains('is-invalid')) { limparErro(e.target); }
    });

    // Página recarregada com erros do servidor (ex.: CNPJ já cadastrado): leva a pessoa até o primeiro.
    irParaPrimeiroErro();
})();

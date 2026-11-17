// Confirmação antes de ações destrutivas: <form data-confirmar="Tem certeza?">.
// Fica num arquivo (e não em onsubmit="...") para a política CSP da Tarefa 17 poder bloquear JS inline.
document.addEventListener('submit', function (evento) {
    var mensagem = evento.target.dataset ? evento.target.dataset.confirmar : null;
    if (mensagem && !window.confirm(mensagem)) {
        evento.preventDefault();
    }
});

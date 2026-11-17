<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;

function usuarioBancoTeste(): array
{
    return ['id' => 2, 'nome' => 'João', 'email' => 'j@x.test', 'perfil' => 'cliente', 'empresa_id' => 1, 'senha_hash' => 'NAO_DEVE_FICAR_NA_SESSAO'];
}

teste('Auth: sem login não há usuário', function () {
    $_SESSION = [];
    afirmarIgual(null, Auth::usuario(1000));
});

teste('Auth: entrar guarda só os dados necessários (nunca o hash da senha)', function () {
    $_SESSION = [];
    Auth::entrar(usuarioBancoTeste(), 1000);
    $u = Auth::usuario(1000);
    afirmarIgual(['id' => 2, 'nome' => 'João', 'perfil' => 'cliente', 'empresa_id' => 1], $u);
    afirmarFalso(str_contains(json_encode($_SESSION), 'NAO_DEVE_FICAR'));
});

teste('Auth: entrar troca o token CSRF (evita reaproveitar token pré-login)', function () {
    $_SESSION = [];
    $antes = Csrf::token();
    Auth::entrar(usuarioBancoTeste(), 1000);
    afirmarFalso(Csrf::validar($antes));
});

teste('Auth: sessão expira após 30 minutos de inatividade', function () {
    $_SESSION = [];
    Auth::entrar(usuarioBancoTeste(), 1000);
    afirmarVerdadeiro(Auth::usuario(1000 + 1800) !== null);   // exatamente no limite ainda vale
    afirmarIgual(null, Auth::usuario(1000 + 1800 + 1801));   // passou do limite desde o último acesso
    afirmarIgual(null, Auth::usuario(1000 + 1800 + 1802));   // e a sessão foi limpa
});

teste('Auth: atividade renova o prazo de inatividade', function () {
    $_SESSION = [];
    Auth::entrar(usuarioBancoTeste(), 1000);
    afirmarVerdadeiro(Auth::usuario(2500) !== null); // 25 min depois, renova
    afirmarVerdadeiro(Auth::usuario(4000) !== null); // 25 min depois de novo (50 min no total)
});

teste('Auth: sair limpa a sessão', function () {
    $_SESSION = [];
    Auth::entrar(usuarioBancoTeste(), 1000);
    Auth::sair();
    afirmarIgual(null, Auth::usuario(1001));
    afirmarIgual([], $_SESSION);
});

teste('Auth: sessão com dados corrompidos é tratada como deslogada', function () {
    $_SESSION = ['usuario' => 'lixo', 'ultimo_acesso' => 1000];
    afirmarIgual(null, Auth::usuario(1001));
});

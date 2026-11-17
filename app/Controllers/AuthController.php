<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\View;
use App\Models\Usuario;
use App\Services\LimiteTentativas;

final class AuthController
{
    public function formLogin(Request $req): void
    {
        if (Auth::usuario() !== null) {
            redirecionar('dashboard/index');
        }
        View::exibir('auth/login', ['titulo' => 'Entrar', 'erro' => null, 'email' => '']);
    }

    public function login(Request $req): void
    {
        $agora = time();
        $_SESSION['limite_login'] ??= [];
        $limite = new LimiteTentativas($_SESSION['limite_login']);
        $email = trim($req->post('email') ?? '');
        $senha = $req->post('senha') ?? '';

        if ($limite->bloqueado($agora)) {
            $minutos = (int) ceil($limite->segundosRestantes($agora) / 60);
            $this->recusar(429, "Muitas tentativas. Aguarde $minutos minuto(s) e tente novamente.", $email);
            return;
        }

        $usuario = ($email !== '' && $senha !== '')
            ? (new Usuario(Database::conexao()))->autenticar($email, $senha)
            : null;

        if ($usuario === null) {
            $limite->registrarFalha($agora);
            // Mensagem única: não revela se o e-mail existe.
            $this->recusar(401, 'E-mail ou senha inválidos.', $email);
            return;
        }

        $limite->zerar();
        Auth::entrar($usuario, $agora);
        redirecionar('dashboard/index');
    }

    public function logout(Request $req): void
    {
        Auth::sair();
        redirecionar('auth/login');
    }

    private function recusar(int $codigo, string $erro, string $email): void
    {
        http_response_code($codigo);
        View::exibir('auth/login', ['titulo' => 'Entrar', 'erro' => $erro, 'email' => $email]);
    }
}

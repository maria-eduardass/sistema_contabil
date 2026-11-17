<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Estado de login guardado na sessão do PHP.
 * "agora" é injetável apenas para os testes controlarem o tempo.
 */
final class Auth
{
    public const INATIVIDADE_MAXIMA = 1800; // 30 minutos

    /**
     * Inicia a sessão autenticada. Guarda só o necessário: nunca o hash da senha.
     * session_regenerate_id evita "session fixation" (atacante que fixou o ID antes do login).
     *
     * @param array<string,mixed> $usuario
     */
    public static function entrar(array $usuario, ?int $agora = null): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['usuario'] = [
            'id' => (int) $usuario['id'],
            'nome' => (string) $usuario['nome'],
            'perfil' => (string) $usuario['perfil'],
            'empresa_id' => $usuario['empresa_id'] === null ? null : (int) $usuario['empresa_id'],
        ];
        $_SESSION['ultimo_acesso'] = $agora ?? time();
        Csrf::regenerar();
    }

    /**
     * Usuário logado, ou null. Se passou de 30 min sem atividade, encerra a sessão.
     *
     * @return array{id:int,nome:string,perfil:string,empresa_id:?int}|null
     */
    public static function usuario(?int $agora = null): ?array
    {
        $agora ??= time();
        $usuario = $_SESSION['usuario'] ?? null;
        $ultimo = $_SESSION['ultimo_acesso'] ?? null;

        if (!is_array($usuario) || !is_int($ultimo)) {
            return null;
        }
        if ($agora - $ultimo > self::INATIVIDADE_MAXIMA) {
            self::sair();
            return null;
        }
        $_SESSION['ultimo_acesso'] = $agora;

        return $usuario;
    }

    /**
     * Encerra o login: apaga os dados e troca o ID (o antigo é descartado no servidor).
     * Por que não session_destroy(): ele fecha a sessão, e a página seguinte (ex.: o formulário
     * de login, que guarda o token CSRF) não conseguiria mais gravar nada nela.
     */
    public static function sair(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}

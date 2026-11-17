<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Regras de acesso. Duas camadas, ambas no servidor:
 *  1. Por rota (decidir): quem pode abrir a página — publico, logado, contador ou cliente.
 *  2. Por objeto (podeAcessarEmpresa): o cliente só enxerga os dados da PRÓPRIA empresa.
 *     Sem isso, trocar ?id=1 por ?id=2 na URL exporia dados alheios (falha conhecida como IDOR).
 *
 * Princípio: na dúvida, negar. Um nível de acesso digitado errado numa rota nega em vez de liberar.
 */
final class Guarda
{
    public const PERMITIR = 'permitir';
    public const LOGIN = 'login';   // anônimo: redirecionar ao login
    public const NEGAR = 'negar';   // logado sem permissão: 403

    /** @param array{perfil:string}|null $usuario */
    public static function decidir(string $acesso, ?array $usuario): string
    {
        if ($acesso === 'publico') {
            return self::PERMITIR;
        }
        if ($usuario === null) {
            return self::LOGIN;
        }

        return match ($acesso) {
            'logado' => self::PERMITIR,
            'contador', 'cliente' => $usuario['perfil'] === $acesso ? self::PERMITIR : self::NEGAR,
            default => self::NEGAR,
        };
    }

    /** @param array{perfil:string,empresa_id:?int} $usuario */
    public static function podeAcessarEmpresa(array $usuario, int $empresaId): bool
    {
        return match ($usuario['perfil']) {
            'contador' => true,
            'cliente' => $usuario['empresa_id'] !== null && $usuario['empresa_id'] === $empresaId,
            default => false,
        };
    }

    /**
     * @param array{perfil:string,empresa_id:?int} $usuario
     * @throws HttpException 403
     */
    public static function exigirEmpresa(array $usuario, int $empresaId): void
    {
        if (!self::podeAcessarEmpresa($usuario, $empresaId)) {
            throw new HttpException(403);
        }
    }
}

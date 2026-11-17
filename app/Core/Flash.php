<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Mensagens de uma única exibição ("Empresa salva!") que sobrevivem ao redirecionamento
 * pós-POST. Por que existe: o padrão POST -> redirect -> GET evita reenvio com F5, mas
 * o redirect perde a mensagem; a sessão a carrega até a próxima página.
 */
final class Flash
{
    private const TIPOS = ['sucesso', 'erro'];

    public static function definir(string $tipo, string $mensagem): void
    {
        $_SESSION['flash'][] = [
            'tipo' => in_array($tipo, self::TIPOS, true) ? $tipo : 'erro',
            'mensagem' => $mensagem,
        ];
    }

    /** @return list<array{tipo:string,mensagem:string}> Entrega e apaga. */
    public static function obter(): array
    {
        $mensagens = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);

        return $mensagens;
    }
}

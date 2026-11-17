<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Fábrica de conexão PDO (uma por nome de banco, por requisição).
 *
 * Por que EMULATE_PREPARES=false: o MySQL recebe a query e os parâmetros
 * separados, o que elimina SQL Injection de verdade (não só por escape).
 */
final class Database
{
    /** @var array<string,PDO> */
    private static array $conexoes = [];

    /** @param string|null $banco Nome do banco; padrão = DB_NAME do .env. */
    public static function conexao(?string $banco = null): PDO
    {
        $banco ??= Env::get('DB_NAME', 'fiscalpulse');
        if (isset(self::$conexoes[$banco])) {
            return self::$conexoes[$banco];
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            Env::get('DB_HOST', '127.0.0.1'),
            Env::get('DB_PORT', '3306'),
            $banco
        );

        try {
            $pdo = new PDO($dsn, Env::get('DB_USER', 'root'), Env::get('DB_PASS', ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            // Mensagem genérica: a original pode expor host/usuário.
            throw new RuntimeException('Falha ao conectar ao banco de dados.', 0, $e);
        }

        return self::$conexoes[$banco] = $pdo;
    }
}

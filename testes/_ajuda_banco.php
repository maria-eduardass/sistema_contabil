<?php
declare(strict_types=1);

use App\Core\Env;

/**
 * Recria o banco de TESTE (DB_NAME_TESTE) a partir do database.sql e devolve uma conexão PDO.
 * Roda uma vez por execução; cada teste que altera dados deve usar transação + rollBack().
 *
 * Segurança: recusa rodar se DB_NAME_TESTE for igual ao banco real, para nunca apagar dados de verdade.
 */
function bancoDeTeste(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $teste = Env::get('DB_NAME_TESTE', 'fiscalpulse_teste');
    if ($teste === Env::get('DB_NAME', 'fiscalpulse') || !str_ends_with($teste, '_teste')) {
        throw new RuntimeException('DB_NAME_TESTE deve ser diferente do banco real e terminar em "_teste".');
    }

    $servidor = new PDO(
        sprintf('mysql:host=%s;port=%s;charset=utf8mb4', Env::get('DB_HOST', '127.0.0.1'), Env::get('DB_PORT', '3306')),
        Env::get('DB_USER', 'root'),
        Env::get('DB_PASS', ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $sql = (string) file_get_contents(RAIZ . '/database.sql');
    // Troca só os dois comandos que citam o nome do banco (e-mails do seed também contêm "fiscalpulse").
    $sql = str_replace(
        ['CREATE DATABASE IF NOT EXISTS fiscalpulse ', 'USE fiscalpulse;'],
        ["DROP DATABASE IF EXISTS `$teste`; CREATE DATABASE `$teste` ", "USE `$teste`;"],
        $sql
    );
    $servidor->exec($sql);

    return $pdo = App\Core\Database::conexao($teste);
}

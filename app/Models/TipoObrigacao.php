<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class TipoObrigacao
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array{id:int,sigla:string,nome:string,periodicidade:string}> */
    public function listar(): array
    {
        return $this->pdo->query('SELECT id, sigla, nome, periodicidade FROM tipos_obrigacao ORDER BY sigla')->fetchAll();
    }
}

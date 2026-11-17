<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Violação de uma regra de negócio esperada (ex.: "já está paga"), diferente de um erro de programa.
 * A mensagem é segura para mostrar ao usuário; o código sugere o status HTTP (404, 409, 422).
 */
final class RegraDeNegocio extends RuntimeException
{
    public function __construct(string $mensagem, public readonly int $codigoHttp)
    {
        parent::__construct($mensagem);
    }
}

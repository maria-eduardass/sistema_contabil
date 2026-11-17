<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Lançada por controllers/guardas para encerrar a requisição com 403, 404, 405 etc.
 * O Router a converte na página de erro correspondente.
 */
final class HttpException extends RuntimeException
{
    public function __construct(private int $codigoHttp)
    {
        parent::__construct('HTTP ' . $codigoHttp);
    }

    public function codigoHttp(): int
    {
        return $this->codigoHttp;
    }
}

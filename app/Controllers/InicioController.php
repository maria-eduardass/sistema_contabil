<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;

/** Raiz do site: só encaminha. O Router já mandou anônimos para o login. */
final class InicioController
{
    public function index(Request $req): void
    {
        redirecionar('dashboard/index');
    }
}

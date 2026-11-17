<?php
// Front-controller: único ponto de entrada. Toda a lógica está no Router.
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Core\Request;
use App\Core\Router;

(new Router(require RAIZ . '/config/rotas.php'))->despachar(new Request());

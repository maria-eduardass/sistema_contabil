<?php
declare(strict_types=1);

use App\Core\Env;

define('RAIZ', dirname(__DIR__));

require __DIR__ . '/autoload.php';
require __DIR__ . '/helpers.php';
Env::carregar(RAIZ . '/.env');

date_default_timezone_set('America/Sao_Paulo');
mb_internal_encoding('UTF-8');

// Por que: detalhes de erro só aparecem em desenvolvimento; sempre vão para o log.
$emDev = Env::get('APP_ENV', 'producao') === 'desenvolvimento';
ini_set('display_errors', $emDev ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', RAIZ . '/storage/logs/php_erros.log');
error_reporting(E_ALL);

$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

// Sessão endurecida: ID só em cookie, HttpOnly, SameSite e modo estrito (rejeita IDs não emitidos).
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
// O PHP apaga sessões "velhas" pelo gc_maxlifetime (padrão 24 min). Alinhado aos 30 min de inatividade
// do Auth, senão a sessão poderia sumir ANTES do prazo anunciado.
ini_set('session.gc_maxlifetime', (string) \App\Core\Auth::INATIVIDADE_MAXIMA);
session_set_cookie_params([
    'lifetime' => 0,
    // Cookie restrito à pasta do sistema: não é enviado a outros projetos do mesmo localhost/XAMPP.
    'path' => rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_name('FISCALPULSE_SID');
session_start();

\App\Core\Cabecalhos::enviar($https); // CSP, anti-clickjacking, no-store etc. (ver Cabecalhos.php)

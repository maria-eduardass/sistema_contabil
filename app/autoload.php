<?php
declare(strict_types=1);

/**
 * Autoloader PSR-4 mínimo (sem Composer): App\Core\Env -> app/Core/Env.php.
 * Por que: o ambiente XAMPP do projeto dispensa dependências externas.
 */
spl_autoload_register(static function (string $classe): void {
    $prefixo = 'App\\';
    if (strncmp($classe, $prefixo, strlen($prefixo)) !== 0) {
        return;
    }
    $relativo = substr($classe, strlen($prefixo));
    $arquivo = __DIR__ . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relativo) . '.php';
    if (is_file($arquivo)) {
        require $arquivo;
    }
});

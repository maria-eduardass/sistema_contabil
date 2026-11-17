<?php
declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;
use Throwable;

/**
 * Renderiza views dentro de um layout.
 * Por que renderizar em buffer e só então imprimir: se uma view falhar no meio,
 * nada pela metade vaza para o navegador e o Router pode mostrar a página 500.
 */
final class View
{
    /** @param array<string,mixed> $dados */
    public static function exibir(string $view, array $dados = [], string $layout = 'layout/principal'): void
    {
        echo self::renderizar($view, $dados, $layout);
    }

    /** Inclui um trecho reutilizável (sem layout) dentro de outra view. @param array<string,mixed> $dados */
    public static function parcial(string $nome, array $dados = []): void
    {
        echo self::capturar($nome, $dados);
    }

    /** @param array<string,mixed> $dados */
    public static function renderizar(string $view, array $dados = [], string $layout = 'layout/principal'): string
    {
        $conteudo = self::capturar($view, $dados);
        return $layout === '' ? $conteudo : self::capturar($layout, $dados + ['conteudo' => $conteudo]);
    }

    /** @param array<string,mixed> $dados */
    private static function capturar(string $nome, array $dados): string
    {
        // Nomes de view vêm do código, mas validamos para impedir path traversal por engano.
        if (!preg_match('~^[a-z_]+(/[a-z_]+)*$~', $nome)) {
            throw new InvalidArgumentException('Nome de view inválido.');
        }
        $arquivo = RAIZ . '/app/Views/' . $nome . '.php';

        ob_start();
        try {
            (static function () use ($arquivo, $dados): void {
                extract($dados, EXTR_SKIP);
                require $arquivo;
            })();
            return (string) ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }
}

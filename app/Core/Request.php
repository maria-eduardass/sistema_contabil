<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Acesso somente-leitura à requisição. Aceita valores injetados para ser testável.
 * Por que só strings: arrays vindos de ?campo[]=x são ignorados, evitando erros de tipo.
 */
final class Request
{
    private string $metodo;
    /** @var array<string,mixed> */
    private array $get;
    /** @var array<string,mixed> */
    private array $post;

    /**
     * @param array<string,mixed>|null $get
     * @param array<string,mixed>|null $post
     */
    public function __construct(?string $metodo = null, ?array $get = null, ?array $post = null)
    {
        $this->metodo = strtoupper($metodo ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $this->get = $get ?? $_GET;
        $this->post = $post ?? $_POST;
    }

    public function metodo(): string
    {
        return $this->metodo;
    }

    /** Rota solicitada; sem ?rota= cai na página inicial. */
    public function rota(): string
    {
        return $this->get('rota') ?? 'inicio/index';
    }

    public function get(string $chave): ?string
    {
        return is_string($this->get[$chave] ?? null) ? $this->get[$chave] : null;
    }

    public function post(string $chave): ?string
    {
        return is_string($this->post[$chave] ?? null) ? $this->post[$chave] : null;
    }

    /** Id numérico da URL: só inteiro positivo simples (sem sinal, ponto, zeros à esquerda). */
    public function getInt(string $chave): ?int
    {
        return self::paraInt($this->get($chave));
    }

    public function postInt(string $chave): ?int
    {
        return self::paraInt($this->post($chave));
    }

    private static function paraInt(?string $valor): ?int
    {
        return $valor !== null && preg_match('/^[1-9]\d{0,9}\z/', $valor) ? (int) $valor : null;
    }
}

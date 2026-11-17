<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Despacha ?rota=controller/acao usando uma tabela fixa (whitelist).
 * Por que whitelist: nenhuma classe ou arquivo é escolhido a partir do input do usuário.
 */
final class Router
{
    private const MENSAGENS = [
        403 => 'Acesso negado.',
        404 => 'Página não encontrada.',
        405 => 'Método não permitido.',
        500 => 'Erro interno. Tente novamente mais tarde.',
    ];

    /** @param array<string,array<string,array{0:class-string,1:string,2?:string}>> $rotas rota => [MÉTODO => [Controller, ação, acesso]] */
    public function __construct(private array $rotas)
    {
    }

    public function despachar(Request $req): void
    {
        View::compartilhar('rotaAtual', $req->rota()); // o menu lateral marca a página atual
        try {
            $this->executar($req);
        } catch (HttpException $e) {
            $this->erro($e->codigoHttp());
        } catch (Throwable $e) {
            Logger::erro($e);
            $this->erro(500); // sem detalhes técnicos para o usuário
        }
    }

    private function executar(Request $req): void
    {
        $rota = $req->rota();
        if (!preg_match('~^[a-z]+/[a-z]+$~', $rota) || !isset($this->rotas[$rota])) {
            throw new HttpException(404);
        }

        $porMetodo = $this->rotas[$rota];
        if (!isset($porMetodo[$req->metodo()])) {
            header('Allow: ' . implode(', ', array_keys($porMetodo)));
            throw new HttpException(405);
        }

        // Sem o 3º item, a rota exige login (seguro por padrão: é preciso declarar 'publico' de propósito).
        $entrada = $porMetodo[$req->metodo()];
        $classe = $entrada[0];
        $acao = $entrada[1];
        $acesso = $entrada[2] ?? 'logado';

        $decisao = Guarda::decidir($acesso, Auth::usuario());
        if ($decisao === Guarda::LOGIN) {
            redirecionar('auth/login');
        }
        if ($decisao === Guarda::NEGAR) {
            throw new HttpException(403);
        }

        // CSRF em todo POST (formulário ou fetch com cabeçalho X-CSRF-Token).
        if ($req->metodo() === 'POST') {
            Csrf::exigir($req->post('csrf_token') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null));
        }

        (new $classe())->$acao($req);
    }

    private function erro(int $codigo): void
    {
        http_response_code($codigo);
        View::exibir('erros/erro', [
            'titulo' => 'Erro ' . $codigo,
            'codigo' => $codigo,
            'mensagem' => self::MENSAGENS[$codigo] ?? self::MENSAGENS[500],
        ]);
    }
}

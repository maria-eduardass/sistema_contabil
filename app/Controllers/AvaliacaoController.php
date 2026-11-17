<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Json;
use App\Core\Request;
use App\Core\View;
use App\Models\Avaliacao;
use App\Services\AvaliacaoResumo;
use App\Services\ValidadorAvaliacao;

/** Avaliação do sistema (nota de 1 a 5) feita pelo modal, para o teste com o cliente final. */
final class AvaliacaoController
{
    /** Chamado por fetch (assets/js/avaliacao.js). O CSRF já foi exigido pelo Router, como em todo POST. */
    public function salvar(Request $req): void
    {
        $r = ValidadorAvaliacao::validar([
            'nota' => $req->post('nota'),
            'comentario' => $req->post('comentario'),
        ]);

        if ($r['erros'] !== []) {
            Json::enviar(['ok' => false, 'erros' => $r['erros']], 422);
            return;
        }

        // Quem avaliou vem da SESSÃO, nunca do formulário.
        (new Avaliacao(Database::conexao()))->criar((int) Auth::usuario()['id'], $r['dados']['nota'], $r['dados']['comentario']);
        Json::enviar(['ok' => true, 'mensagem' => 'Obrigado pela sua avaliação!'], 201);
    }

    /** Resultados para o contador: média, distribuição das notas e os comentários. */
    public function index(Request $req): void
    {
        $modelo = new Avaliacao(Database::conexao());
        View::exibir('avaliacoes/index', [
            'titulo' => 'Avaliações',
            'resumo' => AvaliacaoResumo::de($modelo->distribuicao()),
            'avaliacoes' => $modelo->listarRecentes(50),
        ]);
    }
}

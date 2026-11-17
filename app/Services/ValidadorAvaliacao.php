<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;

/**
 * Valida a avaliação do sistema. Pura. Só nota e comentário são lidos do formulário:
 * quem avaliou vem da sessão e a data é gravada pelo banco, então ninguém pode forjá-los.
 */
final class ValidadorAvaliacao
{
    /**
     * @param array<string,mixed> $entrada
     * @return array{dados:array{nota:int,comentario:?string},erros:array<string,string>}
     */
    public static function validar(array $entrada): array
    {
        $nota = is_string($entrada['nota'] ?? null) ? Validator::nota($entrada['nota']) : null; // sem trim: " 5" é inválido
        $comentario = is_string($entrada['comentario'] ?? null) ? trim($entrada['comentario']) : '';

        $erros = [];
        if ($nota === null) {
            $erros['nota'] = 'Escolha uma nota de 1 a 5.';
        }
        if (!Validator::texto($comentario, 0, 500)) {
            $erros['comentario'] = 'O comentário pode ter no máximo 500 caracteres.';
        }

        return [
            'dados' => ['nota' => $nota ?? 0, 'comentario' => $comentario === '' ? null : $comentario],
            'erros' => $erros,
        ];
    }
}

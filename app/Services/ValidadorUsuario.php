<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;

/**
 * Valida o cadastro de usuário cliente. Pura (sem banco): a checagem de e-mail duplicado
 * fica no controller. A senha segue em texto até o model aplicar o hash — nunca é gravada assim.
 */
final class ValidadorUsuario
{
    /** O bcrypt só usa os 72 primeiros bytes; aceitar mais daria falsa sensação de segurança. */
    private const SENHA_MAX_BYTES = 72;

    /**
     * @param array<string,mixed> $entrada
     * @param bool $exigirSenhaForte false = qualquer senha não vazia (cadastro público escolar)
     * @return array{dados:array{nome:string,email:string,senha:string},erros:array<string,string>}
     */
    public static function validar(array $entrada, bool $exigirSenhaForte = true): array
    {
        $texto = static fn (string $campo): string => is_string($entrada[$campo] ?? null) ? $entrada[$campo] : '';

        $dados = [
            'nome' => trim($texto('nome')),
            'email' => mb_strtolower(trim($texto('email'))),
            'senha' => $texto('senha'), // sem trim: espaços podem fazer parte da senha
        ];

        $erros = [];
        if (!Validator::texto($dados['nome'], 2, 120)) {
            $erros['nome'] = 'Informe o nome (2 a 120 caracteres).';
        }
        if (!Validator::email($dados['email'])) {
            $erros['email'] = 'E-mail inválido.';
        }
        if ($exigirSenhaForte && !Validator::senhaForte($dados['senha'])) {
            $erros['senha'] = 'A senha deve ter pelo menos 8 caracteres, com letras e números.';
        } elseif (!$exigirSenhaForte && $dados['senha'] === '') {
            $erros['senha'] = 'Informe uma senha.'; // senha livre: qualquer coisa, menos vazia
        } elseif (strlen($dados['senha']) > self::SENHA_MAX_BYTES) {
            $erros['senha'] = 'A senha pode ter no máximo 72 caracteres.';
        }

        return ['dados' => $dados, 'erros' => $erros];
    }
}

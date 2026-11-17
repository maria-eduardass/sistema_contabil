<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class Usuario
{
    /**
     * Hash válido de uma senha que ninguém conhece. Usado quando o e-mail não existe, para
     * o tempo de resposta ser igual ao de uma senha errada (evita descobrir e-mails cadastrados).
     */
    private const HASH_FALSO = '$2y$10$4vLXk4TZ5ZXte.rNrD/IUeSGeCMRUhwgu/yKRltNVi6rzJWDUG/4O';

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Devolve o usuário ativo se e-mail e senha conferem; null caso contrário
     * (sem diferenciar o motivo, de propósito).
     *
     * @return array{id:int,nome:string,email:string,perfil:string,empresa_id:?string}|null
     */
    public function autenticar(string $email, string $senha): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, nome, email, senha_hash, perfil, empresa_id FROM usuarios WHERE email = :email AND ativo = 1'
        );
        $stmt->execute(['email' => $email]);
        $usuario = $stmt->fetch();

        $confere = password_verify($senha, $usuario['senha_hash'] ?? self::HASH_FALSO);
        if (!$usuario || !$confere) {
            return null;
        }
        unset($usuario['senha_hash']);

        return $usuario;
    }

    /** O e-mail é único no sistema inteiro (a collation do banco ignora maiúsculas). */
    public function emailExiste(string $email): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE email = :email');
        $stmt->execute(['email' => $email]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Cria um usuário do perfil cliente, preso a uma empresa. O hash é aplicado AQUI, para
     * nenhum chamador conseguir gravar senha em texto puro por esquecimento.
     *
     * CPF e telefone são opcionais e de texto livre (projeto escolar): vão como a pessoa digitou, sem
     * validação de dígitos. O limite de 30 caracteres é o da coluna; o banco em modo estrito recusa mais que isso.
     */
    public function criarCliente(int $empresaId, string $nome, string $email, string $senha, ?string $cpf = null, ?string $telefone = null): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO usuarios (empresa_id, nome, email, cpf, telefone, senha_hash, perfil)
             VALUES (:empresa_id, :nome, :email, :cpf, :telefone, :senha_hash, 'cliente')"
        );
        $stmt->execute([
            'empresa_id' => $empresaId,
            'nome' => $nome,
            'email' => $email,
            'cpf' => $cpf,
            'telefone' => $telefone,
            'senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array{id:int,nome:string,email:string,cpf:?string,telefone:?string,ativo:int}> Nunca inclui o hash. */
    public function listarPorEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, nome, email, cpf, telefone, ativo FROM usuarios WHERE empresa_id = :id ORDER BY nome');
        $stmt->execute(['id' => $empresaId]);

        return $stmt->fetchAll();
    }
}

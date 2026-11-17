<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class Avaliacao
{
    public function __construct(private PDO $pdo)
    {
    }

    /** Cada avaliação fica registrada (não substitui a anterior): serve de histórico do teste com o cliente. */
    public function criar(int $usuarioId, int $nota, ?string $comentario): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO avaliacoes (usuario_id, nota, comentario) VALUES (:usuario, :nota, :comentario)');
        $stmt->execute(['usuario' => $usuarioId, 'nota' => $nota, 'comentario' => $comentario]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array{nome:string,perfil:string,nota:int,comentario:?string,criada_em:string}> Mais recentes primeiro. */
    public function listarRecentes(int $limite = 50): array
    {
        // LIMIT não aceita bind em modo nativo de forma portável; o int tipado + max/min garante um valor seguro.
        $limite = max(1, min(500, $limite));
        return $this->pdo->query(
            "SELECT a.id, u.nome, u.perfil, a.nota, a.comentario, a.criada_em
             FROM avaliacoes a
             JOIN usuarios u ON u.id = a.usuario_id
             ORDER BY a.criada_em DESC, a.id DESC
             LIMIT $limite"
        )->fetchAll();
    }

    /** @return array<int,int> nota => votos, sempre com as 5 notas */
    public function distribuicao(): array
    {
        $distribuicao = array_fill(1, 5, 0);
        foreach ($this->pdo->query('SELECT nota, COUNT(*) AS votos FROM avaliacoes GROUP BY nota')->fetchAll() as $linha) {
            $distribuicao[(int) $linha['nota']] = (int) $linha['votos'];
        }

        return $distribuicao;
    }
}

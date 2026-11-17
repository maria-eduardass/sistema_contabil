<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class Empresa
{
    private const COLUNAS = 'id, razao_social, cnpj, regime_tributario, email_contato, ativa';

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Lista empresas, ativas primeiro. A busca procura no nome e (se tiver dígitos) no CNPJ.
     * O SQL é montado só com trechos fixos; todo texto do usuário vai por parâmetro (bind).
     *
     * @return list<array<string,mixed>>
     */
    public function listar(string $busca = ''): array
    {
        $sql = 'SELECT ' . self::COLUNAS . ' FROM empresas';
        $params = [];

        $busca = trim($busca);
        if ($busca !== '') {
            $condicoes = ["razao_social LIKE :nome ESCAPE '|'"];
            $params['nome'] = '%' . self::escaparLike($busca) . '%';
            $digitos = preg_replace('/\D/', '', $busca) ?? '';
            if ($digitos !== '') {
                $condicoes[] = 'cnpj LIKE :cnpj';
                $params['cnpj'] = '%' . $digitos . '%';
            }
            $sql .= ' WHERE ' . implode(' OR ', $condicoes);
        }

        $stmt = $this->pdo->prepare($sql . ' ORDER BY ativa DESC, razao_social');
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function listarAtivas(): array
    {
        return $this->pdo->query('SELECT ' . self::COLUNAS . ' FROM empresas WHERE ativa = 1 ORDER BY razao_social')->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT ' . self::COLUNAS . ' FROM empresas WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /** $ignorarId permite editar uma empresa sem "colidir" com o próprio CNPJ. */
    public function cnpjExiste(string $cnpj, ?int $ignorarId = null): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM empresas WHERE cnpj = :cnpj AND id <> :id');
        $stmt->execute(['cnpj' => $cnpj, 'id' => $ignorarId ?? 0]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** @param array{razao_social:string,cnpj:string,regime_tributario:string,email_contato:string} $dados */
    public function criar(array $dados): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO empresas (razao_social, cnpj, regime_tributario, email_contato)
             VALUES (:razao_social, :cnpj, :regime_tributario, :email_contato)'
        );
        $stmt->execute($dados);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array{razao_social:string,cnpj:string,regime_tributario:string,email_contato:string} $dados */
    public function atualizar(int $id, array $dados): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE empresas SET razao_social = :razao_social, cnpj = :cnpj,
             regime_tributario = :regime_tributario, email_contato = :email_contato WHERE id = :id'
        );
        $stmt->execute($dados + ['id' => $id]);
    }

    /** Inativa em vez de excluir: obrigações e histórico continuam consultáveis. */
    public function inativar(int $id): void
    {
        $this->pdo->prepare('UPDATE empresas SET ativa = 0 WHERE id = :id')->execute(['id' => $id]);
    }

    /** Neutraliza % _ e o próprio caractere de escape, para a busca ser sempre literal. */
    private static function escaparLike(string $texto): string
    {
        return str_replace(['|', '%', '_'], ['||', '|%', '|_'], $texto);
    }
}

<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class Obrigacao
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Já existe esta obrigação (empresa + tipo + competência)? Serve para dar uma mensagem
     * amigável; quem garante a unicidade de verdade é o UNIQUE do banco.
     */
    public function existe(int $empresaId, int $tipoId, string $competencia): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM obrigacoes WHERE empresa_id = :empresa AND tipo_obrigacao_id = :tipo AND competencia = :competencia'
        );
        $stmt->execute(['empresa' => $empresaId, 'tipo' => $tipoId, 'competencia' => $competencia]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @param array{empresa_id:int,tipo_obrigacao_id:int,competencia:string,data_vencimento:string,valor_centavos:int,observacao:?string,status:string} $d
     */
    public function criar(array $d): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO obrigacoes (empresa_id, tipo_obrigacao_id, competencia, data_vencimento, valor, status, observacao)
             VALUES (:empresa_id, :tipo_obrigacao_id, :competencia, :data_vencimento, :valor, :status, :observacao)'
        );
        $stmt->execute([
            'empresa_id' => $d['empresa_id'],
            'tipo_obrigacao_id' => $d['tipo_obrigacao_id'],
            'competencia' => $d['competencia'],
            'data_vencimento' => $d['data_vencimento'],
            // Centavos inteiros -> "1234.56": o DECIMAL recebe o valor exato, sem passar por float.
            'valor' => sprintf('%d.%02d', intdiv($d['valor_centavos'], 100), $d['valor_centavos'] % 100),
            'status' => $d['status'],
            'observacao' => $d['observacao'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string,mixed>|null */
    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, empresa_id, status, data_vencimento, data_pagamento FROM obrigacoes WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /** Pendentes com vencimento anterior a $hoje (Y-m-d) viram atrasadas. Devolve quantas mudaram. */
    public function marcarAtrasadas(string $hoje): int
    {
        $stmt = $this->pdo->prepare("UPDATE obrigacoes SET status = 'atrasado' WHERE status = 'pendente' AND data_vencimento < :hoje");
        $stmt->execute(['hoje' => $hoje]);

        return $stmt->rowCount();
    }

    /**
     * Marca como paga SÓ SE ainda não estiver paga, numa única instrução atômica.
     * Por que não "ler e depois gravar": dois cliques simultâneos poderiam passar ambos
     * na leitura e o segundo sobrescreveria a data do primeiro pagamento.
     *
     * @return bool false se nada mudou (não existe ou já estava paga)
     */
    public function marcarComoPaga(int $id, string $dataPagamento): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE obrigacoes SET status = 'pago', data_pagamento = :data WHERE id = :id AND status <> 'pago'"
        );
        $stmt->execute(['data' => $dataPagamento, 'id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /** Exclui só se NÃO estiver paga: pagamentos são histórico fiscal. @return bool false se nada foi excluído */
    public function excluirSeNaoPaga(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM obrigacoes WHERE id = :id AND status <> 'pago'");
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Obrigações NÃO pagas de todas as empresas ATIVAS, agrupadas por empresa, numa consulta só.
     * Por que uma consulta e não uma por empresa: o ranking do contador ficaria mais lento a
     * cada empresa cadastrada (problema "N+1").
     *
     * @return array<int,list<array<string,mixed>>>
     */
    public function listarEmAbertoDeEmpresasAtivas(): array
    {
        $linhas = $this->pdo->query(
            "SELECT o.empresa_id, o.id, t.sigla AS tipo_sigla, o.data_vencimento,
                    CAST(o.valor * 100 AS UNSIGNED) AS valor_centavos, o.status
             FROM obrigacoes o
             JOIN tipos_obrigacao t ON t.id = o.tipo_obrigacao_id
             JOIN empresas e ON e.id = o.empresa_id
             WHERE e.ativa = 1 AND o.status <> 'pago'
             ORDER BY o.empresa_id, o.data_vencimento, o.id"
        )->fetchAll();

        $porEmpresa = [];
        foreach ($linhas as $linha) {
            $porEmpresa[(int) $linha['empresa_id']][] = $linha;
        }

        return $porEmpresa;
    }

    /**
     * Em aberto primeiro (por vencimento mais próximo), pagas por último.
     * valor_centavos sai do banco já como inteiro (DECIMAL * 100 é exato), pronto para o MultaService.
     *
     * @return list<array<string,mixed>>
     */
    public function listarPorEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT o.id, o.tipo_obrigacao_id, t.sigla AS tipo_sigla, t.nome AS tipo_nome, o.competencia,
                    o.data_vencimento, CAST(o.valor * 100 AS UNSIGNED) AS valor_centavos, o.status,
                    o.data_pagamento, o.observacao
             FROM obrigacoes o
             JOIN tipos_obrigacao t ON t.id = o.tipo_obrigacao_id
             WHERE o.empresa_id = :empresa
             ORDER BY (o.status = 'pago'), o.data_vencimento"
        );
        $stmt->execute(['empresa' => $empresaId]);

        return $stmt->fetchAll();
    }
}

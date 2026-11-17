<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;
use App\Models\Obrigacao;
use DateTimeImmutable;

/**
 * Ciclo de vida do status de uma obrigação: pendente -> atrasado -> pago.
 *
 * Por que sincronizar ao abrir as telas e não por cron: o XAMPP não tem agendador, e a operação
 * é idempotente e barata (um UPDATE indexado), então rodar a cada carga é seguro.
 */
final class StatusService
{
    public function __construct(private Obrigacao $obrigacoes)
    {
    }

    /** Pendentes vencidas viram atrasadas. Atraso só começa no dia SEGUINTE ao vencimento. @return int quantas mudaram */
    public function sincronizar(DateTimeImmutable $hoje): int
    {
        return $this->obrigacoes->marcarAtrasadas($hoje->format('Y-m-d'));
    }

    /**
     * @throws RegraDeNegocio 422 data inválida/futura, 404 inexistente, 409 já paga
     */
    public function pagar(int $id, string $dataPagamento, DateTimeImmutable $hoje): void
    {
        if (!Validator::dataNaoFutura($dataPagamento, $hoje)) {
            throw new RegraDeNegocio('Informe uma data de pagamento válida, que não seja futura.', 422);
        }
        if ($this->obrigacoes->marcarComoPaga($id, $dataPagamento)) {
            return;
        }
        // Nada mudou: descobre o motivo só agora (o caminho feliz faz uma única consulta).
        throw $this->obrigacoes->buscar($id) === null
            ? new RegraDeNegocio('Obrigação não encontrada.', 404)
            : new RegraDeNegocio('Esta obrigação já está paga.', 409);
    }

    /** @throws RegraDeNegocio 404 inexistente, 409 paga (histórico não se apaga) */
    public function excluir(int $id): void
    {
        if ($this->obrigacoes->excluirSeNaoPaga($id)) {
            return;
        }
        throw $this->obrigacoes->buscar($id) === null
            ? new RegraDeNegocio('Obrigação não encontrada.', 404)
            : new RegraDeNegocio('Obrigações pagas não podem ser excluídas.', 409);
    }
}

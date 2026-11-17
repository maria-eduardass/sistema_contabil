<?php
declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Monta a mensagem de cobrança e o link do WhatsApp ("Cobrar no WhatsApp").
 *
 * Por que só um link wa.me e não uma API: abrir o WhatsApp com o texto pronto é uma ação do
 * próprio contador (ele revisa e aperta enviar), sem integração paga nem dado enviado pelo
 * servidor. O destinatário é escolhido no WhatsApp, porque o cadastro da empresa guarda só e-mail.
 *
 * O host do link é FIXO (wa.me): nenhum dado do usuário (nome da empresa etc.) vai para a
 * parte do endereço, somente para o texto, já codificado.
 */
final class MensagemCobranca
{
    private const BASE = 'https://wa.me/?text=';

    /**
     * @param array<string,mixed> $guia tipo_sigla, tipo_nome?, competencia, data_vencimento, valor_centavos, status?
     * @throws InvalidArgumentException guia paga (não há o que cobrar) ou data inválida
     */
    public static function montar(string $empresa, array $guia, DateTimeImmutable $hoje): string
    {
        if (($guia['status'] ?? '') === 'pago') {
            throw new InvalidArgumentException('Guia paga não gera cobrança.');
        }

        $empresa = trim((string) preg_replace('/\s+/u', ' ', $empresa)); // uma linha só: nome não "forja" mensagem
        $dias = TermometroRisco::diasParaVencer((string) $guia['data_vencimento'], $hoje); // negativo = atrasada
        $valor = (int) $guia['valor_centavos'];

        $nome = trim((string) ($guia['tipo_nome'] ?? ''));
        $descricao = (string) $guia['tipo_sigla'] . ($nome !== '' ? ' - ' . $nome : '');
        $linhas = [
            'Olá! Aqui é do escritório de contabilidade.',
            '',
        ];

        if ($dias < 0) {
            $atraso = -$dias;
            $linhas[] = "Identificamos uma guia em atraso da empresa *$empresa*:";
            $linhas[] = "• Guia: $descricao (competência " . \formatarCompetencia((string) $guia['competencia']) . ')';
            $linhas[] = '• Vencimento: ' . \formatarData((string) $guia['data_vencimento']) . " (atrasada há $atraso " . ($atraso === 1 ? 'dia' : 'dias') . ')';
            if ($valor > 0) {
                $total = $valor + MultaService::calcular($valor, $atraso)['total'];
                $linhas[] = '• Valor original: ' . \formatarCentavos($valor);
                $linhas[] = '• Valor atualizado com multa e juros: ' . \formatarCentavos($total);
                $linhas[] = '';
                $linhas[] = 'Podemos ajudar a regularizar? O valor atualizado é uma estimativa e pode variar até o pagamento.';
            } else {
                $linhas[] = '• Valor: sem valor a pagar (declaração)';
                $linhas[] = '';
                $linhas[] = 'Podemos ajudar a regularizar a entrega?';
            }
        } else {
            $prazo = match ($dias) {
                0 => 'vence hoje',
                1 => 'vence amanhã',
                default => "vence em $dias dias",
            };
            $linhas[] = "Lembrete sobre a empresa *$empresa*:";
            $linhas[] = "• Guia: $descricao (competência " . \formatarCompetencia((string) $guia['competencia']) . ')';
            $linhas[] = '• Vencimento: ' . \formatarData((string) $guia['data_vencimento']) . " ($prazo)";
            $linhas[] = $valor > 0 ? '• Valor: ' . \formatarCentavos($valor) : '• Valor: sem valor a pagar (declaração)';
            $linhas[] = '';
            $linhas[] = 'Posso ajudar a deixar tudo certo antes do prazo?';
        }

        return implode("\n", $linhas);
    }

    /**
     * Acrescenta a cada linha da lista de obrigações o link do WhatsApp e o valor atualizado.
     * Guia paga não recebe nada (não há o que cobrar). O valor atualizado só existe para atraso.
     *
     * @param list<array<string,mixed>> $linhas formato de Obrigacao::listarPorEmpresa
     * @return list<array<string,mixed>> as mesmas linhas + link_whatsapp (?string) + valor_atualizado_centavos (?int)
     */
    public static function enriquecerLista(array $linhas, string $empresa, DateTimeImmutable $hoje): array
    {
        $resultado = [];
        foreach ($linhas as $linha) {
            $linha['link_whatsapp'] = null;
            $linha['valor_atualizado_centavos'] = null;

            if (($linha['status'] ?? '') !== 'pago') {
                $linha['link_whatsapp'] = self::linkWhatsApp(self::montar($empresa, $linha, $hoje));
                $dias = TermometroRisco::diasParaVencer((string) $linha['data_vencimento'], $hoje);
                if ($dias < 0 && (int) $linha['valor_centavos'] > 0) {
                    $valor = (int) $linha['valor_centavos'];
                    $linha['valor_atualizado_centavos'] = $valor + MultaService::calcular($valor, -$dias)['total'];
                }
            }
            $resultado[] = $linha;
        }

        return $resultado;
    }

    /** rawurlencode troca espaço, quebra de linha, & # ? " < > por %XX: nada escapa do parâmetro text=. */
    public static function linkWhatsApp(string $mensagem): string
    {
        return self::BASE . rawurlencode($mensagem);
    }
}

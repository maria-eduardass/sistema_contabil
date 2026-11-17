<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Freio contra tentativa e erro de senha: N falhas seguidas bloqueiam o login por um tempo.
 *
 * O estado fica num array recebido por referência (no uso real, um pedaço da $_SESSION),
 * e "agora" é injetado, o que torna a regra testável sem sessão nem relógio.
 * Limitação assumida no MVP: por ser por sessão, quem descarta o cookie recomeça do zero;
 * um controle por IP/e-mail exigiria uma tabela extra no banco.
 */
final class LimiteTentativas
{
    /** @var array<string,int> */
    private array $estado;

    /** @param array<string,int> $estado */
    public function __construct(array &$estado, private int $maximo = 5, private int $bloqueioSegundos = 300)
    {
        $this->estado = &$estado;
    }

    public function bloqueado(int $agora): bool
    {
        return $this->segundosRestantes($agora) > 0;
    }

    public function segundosRestantes(int $agora): int
    {
        $ate = $this->estado['bloqueado_ate'] ?? 0;
        if ($ate === 0) {
            return 0;
        }
        if ($agora >= $ate) {
            $this->zerar(); // prazo vencido: recomeça com contador limpo
            return 0;
        }
        return $ate - $agora;
    }

    public function registrarFalha(int $agora): void
    {
        if ($this->bloqueado($agora)) {
            return; // já bloqueado: não prolonga o prazo
        }
        $falhas = ($this->estado['falhas'] ?? 0) + 1;
        if ($falhas >= $this->maximo) {
            $this->estado = ['falhas' => 0, 'bloqueado_ate' => $agora + $this->bloqueioSegundos];
            return;
        }
        $this->estado['falhas'] = $falhas;
    }

    public function zerar(): void
    {
        $this->estado = [];
    }
}

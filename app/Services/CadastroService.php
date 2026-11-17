<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Empresa;
use App\Models\Usuario;
use PDO;
use PDOException;

/**
 * Autocadastro: cria uma EMPRESA nova e o usuário CLIENTE dono dela, de uma vez só.
 *
 * Por que é seguro abrir esta porta sem login:
 *  - O perfil é fixo (cliente, definido em Usuario::criarCliente). Ninguém vira contador por aqui.
 *  - A pessoa só entra na empresa que ela própria cria. Um CNPJ que já existe é RECUSADO, em vez de
 *    "anexar" a pessoa a ele: do contrário bastaria digitar o CNPJ de um cliente do escritório para ver
 *    os dados dele. Clientes que já existem recebem o acesso pelo contador.
 *  - Tudo numa transação: nunca sobra uma empresa sem dono nem um usuário sem empresa.
 */
final class CadastroService
{
    private const MENSAGEM_CNPJ = 'Já existe uma empresa com este CNPJ. Se ela já é cliente do escritório, peça ao seu contador para criar o seu acesso.';
    private const MENSAGEM_EMAIL = 'Já existe uma conta com este e-mail. Tente entrar ou use outro e-mail.';
    private const MYSQL_DUPLICADO = 1062;

    /**
     * @param array<string,mixed> $dados saída de ValidadorCadastro::validar() (já normalizada)
     * @return array{usuario:?array{id:int,nome:string,perfil:string,empresa_id:int},erros:array<string,string>}
     *         erros vazio = sucesso; "usuario" traz o que o login precisa
     * @throws PDOException falha inesperada do banco (a transação é desfeita antes)
     */
    public static function cadastrar(PDO $pdo, array $dados): array
    {
        $empresas = new Empresa($pdo);
        $usuarios = new Usuario($pdo);

        // Conferência amigável antes de gravar; a garantia real são os UNIQUE do banco (ver o catch).
        $erros = [];
        if ($empresas->cnpjExiste((string) $dados['cnpj'])) {
            $erros['cnpj'] = self::MENSAGEM_CNPJ;
        }
        if ($usuarios->emailExiste((string) $dados['email'])) {
            $erros['email'] = self::MENSAGEM_EMAIL;
        }
        if ($erros !== []) {
            return ['usuario' => null, 'erros' => $erros];
        }

        // A partir daqui são DOIS INSERTs que só fazem sentido juntos (a empresa e o dono dela). A transação
        // garante tudo-ou-nada: se o segundo falhar, o primeiro é desfeito e não sobra empresa sem usuário.
        $pdo->beginTransaction();
        try {
            // O perfil NÃO vem de $dados: criarCliente grava sempre "cliente", e a empresa é a que acabou de ser criada.
            $empresaId = $empresas->criar([
                'razao_social' => (string) $dados['razao_social'],
                'cnpj' => (string) $dados['cnpj'],
                'regime_tributario' => (string) $dados['regime_tributario'],
                'email_contato' => (string) $dados['email_contato'],
            ]);
            $usuarioId = $usuarios->criarCliente(
                $empresaId,
                (string) $dados['nome'],
                (string) $dados['email'],
                (string) $dados['senha'],
                isset($dados['cpf']) ? (string) $dados['cpf'] : null,       // opcionais, texto livre
                isset($dados['telefone']) ? (string) $dados['telefone'] : null
            );
            $pdo->commit();
        } catch (PDOException $e) {
            // Desfaz o que já tinha sido gravado (a empresa), mesmo quando o erro é inesperado; só então decide o que fazer.
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (($e->errorInfo[1] ?? 0) === self::MYSQL_DUPLICADO) {
                // Corrida: duas pessoas passaram na conferência ao mesmo tempo; o UNIQUE decidiu. A mensagem do banco
                // cita o nome da chave (uq_empresas_cnpj ou uq_usuarios_email), o que revela qual campo repetiu.
                $campo = str_contains((string) ($e->errorInfo[2] ?? ''), 'cnpj') ? 'cnpj' : 'email';
                return ['usuario' => null, 'erros' => [$campo => $campo === 'cnpj' ? self::MENSAGEM_CNPJ : self::MENSAGEM_EMAIL]];
            }
            throw $e;
        }

        // Devolve só o que o login precisa (nunca o hash da senha).
        return [
            'usuario' => ['id' => $usuarioId, 'nome' => (string) $dados['nome'], 'perfil' => 'cliente', 'empresa_id' => $empresaId],
            'erros' => [],
        ];
    }
}

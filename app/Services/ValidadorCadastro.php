<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;

/**
 * Valida o formulário "Criar conta": os dados da pessoa (ValidadorUsuario) + os dados da empresa
 * dela (ValidadorEmpresa) + CPF e telefone opcionais + a confirmação da senha. Pura (sem banco): CNPJ e
 * e-mail já usados são conferidos depois, pelo CadastroService.
 *
 * PROJETO ESCOLAR: este formulário é deliberadamente permissivo, a pedido do projeto.
 *  - Senha: qualquer coisa não vazia (sem exigir letra, número ou 8 caracteres). Fica só o limite técnico
 *    de 72 bytes, porque o bcrypt ignoraria o que passasse disso.
 *  - CNPJ: qualquer sequência de 14 números (sem conferir o dígito verificador). O banco exige 14 dígitos.
 *  - CPF e telefone: opcionais e de texto livre, só com o limite de tamanho da coluna.
 * O cadastro feito pelo CONTADOR (empresas/usuarios) continua com as regras completas. Para endurecer este
 * formulário em produção basta trocar os dois "false" abaixo por "true" e validar CPF/telefone de verdade.
 *
 * Só os campos do formulário são lidos. Perfil, situação e empresa NÃO existem aqui: quem se cadastra
 * é sempre cliente e só enxerga a empresa que acabou de criar (ver CadastroService).
 */
final class ValidadorCadastro
{
    private const LIMITE_TEXTO_LIVRE = 30; // tamanho das colunas cpf e telefone

    /**
     * @param array<string,mixed> $entrada
     * @return array{dados:array<string,?string>,erros:array<string,string>}
     */
    public static function validar(array $entrada): array
    {
        $texto = static fn (string $campo): string => is_string($entrada[$campo] ?? null) ? $entrada[$campo] : '';

        $usuario = ValidadorUsuario::validar([
            'nome' => $texto('nome'),
            'email' => $texto('email'),
            'senha' => $texto('senha'),
        ], false); // false: senha livre
        // O e-mail da conta é também o contato da empresa: um campo a menos no formulário.
        $empresa = ValidadorEmpresa::validar([
            'razao_social' => $texto('razao_social'),
            'cnpj' => $texto('cnpj'),
            'regime_tributario' => $texto('regime_tributario'),
            'email_contato' => $usuario['dados']['email'],
        ], false); // false: não confere o dígito do CNPJ

        $erros = $usuario['erros'];
        foreach ($empresa['erros'] as $campo => $mensagem) {
            if ($campo !== 'email_contato') { // esse campo não existe na tela; o erro já aparece em "email"
                $erros[$campo] = $mensagem;
            }
        }
        if ($texto('senha') !== $texto('senha_confirmacao')) {
            $erros['senha_confirmacao'] = 'As senhas não coincidem.';
        }

        $cpf = self::textoLivre($texto('cpf'), 'O CPF', $erros, 'cpf');
        $telefone = self::textoLivre($texto('telefone'), 'O telefone', $erros, 'telefone');

        return [
            'dados' => [
                'nome' => $usuario['dados']['nome'],
                'email' => $usuario['dados']['email'],
                'cpf' => $cpf,
                'telefone' => $telefone,
                'senha' => $usuario['dados']['senha'],
                'razao_social' => $empresa['dados']['razao_social'],
                'cnpj' => $empresa['dados']['cnpj'],
                'regime_tributario' => $empresa['dados']['regime_tributario'],
                'email_contato' => $empresa['dados']['email_contato'],
            ],
            'erros' => $erros,
        ];
    }

    /**
     * Campo opcional de texto livre: vazio vira null; o resto vai como veio (com trim). Só o tamanho é conferido.
     *
     * @param array<string,string> $erros
     */
    private static function textoLivre(string $valor, string $rotulo, array &$erros, string $campo): ?string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }
        if (!Validator::texto($valor, 1, self::LIMITE_TEXTO_LIVRE)) {
            $erros[$campo] = "$rotulo pode ter no máximo " . self::LIMITE_TEXTO_LIVRE . ' caracteres.';
        }

        return $valor;
    }
}

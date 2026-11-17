<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Valida o formulário "Criar conta": os dados da pessoa (ValidadorUsuario) + os dados da empresa
 * dela (ValidadorEmpresa) + a confirmação da senha. Pura (sem banco): CNPJ e e-mail já usados são
 * conferidos depois, pelo CadastroService.
 *
 * Reaproveita os dois validadores existentes de propósito: as regras de CNPJ, senha, e-mail e regime
 * são as mesmas do cadastro feito pelo contador, e assim nunca divergem.
 *
 * Só os campos do formulário são lidos. Perfil, situação e empresa NÃO existem aqui: quem se cadastra
 * é sempre cliente e só enxerga a empresa que acabou de criar (ver CadastroService).
 */
final class ValidadorCadastro
{
    /**
     * @param array<string,mixed> $entrada
     * @return array{dados:array<string,string>,erros:array<string,string>}
     */
    public static function validar(array $entrada): array
    {
        $texto = static fn (string $campo): string => is_string($entrada[$campo] ?? null) ? $entrada[$campo] : '';

        $usuario = ValidadorUsuario::validar([
            'nome' => $texto('nome'),
            'email' => $texto('email'),
            'senha' => $texto('senha'),
        ]);
        // O e-mail da conta é também o contato da empresa: um campo a menos no formulário.
        $empresa = ValidadorEmpresa::validar([
            'razao_social' => $texto('razao_social'),
            'cnpj' => $texto('cnpj'),
            'regime_tributario' => $texto('regime_tributario'),
            'email_contato' => $usuario['dados']['email'],
        ]);

        $erros = $usuario['erros'];
        foreach ($empresa['erros'] as $campo => $mensagem) {
            if ($campo !== 'email_contato') { // esse campo não existe na tela; o erro já aparece em "email"
                $erros[$campo] = $mensagem;
            }
        }
        if ($texto('senha') !== $texto('senha_confirmacao')) {
            $erros['senha_confirmacao'] = 'As senhas não coincidem.';
        }

        return [
            'dados' => [
                'nome' => $usuario['dados']['nome'],
                'email' => $usuario['dados']['email'],
                'senha' => $usuario['dados']['senha'],
                'razao_social' => $empresa['dados']['razao_social'],
                'cnpj' => $empresa['dados']['cnpj'],
                'regime_tributario' => $empresa['dados']['regime_tributario'],
                'email_contato' => $empresa['dados']['email_contato'],
            ],
            'erros' => $erros,
        ];
    }
}

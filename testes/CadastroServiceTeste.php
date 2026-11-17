<?php
declare(strict_types=1);

require_once __DIR__ . '/_ajuda_banco.php';

use App\Models\Usuario;
use App\Services\CadastroService;

/*
 * Testes de INTEGRAÇÃO do cadastro (usam o banco de teste). Ponto importante: CadastroService abre a própria transação,
 * e o MySQL não aceita transação dentro de transação. Por isso estes testes NÃO usam o rollback externo dos outros
 * arquivos; cada um limpa o que criou (comLimpezaCadastroTeste), antes e depois, para não afetar os demais testes
 * (vários contam as 3 empresas e os 3 usuários do seed).
 */
const CNPJ_NOVO_TESTE = '45723174000110';

function dadosCadastroTeste(array $sobrescrever = []): array
{
    return $sobrescrever + [
        'nome' => 'Maria Souza',
        'email' => 'maria@padaria.test',
        'senha' => 'Senha@123',
        'razao_social' => 'Padaria da Maria Ltda',
        'cnpj' => CNPJ_NOVO_TESTE,
        'regime_tributario' => 'simples',
        'email_contato' => 'maria@padaria.test',
    ];
}

/** Estas operações abrem a PRÓPRIA transação, então os testes não usam rollback externo: limpam o que criaram. */
function limparCadastroTeste(PDO $pdo): void
{
    $pdo->exec("DELETE FROM usuarios WHERE email IN ('maria@padaria.test', 'outra@padaria.test')");
    $pdo->exec("DELETE FROM empresas WHERE cnpj = '" . CNPJ_NOVO_TESTE . "'");
}

function contagensTeste(PDO $pdo): string
{
    return (int) $pdo->query('SELECT COUNT(*) FROM empresas')->fetchColumn() . '/' . (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
}

function comLimpezaCadastroTeste(callable $fn): void
{
    $pdo = bancoDeTeste();
    limparCadastroTeste($pdo);
    try {
        $fn($pdo);
    } finally {
        limparCadastroTeste($pdo);
    }
}

testeIntegracao('Cadastro: cria a empresa e um usuário CLIENTE preso a ela', function () {
    comLimpezaCadastroTeste(function ($pdo) {
        $r = CadastroService::cadastrar($pdo, dadosCadastroTeste());
        afirmarIgual([], $r['erros']);
        afirmarIgual('cliente', $r['usuario']['perfil']);

        $empresa = $pdo->query("SELECT id, ativa, email_contato, razao_social FROM empresas WHERE cnpj = '" . CNPJ_NOVO_TESTE . "'")->fetch();
        afirmarIgual($empresa['id'], $r['usuario']['empresa_id']);
        afirmarIgual(1, (int) $empresa['ativa']);
        afirmarIgual('maria@padaria.test', $empresa['email_contato']);
        $linha = $pdo->query("SELECT perfil, empresa_id, ativo FROM usuarios WHERE email = 'maria@padaria.test'")->fetch();
        afirmarIgual(['cliente', (int) $empresa['id'], 1], [$linha['perfil'], (int) $linha['empresa_id'], (int) $linha['ativo']]);
    });
});

testeIntegracao('Cadastro: a senha é gravada como hash e a pessoa consegue entrar com ela', function () {
    comLimpezaCadastroTeste(function ($pdo) {
        CadastroService::cadastrar($pdo, dadosCadastroTeste());
        $hash = (string) $pdo->query("SELECT senha_hash FROM usuarios WHERE email = 'maria@padaria.test'")->fetchColumn();
        afirmarVerdadeiro($hash !== 'Senha@123' && str_starts_with($hash, '$2y$'));
        $logado = (new Usuario($pdo))->autenticar('maria@padaria.test', 'Senha@123');
        afirmarVerdadeiro($logado !== null);
        afirmarIgual('cliente', $logado['perfil']);
    });
});

testeIntegracao('Cadastro: perfil e empresa enviados à força são ignorados (nunca vira contador nem entra em empresa alheia)', function () {
    comLimpezaCadastroTeste(function ($pdo) {
        $r = CadastroService::cadastrar($pdo, dadosCadastroTeste(['perfil' => 'contador', 'empresa_id' => 1, 'ativo' => 0]));
        afirmarIgual('cliente', $r['usuario']['perfil']);
        afirmarVerdadeiro($r['usuario']['empresa_id'] !== 1, 'caiu na empresa 1 (Padaria do seed)');
        afirmarIgual(1, (int) $pdo->query("SELECT ativo FROM usuarios WHERE email = 'maria@padaria.test'")->fetchColumn());
    });
});

testeIntegracao('Cadastro: CNPJ já cadastrado é recusado e NÃO dá acesso à empresa existente', function () {
    comLimpezaCadastroTeste(function ($pdo) {
        $antes = contagensTeste($pdo);
        $r = CadastroService::cadastrar($pdo, dadosCadastroTeste(['cnpj' => '11222333000181'])); // Padaria do seed
        afirmarIgual(null, $r['usuario']);
        afirmarVerdadeiro(isset($r['erros']['cnpj']));
        afirmarVerdadeiro(str_contains($r['erros']['cnpj'], 'contador'), 'a mensagem orienta a falar com o contador');
        afirmarIgual($antes, contagensTeste($pdo));
    });
});

testeIntegracao('Cadastro: e-mail já usado é recusado (sem diferenciar maiúsculas) e não deixa empresa órfã', function () {
    comLimpezaCadastroTeste(function ($pdo) {
        $antes = contagensTeste($pdo);
        $r = CadastroService::cadastrar($pdo, dadosCadastroTeste(['email' => 'CONTADOR@fiscalpulse.test']));
        afirmarIgual(null, $r['usuario']);
        afirmarVerdadeiro(isset($r['erros']['email']));
        afirmarIgual($antes, contagensTeste($pdo));
        afirmarIgual(0, (int) $pdo->query("SELECT COUNT(*) FROM empresas WHERE cnpj = '" . CNPJ_NOVO_TESTE . "'")->fetchColumn());
    });
});

testeIntegracao('Cadastro: CNPJ e e-mail repetidos juntos acusam os dois campos', function () {
    comLimpezaCadastroTeste(function ($pdo) {
        $r = CadastroService::cadastrar($pdo, dadosCadastroTeste(['cnpj' => '11222333000181', 'email' => 'contador@fiscalpulse.test']));
        afirmarVerdadeiro(isset($r['erros']['cnpj']) && isset($r['erros']['email']));
    });
});

testeIntegracao('Cadastro: é atômico — se a criação do usuário falha, a empresa criada antes é desfeita', function () {
    comLimpezaCadastroTeste(function ($pdo) {
        $antes = contagensTeste($pdo);
        // Nome maior que a coluna (120): o INSERT do usuário falha DEPOIS de a empresa já ter sido inserida.
        // Só dá erro porque a conexão usa o modo estrito (ver DatabaseTeste); sem ele o nome seria cortado em silêncio.
        afirmarExcecao(PDOException::class, fn () => CadastroService::cadastrar($pdo, dadosCadastroTeste(['nome' => str_repeat('a', 400)])));
        afirmarIgual($antes, contagensTeste($pdo));
        afirmarFalso($pdo->inTransaction(), 'a transação não pode ficar aberta');
    });
});

testeIntegracao('Cadastro: duas pessoas diferentes com CNPJs diferentes convivem, cada uma só na sua empresa', function () {
    comLimpezaCadastroTeste(function ($pdo) {
        $a = CadastroService::cadastrar($pdo, dadosCadastroTeste());
        $b = CadastroService::cadastrar($pdo, dadosCadastroTeste(['email' => 'outra@padaria.test', 'cnpj' => '00000000000191', 'razao_social' => 'Outra Ltda']));
        // o CNPJ 00000000000191 é o da Clínica do seed, então o segundo cadastro é recusado
        afirmarVerdadeiro($a['usuario'] !== null);
        afirmarIgual(null, $b['usuario']);
        afirmarVerdadeiro(isset($b['erros']['cnpj']));
    });
});

// --- CPF, telefone, senha e CNPJ livres (projeto escolar) ---
// O serviço guarda o que recebe (o ValidadorCadastro já decidiu o que é aceitável). Aqui se confere que o dado chega ao banco
// exatamente como veio, inclusive texto estranho: a defesa contra XSS é ESCAPAR NA SAÍDA, e contra SQL Injection é o bind.
testeIntegracao('Cadastro: grava CPF e telefone da conta quando informados', function () {
    comLimpezaCadastroTeste(function ($pdo) {
        $r = CadastroService::cadastrar($pdo, dadosCadastroTeste(['cpf' => '000.000.000-00', 'telefone' => '(11) 90000-0000']));
        afirmarIgual([], $r['erros']);
        $linha = $pdo->query("SELECT cpf, telefone FROM usuarios WHERE email = 'maria@padaria.test'")->fetch();
        afirmarIgual(['000.000.000-00', '(11) 90000-0000'], [$linha['cpf'], $linha['telefone']]);
    });
});

testeIntegracao('Cadastro: sem CPF e telefone (opcionais) a conta é criada com null', function () {
    comLimpezaCadastroTeste(function ($pdo) {
        CadastroService::cadastrar($pdo, dadosCadastroTeste());
        $linha = $pdo->query("SELECT cpf, telefone FROM usuarios WHERE email = 'maria@padaria.test'")->fetch();
        afirmarIgual([null, null], [$linha['cpf'], $linha['telefone']]);
    });
});

testeIntegracao('Cadastro: senha qualquer ("1") e CNPJ de dígito falso (22222222222222) funcionam e a pessoa entra', function () {
    comLimpezaCadastroTeste(function ($pdo) {
        $pdo->exec("DELETE FROM empresas WHERE cnpj = '22222222222222'");
        try {
            $r = CadastroService::cadastrar($pdo, dadosCadastroTeste(['senha' => '1', 'cnpj' => '22222222222222']));
            afirmarIgual([], $r['erros']);
            afirmarVerdadeiro((new Usuario($pdo))->autenticar('maria@padaria.test', '1') !== null);
        } finally {
            $pdo->exec("DELETE FROM usuarios WHERE email = 'maria@padaria.test'");
            $pdo->exec("DELETE FROM empresas WHERE cnpj = '22222222222222'");
        }
    });
});

testeIntegracao('Cadastro: texto estranho em CPF/telefone é guardado como veio (a proteção é escapar na saída, não alterar o dado)', function () {
    comLimpezaCadastroTeste(function ($pdo) {
        CadastroService::cadastrar($pdo, dadosCadastroTeste(['cpf' => '<b>x</b>', 'telefone' => "'; DROP TABLE usuarios; --"]));
        $linha = $pdo->query("SELECT cpf, telefone FROM usuarios WHERE email = 'maria@padaria.test'")->fetch();
        afirmarIgual(['<b>x</b>', "'; DROP TABLE usuarios; --"], [$linha['cpf'], $linha['telefone']]);
        afirmarVerdadeiro((int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() >= 3);
    });
});

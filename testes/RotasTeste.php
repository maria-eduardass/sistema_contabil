<?php
declare(strict_types=1);

/**
 * Política de acesso das rotas, escrita como teste: se alguém adicionar uma rota insegura
 * (GET que altera dados, POST público, cliente com permissão de escrita), este arquivo falha.
 */

function rotasTeste(): array
{
    return require RAIZ . '/config/rotas.php';
}

teste('Rotas: formato válido (nome, método HTTP, controller e acesso conhecidos)', function () {
    foreach (rotasTeste() as $rota => $porMetodo) {
        afirmarIgual(1, preg_match('~^[a-z]+/[a-z]+$~', $rota), "nome da rota $rota");
        foreach ($porMetodo as $metodo => $entrada) {
            afirmarVerdadeiro(in_array($metodo, ['GET', 'POST'], true), "$rota usa método $metodo");
            afirmarVerdadeiro(in_array($entrada[2] ?? 'logado', ['publico', 'logado', 'contador', 'cliente'], true), "$rota: acesso inválido");
        }
    }
});

teste('Rotas: todo controller e ação apontados existem', function () {
    foreach (rotasTeste() as $rota => $porMetodo) {
        foreach ($porMetodo as $metodo => $entrada) {
            afirmarVerdadeiro(class_exists($entrada[0]), "$rota: classe {$entrada[0]} não existe");
            afirmarVerdadeiro(is_callable([new $entrada[0](), $entrada[1]]), "$rota: ação {$entrada[1]} não é pública");
        }
    }
});

teste('Rotas: ações que alteram dados aceitam SOMENTE POST (nada de GET com efeito colateral)', function () {
    $mutantes = ['salvar', 'atualizar', 'inativar', 'excluir', 'pagar', 'logout'];
    foreach (rotasTeste() as $rota => $porMetodo) {
        if (in_array(explode('/', $rota)[1], $mutantes, true)) {
            afirmarIgual(['POST'], array_keys($porMetodo), "$rota deveria ser só POST");
        }
    }
});

/**
 * Lista REVISADA do que qualquer visitante (sem login) pode acessar: entrar e criar a própria conta.
 * Acrescentar uma rota pública aqui é uma decisão de segurança consciente, não um efeito colateral.
 */
const ROTAS_PUBLICAS_TESTE = [
    'GET' => ['auth/login', 'cadastro/novo'],
    'POST' => ['auth/login', 'cadastro/salvar'],
];

teste('Rotas: as únicas rotas públicas são entrar e criar conta (cada uma no método esperado)', function () {
    $publicas = ['GET' => [], 'POST' => []];
    foreach (rotasTeste() as $rota => $porMetodo) {
        foreach ($porMetodo as $metodo => $entrada) {
            if (($entrada[2] ?? 'logado') === 'publico') {
                $publicas[$metodo][] = $rota;
            }
        }
    }
    sort($publicas['GET']);
    sort($publicas['POST']);
    afirmarIgual(ROTAS_PUBLICAS_TESTE['GET'], $publicas['GET']);
    afirmarIgual(ROTAS_PUBLICAS_TESTE['POST'], $publicas['POST']);
});

teste('Rotas: o cadastro nunca aceita GET para gravar (só POST) e o formulário é só GET', function () {
    $rotas = rotasTeste();
    afirmarIgual(['POST'], array_keys($rotas['cadastro/salvar']));
    afirmarIgual(['GET'], array_keys($rotas['cadastro/novo']));
});

/**
 * Rotas em que o DONO da empresa (cliente) também grava, mas SÓ nos dados da própria empresa. Elas são "logado" (não
 * "contador") e a propriedade é conferida dentro do controller com Guarda::exigirEmpresa (ver o teste mais abaixo).
 * Acrescentar uma rota aqui é uma decisão de segurança consciente.
 */
const ROTAS_DO_DONO_TESTE = ['obrigacoes/salvar', 'obrigacoes/pagar', 'obrigacoes/excluir'];

teste('Rotas: o cliente só grava a avaliação, as obrigações da PRÓPRIA empresa e o cadastro; todo o resto é do contador', function () {
    // As rotas públicas (entrar e criar conta) também aceitam POST sem login, por definição.
    $permitidasAoCliente = array_merge(['auth/login', 'auth/logout', 'avaliacoes/salvar', 'cadastro/salvar'], ROTAS_DO_DONO_TESTE);
    foreach (rotasTeste() as $rota => $porMetodo) {
        if (!isset($porMetodo['POST'])) {
            continue;
        }
        $acesso = $porMetodo['POST'][2] ?? 'logado';
        if (in_array($rota, $permitidasAoCliente, true)) {
            continue;
        }
        afirmarIgual('contador', $acesso, "$rota (POST) deveria ser restrita ao contador");
    }
});

teste('Rotas: rota sem nível de acesso declarado vale "logado" (seguro por padrão)', function () {
    $r = new ReflectionClass(App\Core\Router::class);
    afirmarVerdadeiro(str_contains((string) file_get_contents($r->getFileName()), "?? 'logado'"));
});

teste('Rotas: criar, pagar e excluir obrigação exigem login (não só contador) e o formulário também', function () {
    $rotas = rotasTeste();
    foreach (ROTAS_DO_DONO_TESTE as $rota) {
        afirmarIgual('logado', $rotas[$rota]['POST'][2] ?? 'logado', $rota);
    }
    afirmarIgual('logado', $rotas['obrigacoes/criar']['GET'][2] ?? 'logado');
    afirmarIgual(['POST'], array_keys($rotas['obrigacoes/pagar']));
    afirmarIgual(['POST'], array_keys($rotas['obrigacoes/excluir']));
});

teste('Rotas: o controller de obrigações confere a propriedade da empresa em TODA ação que o dono pode usar', function () {
    // Armadilha de regressão: como a rota deixou de ser só do contador, a única barreira contra um cliente mexer na
    // obrigação de OUTRA empresa é o Guarda::exigirEmpresa dentro do controller. Se alguém o remover, este teste falha.
    $codigo = (string) file_get_contents(RAIZ . '/app/Controllers/ObrigacaoController.php');
    afirmarVerdadeiro(substr_count($codigo, 'Guarda::exigirEmpresa(') >= 4, 'criar, salvar, pagar e excluir precisam checar o dono');
});

teste('Rotas: a simulação do termômetro é só leitura (GET) e exige login', function () {
    $rotas = rotasTeste();
    afirmarIgual(['GET'], array_keys($rotas['termometro/simular']));
    afirmarIgual('logado', $rotas['termometro/simular']['GET'][2] ?? 'logado');
});

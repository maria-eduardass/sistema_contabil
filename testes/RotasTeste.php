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

teste('Rotas: o cliente só consegue gravar a própria avaliação (e sair); todo o resto é do contador', function () {
    // As rotas públicas (entrar e criar conta) também aceitam POST sem login, por definição.
    $permitidasAoCliente = ['auth/login', 'auth/logout', 'avaliacoes/salvar', 'cadastro/salvar'];
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

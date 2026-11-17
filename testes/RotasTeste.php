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

teste('Rotas: a única rota pública é o login', function () {
    $publicas = [];
    foreach (rotasTeste() as $rota => $porMetodo) {
        foreach ($porMetodo as $entrada) {
            if (($entrada[2] ?? 'logado') === 'publico') {
                $publicas[$rota] = true;
            }
        }
    }
    afirmarIgual(['auth/login'], array_keys($publicas));
});

teste('Rotas: o cliente só consegue gravar a própria avaliação (e sair); todo o resto é do contador', function () {
    $permitidasAoCliente = ['auth/login', 'auth/logout', 'avaliacoes/salvar'];
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

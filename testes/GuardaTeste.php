<?php
declare(strict_types=1);

use App\Core\Guarda;

function contadorTeste(): array
{
    return ['id' => 1, 'nome' => 'Carla', 'perfil' => 'contador', 'empresa_id' => null];
}

function clienteTeste(int $empresaId = 1): array
{
    return ['id' => 2, 'nome' => 'João', 'perfil' => 'cliente', 'empresa_id' => $empresaId];
}

// --- Acesso por rota ---
teste('Guarda: rota pública libera qualquer um', function () {
    afirmarIgual(Guarda::PERMITIR, Guarda::decidir('publico', null));
    afirmarIgual(Guarda::PERMITIR, Guarda::decidir('publico', clienteTeste()));
});

teste('Guarda: rota "logado" manda anônimo para o login e libera qualquer perfil', function () {
    afirmarIgual(Guarda::LOGIN, Guarda::decidir('logado', null));
    afirmarIgual(Guarda::PERMITIR, Guarda::decidir('logado', contadorTeste()));
    afirmarIgual(Guarda::PERMITIR, Guarda::decidir('logado', clienteTeste()));
});

teste('Guarda: rota de contador — anônimo vai ao login, cliente é negado', function () {
    afirmarIgual(Guarda::LOGIN, Guarda::decidir('contador', null));
    afirmarIgual(Guarda::NEGAR, Guarda::decidir('contador', clienteTeste()));
    afirmarIgual(Guarda::PERMITIR, Guarda::decidir('contador', contadorTeste()));
});

teste('Guarda: rota de cliente — anônimo vai ao login, contador é negado', function () {
    afirmarIgual(Guarda::LOGIN, Guarda::decidir('cliente', null));
    afirmarIgual(Guarda::NEGAR, Guarda::decidir('cliente', contadorTeste()));
    afirmarIgual(Guarda::PERMITIR, Guarda::decidir('cliente', clienteTeste()));
});

teste('Guarda: nível de acesso desconhecido (erro de digitação) nega por segurança', function () {
    afirmarIgual(Guarda::NEGAR, Guarda::decidir('admin', contadorTeste()));
    afirmarIgual(Guarda::LOGIN, Guarda::decidir('admin', null));
});

// --- Acesso por empresa (proteção contra IDOR) ---
teste('Guarda: contador acessa qualquer empresa', function () {
    afirmarVerdadeiro(Guarda::podeAcessarEmpresa(contadorTeste(), 1));
    afirmarVerdadeiro(Guarda::podeAcessarEmpresa(contadorTeste(), 999));
});

teste('Guarda: cliente acessa apenas a própria empresa', function () {
    afirmarVerdadeiro(Guarda::podeAcessarEmpresa(clienteTeste(1), 1));
    afirmarFalso(Guarda::podeAcessarEmpresa(clienteTeste(1), 2));
});

teste('Guarda: perfil sem empresa ou perfil desconhecido nunca acessa empresa alheia', function () {
    afirmarFalso(Guarda::podeAcessarEmpresa(['perfil' => 'cliente', 'empresa_id' => null], 1));
    afirmarFalso(Guarda::podeAcessarEmpresa(['perfil' => 'visitante', 'empresa_id' => 1], 1));
});

teste('Guarda: exigirEmpresa lança 403 quando não pode e passa quando pode', function () {
    Guarda::exigirEmpresa(clienteTeste(1), 1); // não lança
    try {
        Guarda::exigirEmpresa(clienteTeste(1), 2);
        throw new AssertionError('Deveria lançar HttpException');
    } catch (App\Core\HttpException $e) {
        afirmarIgual(403, $e->codigoHttp());
    }
});

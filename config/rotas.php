<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\AvaliacaoController;
use App\Controllers\CadastroController;
use App\Controllers\DashboardController;
use App\Controllers\EmpresaController;
use App\Controllers\InicioController;
use App\Controllers\ObrigacaoController;
use App\Controllers\TermometroController;
use App\Controllers\UsuarioController;

/**
 * Tabela única de rotas: 'controller/acao' => [MÉTODO HTTP => [Classe, método, acesso]].
 *
 * acesso: 'publico' | 'logado' | 'contador' | 'cliente'. Se omitido, vale 'logado'.
 * Ações que alteram dados devem aceitar apenas POST (o Router exige CSRF em todo POST).
 */
return [
    'inicio/index' => ['GET' => [InicioController::class, 'index', 'logado']],

    'auth/login' => [
        'GET' => [AuthController::class, 'formLogin', 'publico'],
        'POST' => [AuthController::class, 'login', 'publico'],
    ],
    'auth/logout' => ['POST' => [AuthController::class, 'logout', 'logado']],

    // Criar conta: públicas de propósito (quem se cadastra ainda não tem login). O POST continua exigindo CSRF
    // e só cria um CLIENTE com a PRÓPRIA empresa (ver CadastroService).
    'cadastro/novo' => ['GET' => [CadastroController::class, 'formulario', 'publico']],
    'cadastro/salvar' => ['POST' => [CadastroController::class, 'salvar', 'publico']],

    'dashboard/index' => ['GET' => [DashboardController::class, 'index', 'logado']],
    'dashboard/contador' => ['GET' => [DashboardController::class, 'contador', 'contador']],
    'dashboard/cliente' => ['GET' => [DashboardController::class, 'cliente', 'cliente']],

    'empresas/index' => ['GET' => [EmpresaController::class, 'index', 'contador']],
    'empresas/criar' => ['GET' => [EmpresaController::class, 'criar', 'contador']],
    'empresas/salvar' => ['POST' => [EmpresaController::class, 'salvar', 'contador']],
    'empresas/editar' => ['GET' => [EmpresaController::class, 'editar', 'contador']],
    'empresas/atualizar' => ['POST' => [EmpresaController::class, 'atualizar', 'contador']],
    'empresas/inativar' => ['POST' => [EmpresaController::class, 'inativar', 'contador']],
    // Lista: contador ou cliente da empresa (conferido no controller). Cadastro: só contador.
    'obrigacoes/index' => ['GET' => [ObrigacaoController::class, 'index', 'logado']],
    // Criar, pagar e excluir: o contador em qualquer empresa e o DONO só na própria. Por isso são "logado": a conferência
    // de propriedade é feita dentro do controller (Guarda::exigirEmpresa), e um teste garante que ela continua lá.
    'obrigacoes/situacao' => ['GET' => [ObrigacaoController::class, 'situacao', 'logado']],
    'obrigacoes/criar' => ['GET' => [ObrigacaoController::class, 'criar', 'logado']],
    'obrigacoes/salvar' => ['POST' => [ObrigacaoController::class, 'salvar', 'logado']],
    'obrigacoes/pagar' => ['POST' => [ObrigacaoController::class, 'pagar', 'logado']],
    'obrigacoes/excluir' => ['POST' => [ObrigacaoController::class, 'excluir', 'logado']],

    // Qualquer usuário logado avalia (via modal/fetch); só o contador vê os resultados.
    'avaliacoes/salvar' => ['POST' => [AvaliacaoController::class, 'salvar', 'logado']],
    'avaliacoes/index' => ['GET' => [AvaliacaoController::class, 'index', 'contador']],

    // JSON do velocímetro: contador ou cliente da empresa (conferido no controller).
    'termometro/json' => ['GET' => [TermometroController::class, 'json', 'logado']],
    // Prévia do termômetro no formulário de nova obrigação (só leitura; o dono só vê a própria empresa).
    'termometro/simular' => ['GET' => [TermometroController::class, 'simular', 'logado']],

    'usuarios/criar' => ['GET' => [UsuarioController::class, 'criar', 'contador']],
    'usuarios/salvar' => ['POST' => [UsuarioController::class, 'salvar', 'contador']],

    // Contador vê qualquer uma; cliente só a própria (conferido no controller via Guarda::exigirEmpresa).
    'empresas/ver' => ['GET' => [EmpresaController::class, 'ver', 'logado']],
];

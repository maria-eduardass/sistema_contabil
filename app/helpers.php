<?php
declare(strict_types=1);

/**
 * Escapa texto para HTML. Toda saída de dados dinâmicos nas views passa por aqui:
 * é a defesa contra XSS. ENT_QUOTES protege também atributos HTML.
 */
function e(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Ícone do Bootstrap Icons. O nome só pode ter letras minúsculas, números e hífens: assim um
 * valor estranho nunca consegue injetar atributos na tag. Decorativo (aria-hidden): o texto ao
 * lado é o que o leitor de tela lê.
 */
function icone(string $nome): string
{
    return preg_match('/^[a-z0-9]+(-[a-z0-9]+)*\z/', $nome) === 1
        ? '<i class="bi bi-' . $nome . '" aria-hidden="true"></i>'
        : '';
}

/**
 * Selo de status da guia: SEMPRE ícone + rótulo (a cor sozinha não pode carregar o significado:
 * daltonismo, impressão em preto e branco). O HTML sai de uma tabela fixa; um valor desconhecido
 * vem escapado.
 */
function seloStatus(string $status): string
{
    $mapa = [
        'pago' => ['Pago', 'check-circle-fill'],
        'pendente' => ['Pendente', 'clock-history'],
        'atrasado' => ['Atrasado', 'exclamation-triangle-fill'],
    ];
    if (!isset($mapa[$status])) {
        return '<span class="selo-status selo-status-desconhecido">' . e($status) . '</span>';
    }
    [$rotulo, $icone] = $mapa[$status];

    return '<span class="selo-status selo-status-' . $status . '">' . icone($icone) . $rotulo . '</span>';
}

/** Selo da faixa de risco (baixo, atenção, alto, crítico): ícone próprio por faixa + o rótulo recebido. */
function seloFaixa(string $faixa, string $rotulo): string
{
    $icones = [
        'baixo' => 'check-circle-fill',
        'atencao' => 'exclamation-circle-fill',
        'alto' => 'exclamation-triangle-fill',
        'critico' => 'x-octagon-fill',
    ];
    if (!isset($icones[$faixa])) {
        return '<span class="selo-status selo-status-desconhecido">' . e($rotulo) . '</span>';
    }

    return '<span class="selo-status selo-faixa-' . $faixa . '">' . icone($icones[$faixa]) . e($rotulo) . '</span>';
}

/** "1 guia", "2 guias" (em português o zero também vai para o plural: "0 guias"). */
function plural(int $quantidade, string $singular, string $plural): string
{
    return $quantidade . ' ' . ($quantidade === 1 ? $singular : $plural);
}

/** Primeira letra do nome, em maiúscula (acentos incluídos), para o avatar. */
function iniciais(string $nome): string
{
    $nome = trim($nome);

    return $nome === '' ? '?' : mb_strtoupper(mb_substr($nome, 0, 1));
}

/** 11222333000181 -> 11.222.333/0001-81 (valores fora do padrão passam sem alteração). */
function formatarCnpj(string $cnpj): string
{
    return preg_match('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})\z/', $cnpj, $p) ? "$p[1].$p[2].$p[3]/$p[4]-$p[5]" : $cnpj;
}

/** 123456 -> "R$ 1.234,56". Recebe centavos (inteiro), então não há erro de ponto flutuante. */
function formatarCentavos(int $centavos): string
{
    return 'R$ ' . number_format(intdiv($centavos, 100), 0, ',', '.') . ',' . sprintf('%02d', $centavos % 100);
}

/** 2026-11-17 -> 17/11/2026 (texto fora do padrão passa sem alteração). */
function formatarData(string $data): string
{
    return preg_match('/^(\d{4})-(\d{2})-(\d{2})\z/', $data, $p) ? "$p[3]/$p[2]/$p[1]" : $data;
}

/** 2026-11-17 14:05:33 -> 17/11/2026 14:05 */
function formatarDataHora(string $dataHora): string
{
    return preg_match('/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})/', $dataHora, $p) ? "$p[3]/$p[2]/$p[1] $p[4]:$p[5]" : $dataHora;
}

/** 2026-11-01 -> 11/2026 */
function formatarCompetencia(string $data): string
{
    return preg_match('/^(\d{4})-(\d{2})-\d{2}\z/', $data, $p) ? "$p[2]/$p[1]" : $data;
}

/**
 * Redireciona para uma rota e encerra. Usado após POST bem-sucedido (padrão PRG),
 * para que F5 não reenvie o formulário.
 *
 * @param array<string,scalar> $params
 */
function redirecionar(string $rota, array $params = []): never
{
    header('Location: ' . url($rota, $params));
    exit;
}

/**
 * Monta uma URL do front-controller (relativa, para funcionar em qualquer pasta do htdocs).
 * A rota vem sempre do código; só os parâmetros são codificados.
 *
 * @param array<string,scalar> $params
 */
function url(string $rota, array $params = []): string
{
    return 'index.php?rota=' . $rota . ($params ? '&' . http_build_query($params) : '');
}

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

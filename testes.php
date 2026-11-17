<?php
/**
 * Runner mínimo de testes (sem Composer/PHPUnit, requisito do ambiente XAMPP).
 *
 *   php testes.php                 -> testes unitários (sem banco)
 *   php testes.php --integracao    -> inclui os testes que usam o banco de teste
 *   http://localhost/sistema_contabil/testes.php[?integracao=1]
 *
 * Cada suíte fica em testes/*Teste.php e registra casos com teste()/testeIntegracao().
 * Sai com código 1 se algum caso falhar (útil antes de commitar).
 */
declare(strict_types=1);

// Este executor pode recriar o banco de teste: pela web, só atende a própria máquina.
// (O .htaccess já restringe; esta checagem é a segunda camada, caso ele não seja honrado.)
if (PHP_SAPI !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Acesso negado.');
}

define('RAIZ', __DIR__);
require RAIZ . '/app/autoload.php';
require RAIZ . '/app/helpers.php';
App\Core\Env::carregar(RAIZ . '/.env');
date_default_timezone_set('America/Sao_Paulo');
mb_internal_encoding('UTF-8');

final class Executor
{
    /** @var list<array{suite:string,nome:string,fn:callable,integracao:bool}> */
    private static array $casos = [];
    public static string $suiteAtual = '';

    public static function registrar(string $nome, callable $fn, bool $integracao): void
    {
        self::$casos[] = ['suite' => self::$suiteAtual, 'nome' => $nome, 'fn' => $fn, 'integracao' => $integracao];
    }

    /** @return list<array{suite:string,nome:string,status:string,erro:string}> */
    public static function executar(bool $comIntegracao): array
    {
        $resultados = [];
        foreach (self::$casos as $caso) {
            $r = ['suite' => $caso['suite'], 'nome' => $caso['nome'], 'status' => 'ok', 'erro' => ''];
            if ($caso['integracao'] && !$comIntegracao) {
                $r['status'] = 'ignorado';
            } else {
                try {
                    ($caso['fn'])();
                } catch (Throwable $e) {
                    $r['status'] = 'falha';
                    $r['erro'] = get_class($e) . ': ' . $e->getMessage();
                }
            }
            $resultados[] = $r;
        }
        return $resultados;
    }
}

function teste(string $nome, callable $fn): void
{
    Executor::registrar($nome, $fn, false);
}

/** Caso que precisa do banco de teste; só roda com --integracao. */
function testeIntegracao(string $nome, callable $fn): void
{
    Executor::registrar($nome, $fn, true);
}

function afirmarVerdadeiro(bool $condicao, string $mensagem = 'Esperava true'): void
{
    if (!$condicao) {
        throw new AssertionError($mensagem);
    }
}

function afirmarFalso(bool $condicao, string $mensagem = 'Esperava false'): void
{
    afirmarVerdadeiro(!$condicao, $mensagem);
}

function afirmarIgual(mixed $esperado, mixed $atual, string $mensagem = ''): void
{
    if ($esperado !== $atual) {
        throw new AssertionError(trim($mensagem . ' Esperado ' . var_export($esperado, true) . ', obtido ' . var_export($atual, true)));
    }
}

/** Garante que $fn lança uma exceção da classe indicada. */
function afirmarExcecao(string $classe, callable $fn): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        if ($e instanceof $classe) {
            return;
        }
        throw new AssertionError("Esperava $classe, lançou " . get_class($e));
    }
    throw new AssertionError("Esperava $classe, nada foi lançado");
}

// --- Carrega as suítes e executa ---
$emCli = PHP_SAPI === 'cli';
$comIntegracao = $emCli ? in_array('--integracao', $argv ?? [], true) : isset($_GET['integracao']);

$arquivos = glob(RAIZ . '/testes/*Teste.php') ?: [];
sort($arquivos);
foreach ($arquivos as $arquivo) {
    Executor::$suiteAtual = basename($arquivo, '.php');
    require $arquivo;
}

$resultados = Executor::executar($comIntegracao);
$contagem = array_count_values(array_column($resultados, 'status'));
$ok = $contagem['ok'] ?? 0;
$falhas = $contagem['falha'] ?? 0;
$ignorados = $contagem['ignorado'] ?? 0;

if ($emCli) {
    foreach ($resultados as $r) {
        $marca = ['ok' => 'OK    ', 'falha' => 'FALHA ', 'ignorado' => 'IGNOR.'][$r['status']];
        echo "[$marca] {$r['suite']} :: {$r['nome']}" . ($r['erro'] !== '' ? "\n         -> {$r['erro']}" : '') . PHP_EOL;
    }
    echo PHP_EOL . "Total: " . count($resultados) . " | OK: $ok | Falhas: $falhas | Ignorados: $ignorados" . PHP_EOL;
    exit($falhas > 0 ? 1 : 0);
}

header('Content-Type: text/html; charset=UTF-8');
$cores = ['ok' => '#198754', 'falha' => '#dc3545', 'ignorado' => '#6c757d'];
?>
<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="UTF-8"><title>Testes · FiscalPulse</title>
<style>body{font-family:system-ui,sans-serif;margin:2rem;max-width:60rem}td,th{padding:.3rem .6rem;text-align:left;border-bottom:1px solid #ddd}</style>
</head><body>
<h1>Testes · FiscalPulse</h1>
<p><strong style="color:<?= $falhas > 0 ? '#dc3545' : '#198754' ?>"><?= $falhas > 0 ? 'FALHOU' : 'PASSOU' ?></strong>
   — OK: <?= $ok ?> | Falhas: <?= $falhas ?> | Ignorados: <?= $ignorados ?>
   <?= $comIntegracao ? '' : '(<a href="?integracao=1">incluir integração</a>)' ?></p>
<table><tr><th>Suíte</th><th>Caso</th><th>Status</th></tr>
<?php foreach ($resultados as $r) : ?>
<tr><td><?= e($r['suite']) ?></td><td><?= e($r['nome']) ?><?= $r['erro'] !== '' ? '<br><small>' . e($r['erro']) . '</small>' : '' ?></td>
<td style="color:<?= $cores[$r['status']] ?>"><?= e($r['status']) ?></td></tr>
<?php endforeach; ?>
</table></body></html>

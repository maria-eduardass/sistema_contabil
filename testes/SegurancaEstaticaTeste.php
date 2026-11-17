<?php
declare(strict_types=1);

/**
 * Varredura estática do código-fonte: trava os padrões que a revisão de segurança aprovou.
 * Não substitui o teste manual, mas impede que uma regressão (um echo sem e(), um SQL
 * concatenado, um onclick inline) entre despercebida.
 */

/** @return list<string> caminhos de arquivos com a extensão dada, dentro de $pasta */
function arquivosTeste(string $pasta, string $extensao): array
{
    $achados = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(RAIZ . '/' . $pasta, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $arquivo) {
        if ($arquivo->getExtension() === $extensao) {
            $achados[] = $arquivo->getPathname();
        }
    }
    sort($achados);
    return $achados;
}

function nomeRelativoTeste(string $caminho): string
{
    return str_replace('\\', '/', substr($caminho, strlen(RAIZ) + 1));
}

// --- Views: toda saída de dado passa por e() (XSS) ---
teste('Estática: toda saída <?= das views é escapada ou está na lista revisada', function () {
    // Expressões revisadas manualmente que NÃO começam com e(): números, constantes, helpers que já escapam.
    $revisadas = [
        // Helpers que devolvem HTML, revisados: icone() só aceita [a-z0-9-]; seloStatus()/seloFaixa() montam
        // a tag de uma tabela fixa e escapam qualquer valor desconhecido (ver testes em NucleoTeste).
        '/^(icone|seloStatus|seloFaixa)\(/',
        '/^e\(/', '/^\(int\) /', '/^(\\\\App\\\\Core\\\\)?Csrf::campo\(\)$/',
        '/^\$(n|nota)$/', '/^\$posicao \+ 1$/', '/^\$(erro|classe)\(/', "/^str_replace\('form-control'/",
        '/^max\(1, \(int\)/', '/^\$conteudo\b/', '/^\$(ehContador|podeAgir) \? \d : \d$/', // colspan: só números fixos
        "/^\\\$valores\['tipo_obrigacao_id'\] === \(string\) \\\$t\['id'\] \? 'selected' : ''$/",
        "/^\\\$valores\['regime_tributario'\] === \\\$chave \? 'selected' : ''$/",
        "/^\\\$resumo\['total'\] === 1 \? 'avaliação' : 'avaliações'$/",
        "/^\\\$resumo\['media'\] === null \? '—' : e\(/",
        "/^\\\$msg\['tipo'\] === 'sucesso' \? 'success' : 'danger'$/",
        "/^\\\$empresa !== null \? e\(\\\$empresa\['razao_social'\]\) : ''$/",
        "/^\\\$a\['comentario'\] === null \? '<span class=\"text-muted\">—<\/span>' : e\(/",
    ];
    $reprovadas = [];
    foreach (arquivosTeste('app/Views', 'php') as $arquivo) {
        preg_match_all('/<\?=\s*(.*?)\s*\?>/s', (string) file_get_contents($arquivo), $m);
        foreach ($m[1] as $expressao) {
            $ok = false;
            foreach ($revisadas as $padrao) {
                if (preg_match($padrao, $expressao)) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                $reprovadas[] = nomeRelativoTeste($arquivo) . ': ' . $expressao;
            }
        }
    }
    afirmarIgual([], $reprovadas, 'Saídas sem e():');
});

// --- CSP: nada de código inline nas views ---
teste('Estática: views sem style="", handlers on*= , javascript: ou <script> inline (compatível com a CSP)', function () {
    $problemas = [];
    foreach (arquivosTeste('app/Views', 'php') as $arquivo) {
        $conteudo = (string) file_get_contents($arquivo);
        if (preg_match('/\sstyle\s*=/i', $conteudo)) {
            $problemas[] = nomeRelativoTeste($arquivo) . ': style inline';
        }
        if (preg_match('/\son[a-z]+\s*=/i', $conteudo)) {
            $problemas[] = nomeRelativoTeste($arquivo) . ': handler on*=';
        }
        if (stripos($conteudo, 'javascript:') !== false) {
            $problemas[] = nomeRelativoTeste($arquivo) . ': javascript:';
        }
        if (preg_match('/<script(?![^>]*\ssrc=)[^>]*>/i', $conteudo)) {
            $problemas[] = nomeRelativoTeste($arquivo) . ': <script> inline';
        }
    }
    afirmarIgual([], $problemas);
});

teste('Estática: JavaScript sem innerHTML, eval, document.write ou string em setTimeout', function () {
    $problemas = [];
    foreach (arquivosTeste('assets/js', 'js') as $arquivo) {
        // Ignora comentários de linha antes de procurar.
        $codigo = preg_replace('~//.*$~m', '', (string) file_get_contents($arquivo));
        foreach (['innerHTML', 'outerHTML', 'insertAdjacentHTML', 'document.write', 'eval(', 'new Function', "setAttribute('style'", 'setAttribute("style"'] as $proibido) {
            if (str_contains((string) $codigo, $proibido)) {
                $problemas[] = nomeRelativoTeste($arquivo) . ": $proibido";
            }
        }
    }
    afirmarIgual([], $problemas);
});

// --- PHP: entrada, SQL e funções perigosas ---
teste('Estática: dados da requisição só são lidos pela classe Request', function () {
    $problemas = [];
    foreach (arquivosTeste('app', 'php') as $arquivo) {
        if (basename($arquivo) === 'Request.php') {
            continue;
        }
        if (preg_match('/\$_(GET|POST|REQUEST|COOKIE|FILES)\b/', (string) file_get_contents($arquivo))) {
            $problemas[] = nomeRelativoTeste($arquivo);
        }
    }
    afirmarIgual([], $problemas);
});

teste('Estática: sem funções perigosas (eval, shell, unserialize...) no código da aplicação', function () {
    $problemas = [];
    foreach (array_merge(arquivosTeste('app', 'php'), arquivosTeste('config', 'php'), [RAIZ . '/index.php']) as $arquivo) {
        $codigo = (string) file_get_contents($arquivo);
        if (preg_match('/\b(eval|system|shell_exec|passthru|popen|proc_open|unserialize|assert|create_function)\s*\(/', $codigo, $m)) {
            $problemas[] = nomeRelativoTeste($arquivo) . ': ' . $m[1];
        }
    }
    afirmarIgual([], $problemas);
});

teste('Estática: SQL nos models não interpola nem concatena variáveis (só prepared statements)', function () {
    $ehSql = static fn (string $texto): bool => preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b/i', $texto) === 1;
    $problemas = [];

    foreach (arquivosTeste('app/Models', 'php') as $arquivo) {
        // O tokenizador do PHP enxerga as strings de verdade (uma regex se confunde com aspas aninhadas).
        $tokens = array_values(array_filter(
            token_get_all((string) file_get_contents($arquivo)),
            static fn ($t): bool => !is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
        ));
        $nome = nomeRelativoTeste($arquivo);

        for ($i = 0, $n = count($tokens); $i < $n; $i++) {
            $t = $tokens[$i];

            if ($t === '"') { // string entre aspas duplas COM interpolação: "... $x ..."
                $texto = '';
                $variaveis = [];
                for ($i++; $i < $n && $tokens[$i] !== '"'; $i++) {
                    if (is_array($tokens[$i]) && $tokens[$i][0] === T_ENCAPSED_AND_WHITESPACE) {
                        $texto .= $tokens[$i][1];
                    } elseif (is_array($tokens[$i]) && $tokens[$i][0] === T_VARIABLE) {
                        $variaveis[] = $tokens[$i][1];
                        $texto .= '{VAR}';
                    } else {
                        $variaveis[] = '{expr}';
                    }
                }
                // Única exceção revisada: LIMIT com inteiro já limitado (Avaliacao::listarRecentes).
                $limiteSeguro = $variaveis === ['$limite'] && preg_match('/LIMIT\s*\{VAR\}\s*$/i', trim($texto)) === 1;
                if ($variaveis !== [] && $ehSql($texto) && !$limiteSeguro) {
                    $problemas[] = "$nome: interpolação em SQL";
                }
                continue;
            }
            if (is_array($t) && $t[0] === T_START_HEREDOC) {
                $problemas[] = "$nome: heredoc em model (revisar manualmente)";
            }
            // 'SELECT ...' . $variavel   ou   $variavel . 'SELECT ...'
            if (is_array($t) && $t[0] === T_CONSTANT_ENCAPSED_STRING && $ehSql($t[1])) {
                $depois = ($tokens[$i + 1] ?? null) === '.' && is_array($tokens[$i + 2] ?? null) && $tokens[$i + 2][0] === T_VARIABLE;
                $antes = $i >= 2 && $tokens[$i - 1] === '.' && is_array($tokens[$i - 2]) && $tokens[$i - 2][0] === T_VARIABLE;
                if ($depois || $antes) {
                    $problemas[] = "$nome: SQL concatenado com variável";
                }
            }
        }
    }
    afirmarIgual([], $problemas);
});

teste('Estática: o detector de SQL realmente reconhece código inseguro (teste do próprio teste)', function () {
    // Garante que o tokenizador acima não está "cego": uma amostra insegura precisa ser flagrada.
    $amostra = '<?php $q = "SELECT * FROM t WHERE id = $id"; $r = \'DELETE FROM t WHERE x = \' . $x;';
    $tokens = token_get_all($amostra);
    $temAspasDuplas = in_array('"', $tokens, true);
    $temConcat = false;
    foreach ($tokens as $i => $t) {
        if (is_array($t) && $t[0] === T_CONSTANT_ENCAPSED_STRING && str_contains($t[1], 'DELETE')) {
            $temConcat = true;
        }
    }
    afirmarVerdadeiro($temAspasDuplas && $temConcat);
});

teste('Estática: senhas só são guardadas com password_hash e conferidas com password_verify', function () {
    $todo = '';
    foreach (arquivosTeste('app', 'php') as $arquivo) {
        $todo .= (string) file_get_contents($arquivo);
    }
    afirmarVerdadeiro(str_contains($todo, 'password_hash('));
    afirmarVerdadeiro(str_contains($todo, 'password_verify('));
    afirmarFalso(preg_match('/\b(md5|sha1|crypt)\s*\(/', $todo) === 1, 'hash fraco encontrado');
});

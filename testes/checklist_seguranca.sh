#!/bin/bash
# Checklist de segurança do FiscalPulse (seção 5 do plano), executado contra o Apache local.
#
#   Requisitos: XAMPP com Apache e MySQL ligados, banco importado (database.sql), Git Bash (curl).
#   Uso (na pasta do projeto):   bash testes/checklist_seguranca.sh
#   Variáveis opcionais:         XAMPP=/c/xampp  RAIZ=/c/xampp/htdocs/sistema_contabil
#
# Cada linha imprime [PASS] ou [FAIL]. O roteiro cria alguns dados de teste (empresa com <script>,
# usuário, avaliações), apaga tudo no final e confere se o banco voltou ao estado do seed.
# Durante a seção 9 ele troca DB_NAME no .env por um banco inexistente por alguns segundos, para
# provocar um erro 500 de verdade, e restaura o arquivo em seguida (também se for interrompido).
XAMPP=${XAMPP:-/c/xampp}
RAIZ=${RAIZ:-$XAMPP/htdocs/sistema_contabil}
B=http://localhost/sistema_contabil
IDX=$B/index.php
SQL="$XAMPP/mysql/bin/mysql.exe -uroot --default-character-set=utf8mb4 fiscalpulse -N -e"
TMP=$(mktemp -d)
PASS=0; FAIL=0

ok()    { PASS=$((PASS+1)); printf '[PASS] %s\n' "$1"; }
falha() { FAIL=$((FAIL+1)); printf '[FAIL] %s  -> %s\n' "$1" "$2"; }
igual() { if [ "$2" = "$3" ]; then ok "$1"; else falha "$1" "esperado=[$2] obtido=[$3]"; fi; }
zero()  { if [ "$2" = "0" ]; then ok "$1"; else falha "$1" "ocorrências=$2 (esperado 0)"; fi; }
maior0(){ if [ "$2" -gt 0 ] 2>/dev/null; then ok "$1"; else falha "$1" "ocorrências=$2 (esperado >0)"; fi; }

tok()   { curl -sL -b "$1" -c "$1" "$IDX?rota=$2" | grep -o 'name="csrf_token" value="[0-9a-f]*"' | head -1 | sed 's/.*value="//;s/"//'; }
code()  { curl -s -o /dev/null -w '%{http_code}' "$@"; }
get()   { jar=$1; shift; curl -s -o "$TMP/pag.html" -w '%{http_code}' -b "$jar" -c "$jar" "$IDX?rota=$1"; }
login() { jar=$1; t=$(tok "$jar" auth/login); curl -s -o /dev/null -w '%{http_code}' -b "$jar" -c "$jar" -X POST "$IDX?rota=auth/login" --data-urlencode "csrf_token=$t" --data-urlencode "email=$2" --data-urlencode "senha=$3"; }
# post JAR ROTA [campos...]  (usa token válido da própria sessão)
post()  { jar=$1; rota=$2; shift 2; t=$(tok "$jar" dashboard/index); curl -s -o "$TMP/pag.html" -w '%{http_code}' -b "$jar" -c "$jar" -X POST "$IDX?rota=$rota" --data-urlencode "csrf_token=$t" "$@"; }
sid()   { grep FISCALPULSE "$1" | awk '{print $7}'; }

CJ=$TMP/contador.txt; KJ=$TMP/cliente.txt
login "$CJ" contador@fiscalpulse.test 'Contador@123' >/dev/null
login "$KJ" cliente1@fiscalpulse.test 'Cliente@123' >/dev/null
HOJE=$(date +%F); MES=$(date +%Y-%m); FUT=$(date -d '+20 days' +%F)

echo "=== 1. SQL INJECTION"
igual "SQLi-1 login com ' OR '1'='1 não autentica" 401 "$(login $TMP/j1.txt "' OR '1'='1" "' OR '1'='1")"
igual "SQLi-2 login com comentário SQL (admin' --) não autentica" 401 "$(login $TMP/j2.txt "contador@fiscalpulse.test' --" "x")"
busca() { curl -s -G -o "$TMP/pag.html" -w '%{http_code}' -b "$CJ" -c "$CJ" "$IDX" --data-urlencode "rota=empresas/index" --data-urlencode "busca=$1"; }
igual "SQLi-3a busca com UNION SELECT responde normalmente (200)" 200 "$(busca "' UNION SELECT id,senha_hash,email,1,1,1 FROM usuarios -- ")"
zero "SQLi-3b busca com UNION SELECT não vaza hashes de senha" "$(grep -c '\$2y\$' $TMP/pag.html)"
zero "SQLi-4 busca com UNION SELECT não mostra erro de SQL" "$(grep -ciE 'SQL syntax|SQLSTATE|PDOException|mysql' $TMP/pag.html)"
busca "" >/dev/null
igual "SQLi-4a validação do seletor: a listagem sem filtro mostra as 3 empresas (se isto falhar, os itens 4b/4c não provam nada)" 3 "$(grep -c '<span class="selo-status' $TMP/pag.html)"
busca "' OR 'a'='a' -- " >/dev/null
igual "SQLi-4b tautologia ' OR 'a'='a não devolve todas as empresas (0 linhas)" 0 "$(grep -c '<span class="selo-status' $TMP/pag.html)"
busca "%' OR 1=1 -- " >/dev/null
igual "SQLi-4c ' OR 1=1 só casa o CNPJ legítimo pelos dígitos '11' (2 de 3 empresas, nunca todas)" 2 "$(grep -c '<span class="selo-status' $TMP/pag.html)"
ini=$(date +%s%N); busca "x' AND SLEEP(4) -- " >/dev/null; ms=$(( ($(date +%s%N) - ini) / 1000000 ))
if [ $ms -lt 3000 ]; then ok "SQLi-4d injeção por tempo (SLEEP 4s) não atrasa a resposta (${ms} ms)"; else falha "SQLi-4d SLEEP" "${ms} ms"; fi
igual "SQLi-5 ?id=1 OR 1=1 é rejeitado (404)" 404 "$(get "$CJ" 'empresas/ver&id=1%20OR%201=1')"
igual "SQLi-6 ?id=1;DROP TABLE é rejeitado (404)" 404 "$(get "$CJ" 'empresas/editar&id=1;DROP%20TABLE%20empresas')"
igual "SQLi-7 tipo_obrigacao_id injetado é rejeitado (422)" 422 "$(post "$CJ" obrigacoes/salvar --data-urlencode empresa_id=1 --data-urlencode 'tipo_obrigacao_id=5 OR 1=1' --data-urlencode competencia=$MES --data-urlencode data_vencimento=$FUT)"
igual "SQLi-8 as tabelas continuam íntegras (3 empresas, 3 usuários)" "3 3" "$($SQL 'select count(*) from empresas' ) $($SQL 'select count(*) from usuarios')"

echo "=== 2. XSS ARMAZENADO (dados gravados e depois exibidos)"
igual "XSS-A cadastra empresa com <script> na razão social" 302 "$(post "$CJ" empresas/salvar --data-urlencode 'razao_social=<script>alert(1)</script> Ltda' --data-urlencode cnpj=45.723.174/0001-10 --data-urlencode regime_tributario=mei --data-urlencode email_contato=x@y.test)"
EID=$($SQL "select id from empresas where cnpj='45723174000110'")
igual "XSS-B cadastra obrigação com \"><img onerror> na observação" 302 "$(post "$CJ" obrigacoes/salvar --data-urlencode empresa_id=$EID --data-urlencode tipo_obrigacao_id=5 --data-urlencode competencia=$MES --data-urlencode data_vencimento=$FUT --data-urlencode valor=10 --data-urlencode 'observacao="><img src=x onerror=alert(1)>')"
for rota in "empresas/index" "empresas/ver&id=$EID" "empresas/editar&id=$EID" "dashboard/contador"; do
  get "$CJ" "$rota" >/dev/null
  zero  "XSS-1 sem <script> executável em $rota" "$(grep -c '<script>alert(1)' $TMP/pag.html)"
  maior0 "XSS-1 aparece escapado (&lt;script&gt;) em $rota" "$(grep -c '&lt;script&gt;alert(1)' $TMP/pag.html)"
done
get "$CJ" "obrigacoes/index&empresa_id=$EID" >/dev/null
zero   "XSS-2 observação sem <img> executável na lista de obrigações" "$(grep -c '<img src=x' $TMP/pag.html)"
maior0 "XSS-2 observação aparece escapada (&lt;img)" "$(grep -c '&lt;img src=x' $TMP/pag.html)"
post "$CJ" avaliacoes/salvar --data-urlencode nota=5 --data-urlencode 'comentario=<script>alert(1)</script>' >/dev/null
get "$CJ" avaliacoes/index >/dev/null
zero "XSS-3 comentário de avaliação sem <script> executável" "$(grep -c '<script>alert(1)' $TMP/pag.html)"
curl -s -b "$CJ" -c "$CJ" "$IDX?rota=termometro/json&empresa_id=$EID" > $TMP/j.json
zero "XSS-4 JSON do termômetro não contém '<' cru" "$(grep -c '<' $TMP/j.json)"

echo "=== 3. XSS REFLETIDO (dado da URL/formulário devolvido na resposta)"
get "$CJ" "empresas/index&busca=<script>alert(1)</script>" >/dev/null
zero "XSS-R1 ?busca=<script> não é refletido cru" "$(grep -c '<script>alert(1)' $TMP/pag.html)"
get "$CJ" "<script>alert(1)</script>" >/dev/null
zero "XSS-R2 rota inválida com <script> não é refletida na página 404" "$(grep -c 'alert(1)' $TMP/pag.html)"
t=$(tok $TMP/j3.txt auth/login); curl -s -o $TMP/pag.html -b $TMP/j3.txt -c $TMP/j3.txt -X POST "$IDX?rota=auth/login" --data-urlencode "csrf_token=$t" --data-urlencode 'email="><script>alert(1)</script>' --data-urlencode senha=x
zero "XSS-R3 e-mail do login reexibido sem script executável" "$(grep -c '<script>alert(1)' $TMP/pag.html)"
post "$CJ" empresas/salvar --data-urlencode 'razao_social="><script>alert(1)</script>' --data-urlencode cnpj=1 >/dev/null
zero "XSS-R4 formulário com erro reexibe valores sem script executável" "$(grep -c '<script>alert(1)' $TMP/pag.html)"

echo "=== 4. CSRF"
igual "CSRF-1 POST sem token é bloqueado (403)" 403 "$(code -b $CJ -c $CJ -X POST "$IDX?rota=empresas/inativar" -d id=1)"
igual "CSRF-2 empresa continua ativa depois do POST sem token" 1 "$($SQL 'select ativa from empresas where id=1')"
tk=$(tok "$KJ" dashboard/index)
igual "CSRF-3 token de OUTRA sessão é recusado (403)" 403 "$(code -b $CJ -c $CJ -X POST "$IDX?rota=empresas/inativar" --data-urlencode "csrf_token=$tk" -d id=1)"
igual "CSRF-4 token adulterado é recusado (403)" 403 "$(code -b $CJ -c $CJ -X POST "$IDX?rota=empresas/inativar" --data-urlencode "csrf_token=${tk}00" -d id=1)"
igual "CSRF-5 formulário forjado de outro site (Origin/Referer evil) sem token (403)" 403 "$(code -b $CJ -c $CJ -X POST "$IDX?rota=empresas/inativar" -H 'Origin: http://evil.example' -H 'Referer: http://evil.example/ataque.html' -d id=1)"
igual "CSRF-6 cabeçalho X-CSRF-Token inválido é recusado (403)" 403 "$(code -b $CJ -c $CJ -X POST "$IDX?rota=avaliacoes/salvar" -H 'X-CSRF-Token: abc' -d nota=5)"
J=$TMP/pre.txt; t0=$(tok "$J" auth/login); login_code=$(curl -s -o /dev/null -w '%{http_code}' -b $J -c $J -X POST "$IDX?rota=auth/login" --data-urlencode "csrf_token=$t0" --data-urlencode "email=contador@fiscalpulse.test" --data-urlencode "senha=Contador@123")
igual "CSRF-7 token de ANTES do login não vale depois dele (403)" "302 403" "$login_code $(code -b $J -c $J -X POST "$IDX?rota=avaliacoes/salvar" --data-urlencode "csrf_token=$t0" -d nota=5)"
for r in empresas/inativar obrigacoes/pagar obrigacoes/excluir usuarios/salvar empresas/salvar; do
  igual "CSRF-8 GET em rota de escrita ($r) é recusado (405)" 405 "$(code -b $CJ -c $CJ "$IDX?rota=$r&id=1")"
done

echo "=== 5. QUEBRA DE CONTROLE DE ACESSO / IDOR (cliente da empresa 1)"
for rota in "empresas/ver&id=2" "empresas/ver&id=3" "termometro/json&empresa_id=2" "termometro/json&empresa_id=3" "obrigacoes/index&empresa_id=2" "empresas/ver&id=999" "termometro/json&empresa_id=999" "obrigacoes/index&empresa_id=999"; do
  igual "IDOR cliente em $rota" 403 "$(get "$KJ" "$rota")"
done
for rota in "empresas/ver&id=1" "termometro/json&empresa_id=1" "obrigacoes/index&empresa_id=1" "dashboard/cliente"; do
  igual "Própria empresa acessível ($rota)" 200 "$(get "$KJ" "$rota")"
done
igual "IDOR-JSON a resposta 403 do cliente não revela se a empresa existe (id 2 e id 999 iguais)" "403 403" "$(get "$KJ" 'termometro/json&empresa_id=2') $(get "$KJ" 'termometro/json&empresa_id=999')"

echo "=== 6. ESCALADA DE PERFIL (cliente tentando agir como contador)"
for rota in empresas/index empresas/criar "empresas/editar&id=1" "usuarios/criar&empresa_id=1" "obrigacoes/criar&empresa_id=1" avaliacoes/index dashboard/contador; do
  igual "Escalada GET $rota" 403 "$(get "$KJ" "$rota")"
done
ANTES="$($SQL 'select ativa from empresas where id=1') $($SQL "select status from obrigacoes where id=1") $($SQL 'select count(*) from obrigacoes') $($SQL 'select count(*) from usuarios') $($SQL 'select count(*) from empresas')"
igual "Escalada POST empresas/salvar" 403 "$(post "$KJ" empresas/salvar --data-urlencode razao_social=Hack --data-urlencode cnpj=45723174000110)"
igual "Escalada POST empresas/inativar" 403 "$(post "$KJ" empresas/inativar --data-urlencode id=1)"
igual "Escalada POST obrigacoes/pagar" 403 "$(post "$KJ" obrigacoes/pagar --data-urlencode id=1 --data-urlencode data_pagamento=$HOJE)"
igual "Escalada POST obrigacoes/excluir" 403 "$(post "$KJ" obrigacoes/excluir --data-urlencode id=1)"
igual "Escalada POST usuarios/salvar" 403 "$(post "$KJ" usuarios/salvar --data-urlencode empresa_id=1 --data-urlencode nome=Hack --data-urlencode email=hack@x.test --data-urlencode senha=Senha@123)"
DEPOIS="$($SQL 'select ativa from empresas where id=1') $($SQL "select status from obrigacoes where id=1") $($SQL 'select count(*) from obrigacoes') $($SQL 'select count(*) from usuarios') $($SQL 'select count(*) from empresas')"
igual "Escalada: nenhum dado foi alterado pelas tentativas do cliente" "$ANTES" "$DEPOIS"
post "$CJ" usuarios/salvar --data-urlencode empresa_id=3 --data-urlencode 'nome=Tentativa Perfil' --data-urlencode email=perfil@x.test --data-urlencode senha=Senha@123 --data-urlencode perfil=contador --data-urlencode ativo=0 >/dev/null
igual "Mass assignment: perfil=contador no formulário é ignorado (grava cliente, ativo)" "cliente 1" "$($SQL "select concat(perfil,' ',ativo) from usuarios where email='perfil@x.test'")"

echo "=== 7. SESSÃO"
S1=$TMP/s1.txt; curl -s -o /dev/null -c $S1 "$IDX?rota=auth/login"; ID1=$(sid $S1)
t=$(tok $S1 auth/login); curl -s -o /dev/null -b $S1 -c $S1 -X POST "$IDX?rota=auth/login" --data-urlencode "csrf_token=$t" --data-urlencode email=contador@fiscalpulse.test --data-urlencode senha='Contador@123'; ID2=$(sid $S1)
if [ -n "$ID1" ] && [ "$ID1" != "$ID2" ]; then ok "Sessão-1 o ID da sessão muda no login (anti session fixation)"; else falha "Sessão-1 ID muda no login" "antes=$ID1 depois=$ID2"; fi
CK=$(curl -s -D - -o /dev/null "$IDX?rota=auth/login" | tr -d '\r' | grep -i '^set-cookie')
maior0 "Sessão-2 cookie com HttpOnly" "$(echo "$CK" | grep -ci 'httponly')"
maior0 "Sessão-3 cookie com SameSite=Lax" "$(echo "$CK" | grep -ci 'samesite=lax')"
maior0 "Sessão-4 cookie restrito à pasta do sistema (path=/sistema_contabil/)" "$(echo "$CK" | grep -ci 'path=/sistema_contabil/')"
FIX=attackerchosenid0123456789abcdef
NOVO=$(curl -s -D - -o /dev/null -H "Cookie: FISCALPULSE_SID=$FIX" "$IDX?rota=auth/login" | tr -d '\r' | grep -i '^set-cookie: FISCALPULSE_SID' | sed -E 's/^[^=]*=([^;]*).*/\1/')
if [ -n "$NOVO" ] && [ "$NOVO" != "$FIX" ]; then ok "Sessão-5 ID escolhido pelo atacante é rejeitado (modo estrito)"; else falha "Sessão-5 fixation" "novo=$NOVO"; fi
cp $S1 $TMP/s1_copia.txt
igual "Sessão-6 logado acessa o painel antes do logout" 200 "$(get $S1 dashboard/contador)"
t=$(tok $S1 dashboard/contador); curl -s -o /dev/null -b $S1 -c $S1 -X POST "$IDX?rota=auth/logout" --data-urlencode "csrf_token=$t"
igual "Sessão-7 após logout, o painel redireciona ao login (302)" 302 "$(get $S1 dashboard/contador)"
igual "Sessão-8 após logout, o cookie ANTIGO copiado também não funciona (sessão invalidada no servidor)" 302 "$(get $TMP/s1_copia.txt dashboard/contador)"
HDR=$(curl -s -D - -o /dev/null -b $CJ -c $CJ "$IDX?rota=dashboard/contador" | tr -d '\r')
maior0 "Sessão-9 página autenticada com Cache-Control: no-store (botão voltar)" "$(echo "$HDR" | grep -ci '^cache-control:.*no-store')"
B1=$TMP/b1.txt; r=""; for i in 1 2 3 4 5; do r="$r $(login $B1 contador@fiscalpulse.test errada)"; done
igual "Sessão-10 5 senhas erradas seguidas, depois a senha CERTA, é bloqueada (429)" "401 401 401 401 401 429" "$(echo $r) $(login $B1 contador@fiscalpulse.test 'Contador@123')"

echo "=== 8. EXPOSIÇÃO DE ARQUIVOS"
for p in .env .env.example .gitignore database.sql README.md app/Core/Database.php app/ config/rotas.php storage/logs/app.log storage/ testes/NucleoTeste.php testes/ .git/config; do
  igual "Exposição /$p bloqueado" 403 "$(code "$B/$p")"
done
igual "Exposição listagem de /assets/ bloqueada" 403 "$(code "$B/assets/")"
igual "Exposição listagem de /assets/js/ bloqueada" 403 "$(code "$B/assets/js/")"
igual "Arquivos públicos legítimos funcionam (assets/css/app.css)" 200 "$(code "$B/assets/css/app.css")"

echo "=== 9. ERROS (sem vazamento de detalhes)"
igual "Erro-1 rota inexistente dá 404" 404 "$(get "$CJ" nao/existe)"
igual "Erro-2 GET em rota POST dá 405" 405 "$(get "$CJ" auth/logout)"
igual "Erro-3 registro inexistente dá 404" 404 "$(get "$CJ" 'empresas/editar&id=99999')"
for rota in nao/existe 'empresas/editar&id=99999'; do
  get "$CJ" "$rota" >/dev/null
  # index.php é o link público do front-controller; qualquer OUTRO .php, caminho ou trace seria vazamento.
  zero "Erro-4 página de erro ($rota) sem caminhos, stack trace ou nomes internos" "$(grep -oiE 'C:\\|xampp|htdocs|stack trace|PDOException|Warning:|Notice:|Fatal error|[A-Za-z_/]*\.php' $TMP/pag.html | grep -vc '^index\.php$')"
done
cp $RAIZ/.env $TMP/env.bak; trap '[ -f "$TMP/env.bak" ] && cp "$TMP/env.bak" "$RAIZ/.env"' EXIT INT TERM
sed -i 's/^DB_NAME=.*/DB_NAME=banco_que_nao_existe_xyz/' $RAIZ/.env
E5=$TMP/e5.txt; t=$(tok $E5 auth/login)
c500=$(curl -s -o $TMP/pag500.html -w '%{http_code}' -b $E5 -c $E5 -X POST "$IDX?rota=auth/login" --data-urlencode "csrf_token=$t" --data-urlencode email=contador@fiscalpulse.test --data-urlencode senha='Contador@123')
cp $TMP/env.bak $RAIZ/.env
igual "Erro-5 falha real de banco devolve 500" 500 "$c500"
zero "Erro-6 a página 500 não vaza banco, usuário, SQLSTATE nem caminhos" "$(grep -ciE 'banco_que_nao_existe|SQLSTATE|PDOException|Access denied|root|C:\\|xampp|Stack trace' $TMP/pag500.html)"
maior0 "Erro-7 o detalhe técnico foi para o log (storage/logs/app.log)" "$(tail -3 $RAIZ/storage/logs/app.log | grep -c 'Falha ao conectar')"
diff -q $TMP/env.bak $RAIZ/.env >/dev/null && ok "(.env restaurado sem alterações)" || falha ".env restaurado" "diferente do backup"

echo "=== 10. CABEÇALHOS DE SEGURANÇA"
H=$(curl -s -D - -o /dev/null "$IDX?rota=auth/login" | tr -d '\r')
for h in 'content-security-policy:.*default-src .self.' 'content-security-policy:.*object-src .none.' 'content-security-policy:.*frame-ancestors .none.' 'x-frame-options: DENY' 'x-content-type-options: nosniff' 'referrer-policy: same-origin' 'permissions-policy:.*camera=\(\)'; do
  maior0 "Cabeçalho ($h) na página HTML" "$(echo "$H" | grep -ciE "^$h")"
done
zero "CSP sem unsafe-inline nem unsafe-eval" "$(echo "$H" | grep -i '^content-security-policy' | grep -ciE 'unsafe-inline|unsafe-eval')"
HJ=$(curl -s -D - -o /dev/null -b $CJ -c $CJ "$IDX?rota=termometro/json&empresa_id=1" | tr -d '\r')
maior0 "JSON com Content-Type application/json" "$(echo "$HJ" | grep -ci '^content-type: application/json')"
maior0 "JSON com Cache-Control: no-store" "$(echo "$HJ" | grep -ci '^cache-control:.*no-store')"
maior0 "JSON também com nosniff" "$(echo "$HJ" | grep -ci '^x-content-type-options: nosniff')"

echo "=== LIMPEZA"
$SQL "delete from obrigacoes where empresa_id in (select id from empresas where cnpj='45723174000110'); delete from empresas where cnpj='45723174000110'; delete from usuarios where email='perfil@x.test'; delete from avaliacoes where id > 1;"
echo "banco restaurado (empresas/usuários/obrigações/avaliações): $($SQL 'select count(*) from empresas') $($SQL 'select count(*) from usuarios') $($SQL 'select count(*) from obrigacoes') $($SQL 'select count(*) from avaliacoes')  | empresa 1 ativa=$($SQL 'select ativa from empresas where id=1')  obrigação 1 = $($SQL 'select status from obrigacoes where id=1')"
rm -rf "$TMP"
echo
echo "RESULTADO: $PASS passaram, $FAIL falharam"
[ $FAIL -eq 0 ]

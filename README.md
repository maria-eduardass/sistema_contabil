# FiscalPulse — Gestão Contábil & Termômetro de Risco Fiscal

Sistema web (PHP 8.2 puro, MVC, MySQL/MariaDB via PDO) para escritórios de contabilidade
gerenciarem empresas-clientes e obrigações fiscais, com um Termômetro de Risco Fiscal (0–100%)
e estimativa de multa por atraso.

## Instalação (XAMPP / Windows)
1. Copie a pasta para `C:\xampp\htdocs\sistema_contabil`.
2. No painel do XAMPP, inicie **Apache** e **MySQL**.
3. Abra `http://localhost/phpmyadmin`, aba **Importar**, e importe `database.sql`
   (cria o banco `fiscalpulse` com dados de exemplo).
4. Copie `.env.example` para `.env` (o padrão do XAMPP é usuário `root` sem senha).
5. Acesse `http://localhost/sistema_contabil/`.

## Usuários de teste
| Perfil | E-mail | Senha |
|---|---|---|
| Contador | contador@fiscalpulse.test | Contador@123 |
| Cliente (Padaria, risco alto) | cliente1@fiscalpulse.test | Cliente@123 |
| Cliente (Tech, risco médio) | cliente2@fiscalpulse.test | Cliente@123 |

## Testes e linter
- Testes: `php testes.php` (disponível a partir da Tarefa 4).
- Linter (PowerShell): `Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }`

## Estrutura
`index.php` (front-controller, rotas `?rota=controller/acao`), `app/` (Core, Controllers, Models,
Services, Views), `config/`, `assets/`, `storage/logs/`, `database.sql`.

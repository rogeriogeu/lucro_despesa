# Despesas e Receitas

Controle simples de receitas e despesas (PHP + MySQL + Bootstrap).

- Contas, categorias (receita/despesa) e lançamentos com cadastro, edição e exclusão
- Dashboard: barras Receitas × Despesas por dia, mês e ano, e pizzas por categoria

## Instalação (Laragon)

1. Coloque a pasta em `www/lucro_despesa` e inicie Apache e MySQL.
2. Abra `http://localhost/lucro_despesa/setup.php` (cria o banco `lucro_despesa`, tabelas e categorias padrão) e depois apague `setup.php`.
3. Entre em `http://localhost/lucro_despesa/` com `admin@lucro.local` / `admin123` e troque a senha.

Ajuste usuário/senha do MySQL em `app.php` se necessário.

## PWA
O app pode ser instalado (manifest + service worker). Sessão de login persistente: só pede senha de novo após clicar em Sair.

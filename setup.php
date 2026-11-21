<?php
// Cria o banco lucro_despesa, as tabelas, o usuário inicial e categorias padrão. Acesse uma vez e apague este arquivo.
$msg = '';
try {
    $pdo = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS lucro_despesa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE lucro_despesa');
    foreach (array_filter(array_map('trim', explode(';', file_get_contents(__DIR__ . '/schema.sql')))) as $sql) $pdo->exec($sql);
    if (!$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn()) {
        $pdo->prepare('INSERT INTO usuarios (nome,email,senha_hash) VALUES (?,?,?)')->execute(['Administrador', 'admin@lucro.local', password_hash('admin123', PASSWORD_DEFAULT)]);
        $pdo->exec("INSERT INTO contas (nome) VALUES ('Carteira')");
        $cats = ['receita' => ['Salário', 'Vendas', 'Serviços', 'Investimentos', 'Outras receitas'], 'despesa' => ['Aluguel', 'Alimentação', 'Transporte', 'Energia', 'Água', 'Internet', 'Saúde', 'Lazer', 'Outras despesas']];
        $st = $pdo->prepare('INSERT INTO categorias (nome,tipo) VALUES (?,?)');
        foreach ($cats as $t => $l) foreach ($l as $n) $st->execute([$n, $t]);
        $msg = 'Instalado! Login: admin@lucro.local / admin123 (troque a senha).';
    } else $msg = 'Banco já instalado.';
} catch (Throwable $e) {
    $msg = 'Erro: ' . $e->getMessage();
}
echo '<!doctype html><meta charset="utf-8"><p>' . htmlspecialchars($msg) . '</p><p><a href="login.php">Ir para o login</a></p>';

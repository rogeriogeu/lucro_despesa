<?php
// TEMPORÁRIO: apague do servidor após o diagnóstico.
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

echo "PHP: " . PHP_VERSION . "\n";
echo "Extensoes: pdo_mysql=" . (extension_loaded('pdo_mysql') ? 'sim' : 'NAO') . "\n";
$sd = __DIR__ . '/sessions';
echo "sessions/ existe: " . (is_dir($sd) ? 'sim' : 'nao') . ", gravavel: " . (is_writable(is_dir($sd) ? $sd : __DIR__) ? 'sim' : 'NAO') . "\n";
echo "config.php existe: " . (is_file(__DIR__ . '/config.php') ? 'sim' : 'nao') . "\n\n";

try {
    require __DIR__ . '/app.php';
    echo "app.php carregado OK\n";
    echo "Host do banco: " . DB_HOST . " | banco: " . DB_NAME . " | usuario: " . DB_USER . "\n";
    $pdo = db();
    echo "db() conectou OK\n";
    echo "Tabelas:\n";
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM) as $t) echo " - " . $t[0] . "\n";
    echo "transferencias_ok(): " . (transferencias_ok() ? 'sim' : 'NAO') . "\n";
} catch (Throwable $e) {
    $m = preg_replace('/(password|senha)[^\s,;]*/i', '[oculto]', $e->getMessage());
    echo "ERRO: " . get_class($e) . ": " . $m . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

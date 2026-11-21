<?php
session_start();

const DB_HOST = '127.0.0.1';
const DB_NAME = 'lucro_despesa';
const DB_USER = 'root';
const DB_PASS = '';
const APP_NOME = 'Lucro & Despesa';

function db(): PDO {
    static $pdo = null;
    if (!$pdo) {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function brl($v): string { return 'R$ ' . number_format((float)$v, 2, ',', '.'); }
function num($v): float {
    $v = trim((string)$v);
    if (strpos($v, ',') !== false) $v = str_replace(',', '.', str_replace('.', '', $v));
    return is_numeric($v) ? (float)$v : 0.0;
}
function redirect(string $url): void { header('Location: ' . $url); exit; }

function flash(?string $msg = null, string $type = 'success') {
    if ($msg !== null) { $_SESSION['flash'] = [$msg, $type]; return null; }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf() . '">'; }
function check_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals(csrf(), $_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('Token CSRF inválido.');
    }
}

function user(): ?array { return $_SESSION['user'] ?? null; }
function require_login(): void { if (!user()) redirect('login.php'); check_csrf(); }

function head_comum(string $title): string {
    return '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . h($title) . ' - ' . APP_NOME . '</title>'
        . '<link rel="icon" type="image/svg+xml" href="favicon.svg">'
        . '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">'
        . '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">'
        . '<link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">'
        . '<link href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/alertify.min.css" rel="stylesheet">'
        . '<link href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/themes/bootstrap.min.css" rel="stylesheet">'
        . '<style>:root,[data-bs-theme=light]{--bs-primary:#c62828;--bs-primary-rgb:198,40,40;--bs-link-color:#c62828;--bs-link-color-rgb:198,40,40;--bs-link-hover-color:#8e1b1b}
.btn-primary{--bs-btn-bg:#c62828;--bs-btn-border-color:#c62828;--bs-btn-hover-bg:#a81f1f;--bs-btn-hover-border-color:#a81f1f;--bs-btn-active-bg:#8e1b1b;--bs-btn-active-border-color:#8e1b1b;--bs-btn-disabled-bg:#c62828;--bs-btn-disabled-border-color:#c62828}
.btn-outline-primary{--bs-btn-color:#c62828;--bs-btn-border-color:#c62828;--bs-btn-hover-bg:#c62828;--bs-btn-hover-border-color:#c62828;--bs-btn-active-bg:#a81f1f;--bs-btn-active-border-color:#a81f1f}
.form-control:focus,.form-select:focus{border-color:#e57373;box-shadow:0 0 0 .25rem rgba(198,40,40,.2)}
.page-item.active .page-link{background:#c62828;border-color:#c62828}.dropdown-item.active,.dropdown-item:active{background:#c62828}
.alertify .ajs-dialog{border-top:4px solid #c62828;border-radius:.5rem;overflow:hidden}.alertify .ajs-header{background:#d32f2f;color:#fff;font-weight:600;border-bottom:0}
.alertify .ajs-footer .ajs-buttons .ajs-button.ajs-ok{background:#c62828;border-color:#c62828;color:#fff}.alertify .ajs-footer .ajs-buttons .ajs-button.ajs-cancel{background:#fff;border:1px solid #c62828;color:#c62828}
.alertify-notifier .ajs-message.ajs-error{background:rgba(198,40,40,.95);color:#fff}</style>';
}

function page_header(string $title): void {
    $u = user();
    $cur = basename($_SERVER['SCRIPT_NAME']);
    $menu = ['index.php' => 'Dashboard', 'lancamentos.php' => 'Receitas e Despesas', 'contas.php' => 'Contas', 'categorias.php' => 'Categorias'];
    echo '<!doctype html><html lang="pt-BR"><head>' . head_comum($title) . '</head><body class="bg-light">';
    echo '<nav class="navbar navbar-expand-lg navbar-dark" style="background:#d32f2f"><div class="container">';
    echo '<a class="navbar-brand d-flex align-items-center gap-2" href="index.php"><img src="favicon.svg" width="28" height="28" alt=""> ' . APP_NOME . '</a>';
    echo '<button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nv"><span class="navbar-toggler-icon"></span></button>';
    echo '<div class="collapse navbar-collapse" id="nv"><ul class="navbar-nav me-auto">';
    foreach ($menu as $f => $l) echo '<li class="nav-item"><a class="nav-link' . ($cur === $f ? ' active' : '') . '" href="' . $f . '">' . $l . '</a></li>';
    echo '</ul><span class="navbar-text me-3">' . h($u['nome']) . '</span><a class="btn btn-outline-light btn-sm" href="logout.php">Sair</a></div></div></nav>';
    echo '<main class="container py-4">';
    $GLOBALS['__flash'] = flash();
}

function page_footer(): void {
    $f = $GLOBALS['__flash'] ?? null;
    echo '</main><script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>'
        . '<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>'
        . '<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>'
        . '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>'
        . '<script src="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/alertify.min.js"></script><script>'
        . 'alertify.set("notifier","position","top-right");alertify.defaults.glossary.ok="Confirmar";alertify.defaults.glossary.cancel="Cancelar";'
        . '$(function(){$("table.dt").DataTable({language:{url:"https://cdn.datatables.net/plug-ins/1.13.8/i18n/pt-BR.json"},pageLength:10,order:[],columnDefs:[{orderable:false,targets:-1}]});});'
        . 'document.addEventListener("submit",function(e){var f=e.target,m=f.dataset.confirm;if(!m||f.dataset.ok)return;e.preventDefault();alertify.confirm("Confirmação",m,function(){f.dataset.ok=1;f.submit();},function(){});});';
    if ($f) echo 'alertify.' . ($f[1] === 'danger' ? 'error' : 'success') . '(' . json_encode($f[0]) . ');';
    echo '</script></body></html>';
}

function delete_button(string $page, int $id, string $msg = 'Excluir este registro?'): string {
    return '<form method="post" action="' . h($page) . '" class="d-inline" data-confirm="' . h($msg) . '">' . csrf_field()
        . '<input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="' . $id . '"><button class="btn btn-sm btn-outline-danger">Excluir</button></form>';
}

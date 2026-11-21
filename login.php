<?php
require 'app.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $st = db()->prepare('SELECT * FROM usuarios WHERE email=?');
    $st->execute([trim($_POST['email'] ?? '')]);
    $u = $st->fetch();
    if ($u && password_verify($_POST['senha'] ?? '', $u['senha_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => (int)$u['id'], 'nome' => $u['nome']];
        redirect('index.php');
    }
    flash('E-mail ou senha inválidos.', 'danger');
    redirect('login.php');
}
$fl = flash();
?><!doctype html><html lang="pt-BR"><head><?= head_comum('Login') ?></head>
<body class="bg-light"><div class="container" style="max-width:380px;margin-top:10vh">
<div class="card shadow-sm"><div class="card-body">
<div class="text-center mb-3"><img src="logo.svg" alt="<?= APP_NOME ?>" style="max-width:240px"></div>
<?php if ($fl): ?><div class="alert alert-<?= h($fl[1]) ?>"><?= h($fl[0]) ?></div><?php endif; ?>
<form method="post"><?= csrf_field() ?>
<div class="mb-3"><label class="form-label">E-mail</label><input type="email" name="email" class="form-control" required autofocus></div>
<div class="mb-3"><label class="form-label">Senha</label><input type="password" name="senha" class="form-control" required></div>
<button class="btn btn-primary w-100">Entrar</button></form></div></div></div></body></html>

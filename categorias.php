<?php
require 'app.php';
require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['acao'] ?? '') === 'excluir') {
        $pdo->prepare('DELETE FROM categorias WHERE id=?')->execute([$id]);
        flash('Categoria excluída (lançamentos ficam sem categoria).');
        redirect('categorias.php');
    }
    $nome = trim($_POST['nome'] ?? '');
    $tipo = $_POST['tipo'] ?? '';
    if ($nome === '' || !in_array($tipo, ['receita', 'despesa'], true)) { flash('Dados inválidos.', 'warning'); redirect('categorias.php'); }
    try {
        if ($id) $pdo->prepare('UPDATE categorias SET nome=?,tipo=? WHERE id=?')->execute([$nome, $tipo, $id]);
        else $pdo->prepare('INSERT INTO categorias (nome,tipo) VALUES (?,?)')->execute([$nome, $tipo]);
        flash('Categoria salva.');
    } catch (PDOException $e) {
        flash('Essa categoria já existe.', 'warning');
    }
    redirect('categorias.php');
}

$edit = null;
if (isset($_GET['editar'])) {
    $st = $pdo->prepare('SELECT * FROM categorias WHERE id=?');
    $st->execute([(int)$_GET['editar']]);
    $edit = $st->fetch() ?: null;
}
page_header('Categorias');
echo '<div class="card mb-4"><div class="card-body"><form method="post" class="row g-2">' . csrf_field() . '<input type="hidden" name="id" value="' . h($edit['id'] ?? 0) . '">';
echo '<div class="col-md-4"><label class="form-label">Tipo</label><select class="form-select" name="tipo"><option value="receita"' . (($edit['tipo'] ?? '') === 'receita' ? ' selected' : '') . '>Receita</option><option value="despesa"' . (($edit['tipo'] ?? 'despesa') === 'despesa' ? ' selected' : '') . '>Despesa</option></select></div>';
echo '<div class="col-md-5"><label class="form-label">Nome</label><input class="form-control" name="nome" required value="' . h($edit['nome'] ?? '') . '"></div>';
echo '<div class="col-md-3 d-flex align-items-end gap-2"><button class="btn btn-primary">' . ($edit ? 'Atualizar' : 'Cadastrar') . '</button>' . ($edit ? '<a class="btn btn-secondary" href="categorias.php">Cancelar</a>' : '') . '</div></form></div></div>';
echo '<div class="card"><div class="card-body"><table class="table table-striped dt w-100"><thead><tr><th>ID</th><th>Tipo</th><th>Nome</th><th>Ações</th></tr></thead><tbody>';
foreach ($pdo->query('SELECT * FROM categorias ORDER BY tipo, nome') as $r) {
    $rec = $r['tipo'] === 'receita';
    echo '<tr><td>' . $r['id'] . '</td><td><span class="badge text-bg-' . ($rec ? 'success' : 'danger') . '">' . ($rec ? 'Receita' : 'Despesa') . '</span></td><td>' . h($r['nome']) . '</td>'
        . '<td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="categorias.php?editar=' . $r['id'] . '">Editar</a> ' . delete_button('categorias.php', (int)$r['id'], 'Excluir esta categoria?') . '</td></tr>';
}
echo '</tbody></table></div></div>';
page_footer();

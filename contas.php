<?php
require 'app.php';
require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['acao'] ?? '') === 'excluir') {
        $st = $pdo->prepare('SELECT COUNT(*) FROM lancamentos WHERE conta_id=?');
        $st->execute([$id]);
        if ($st->fetchColumn()) flash('Conta com lançamentos não pode ser excluída.', 'warning');
        else { $pdo->prepare('DELETE FROM contas WHERE id=?')->execute([$id]); flash('Conta excluída.'); }
        redirect('contas.php');
    }
    $nome = trim($_POST['nome'] ?? '');
    if ($nome === '') { flash('Informe o nome da conta.', 'warning'); redirect('contas.php'); }
    $saldo = num($_POST['saldo_inicial'] ?? 0);
    $tipo = in_array($_POST['tipo'] ?? '', ['receita', 'despesa', 'ambas'], true) ? $_POST['tipo'] : 'ambas';
    if ($id) $pdo->prepare('UPDATE contas SET nome=?,tipo=?,saldo_inicial=? WHERE id=?')->execute([$nome, $tipo, $saldo, $id]);
    else $pdo->prepare('INSERT INTO contas (nome,tipo,saldo_inicial) VALUES (?,?,?)')->execute([$nome, $tipo, $saldo]);
    flash('Conta salva.');
    redirect('contas.php');
}

$edit = null;
if (isset($_GET['editar'])) {
    $st = $pdo->prepare('SELECT * FROM contas WHERE id=?');
    $st->execute([(int)$_GET['editar']]);
    $edit = $st->fetch() ?: null;
}
page_header('Contas');
echo '<div class="card mb-4"><div class="card-body"><form method="post" class="row g-2">' . csrf_field() . '<input type="hidden" name="id" value="' . h($edit['id'] ?? 0) . '">';
$tipos = ['receita' => 'Receita', 'despesa' => 'Despesa', 'ambas' => 'Receita e despesa'];
echo '<div class="col-md-4"><label class="form-label">Nome da conta</label><input class="form-control" name="nome" required placeholder="Carteira, Banco..." value="' . h($edit['nome'] ?? '') . '"></div>';
echo '<div class="col-md-3"><label class="form-label">Tipo</label><select class="form-select" name="tipo">';
foreach ($tipos as $k => $v) echo '<option value="' . $k . '"' . (($edit['tipo'] ?? 'ambas') === $k ? ' selected' : '') . '>' . $v . '</option>';
echo '</select></div>';
echo '<div class="col-md-2"><label class="form-label">Saldo inicial (R$)</label><input class="form-control" name="saldo_inicial" value="' . h(isset($edit) ? number_format($edit['saldo_inicial'], 2, ',', '') : '0,00') . '"></div>';
echo '<div class="col-md-3 d-flex align-items-end gap-2"><button class="btn btn-primary">' . ($edit ? 'Atualizar' : 'Cadastrar') . '</button>' . ($edit ? '<a class="btn btn-secondary" href="contas.php">Cancelar</a>' : '') . '</div></form></div></div>';
echo '<div class="card"><div class="card-body"><table class="table table-striped dt w-100"><thead><tr><th>ID</th><th>Conta</th><th>Tipo</th><th>Receitas</th><th>Despesas</th><th>Saldo</th><th>Ações</th></tr></thead><tbody>';
$sql = "SELECT c.*, COALESCE(SUM(CASE WHEN l.tipo='receita' THEN l.valor END),0) AS rec, COALESCE(SUM(CASE WHEN l.tipo='despesa' THEN l.valor END),0) AS des FROM contas c LEFT JOIN lancamentos l ON l.conta_id=c.id GROUP BY c.id ORDER BY c.nome";
foreach ($pdo->query($sql) as $r) {
    $saldo = $r['saldo_inicial'] + $r['rec'] - $r['des'];
    echo '<tr><td>' . $r['id'] . '</td><td>' . h($r['nome']) . '</td><td>' . $tipos[$r['tipo']] . '</td><td class="text-success">' . brl($r['rec']) . '</td><td class="text-danger">' . brl($r['des']) . '</td><td class="fw-bold">' . brl($saldo) . '</td>'
        . '<td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="contas.php?editar=' . $r['id'] . '">Editar</a> ' . delete_button('contas.php', (int)$r['id'], 'Excluir esta conta?') . '</td></tr>';
}
echo '</tbody></table></div></div>';
page_footer();

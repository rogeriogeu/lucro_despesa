<?php
require 'app.php';
require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['acao'] ?? '') === 'excluir') {
        $pdo->prepare('DELETE FROM lancamentos WHERE id=?')->execute([$id]);
        flash('Lançamento excluído.');
        redirect('lancamentos.php');
    }
    $tipo = $_POST['tipo'] ?? '';
    $desc = trim($_POST['descricao'] ?? '');
    $valor = num($_POST['valor'] ?? 0);
    $data = $_POST['data'] ?? '';
    $conta = (int)($_POST['conta_id'] ?? 0);
    $cat = (int)($_POST['categoria_id'] ?? 0) ?: null;
    if (!in_array($tipo, ['receita', 'despesa'], true) || $desc === '' || $valor <= 0 || !$conta || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        flash('Dados inválidos.', 'danger');
        redirect('lancamentos.php' . ($id ? '?editar=' . $id : ''));
    }
    if ($cat) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM categorias WHERE id=? AND tipo=?');
        $st->execute([$cat, $tipo]);
        if (!$st->fetchColumn()) $cat = null;
    }
    $vals = [$conta, $cat, $tipo, $desc, $valor, $data];
    if ($id) {
        $vals[] = $id;
        $pdo->prepare('UPDATE lancamentos SET conta_id=?,categoria_id=?,tipo=?,descricao=?,valor=?,data=? WHERE id=?')->execute($vals);
        flash('Lançamento atualizado.');
    } else {
        $pdo->prepare('INSERT INTO lancamentos (conta_id,categoria_id,tipo,descricao,valor,data) VALUES (?,?,?,?,?,?)')->execute($vals);
        flash('Lançamento cadastrado.');
    }
    redirect('lancamentos.php');
}

$edit = null;
if (isset($_GET['editar'])) {
    $st = $pdo->prepare('SELECT * FROM lancamentos WHERE id=?');
    $st->execute([(int)$_GET['editar']]);
    $edit = $st->fetch() ?: null;
}
$contas = $pdo->query('SELECT id,nome FROM contas ORDER BY nome')->fetchAll();
$cats = $pdo->query('SELECT id,nome,tipo FROM categorias ORDER BY nome')->fetchAll();
$tipoAtual = $edit['tipo'] ?? 'despesa';

page_header('Receitas e Despesas');
if (!$contas) echo '<div class="alert alert-warning">Cadastre uma <a href="contas.php">conta</a> antes de lançar.</div>';
echo '<div class="card mb-4"><div class="card-body"><form method="post" class="row g-2" id="fl">' . csrf_field();
echo '<input type="hidden" name="id" value="' . h($edit['id'] ?? 0) . '">';
echo '<div class="col-md-2"><label class="form-label">Tipo</label><select class="form-select" name="tipo" id="tipo"><option value="receita"' . ($tipoAtual === 'receita' ? ' selected' : '') . '>Receita</option><option value="despesa"' . ($tipoAtual === 'despesa' ? ' selected' : '') . '>Despesa</option></select></div>';
echo '<div class="col-md-4"><label class="form-label">Descrição</label><input class="form-control" name="descricao" required value="' . h($edit['descricao'] ?? '') . '"></div>';
echo '<div class="col-md-3"><label class="form-label">Conta</label><select class="form-select" name="conta_id" required>';
foreach ($contas as $c) echo '<option value="' . $c['id'] . '"' . (($edit['conta_id'] ?? 0) == $c['id'] ? ' selected' : '') . '>' . h($c['nome']) . '</option>';
echo '</select></div>';
echo '<div class="col-md-3"><label class="form-label">Categoria</label><select class="form-select" name="categoria_id" id="cat"><option value="">Sem categoria</option>';
foreach ($cats as $c) echo '<option value="' . $c['id'] . '" data-tipo="' . $c['tipo'] . '"' . (($edit['categoria_id'] ?? 0) == $c['id'] ? ' selected' : '') . '>' . h($c['nome']) . '</option>';
echo '</select></div>';
echo '<div class="col-md-3"><label class="form-label">Valor (R$)</label><input class="form-control" name="valor" required value="' . h(isset($edit) ? number_format($edit['valor'], 2, ',', '') : '') . '"></div>';
echo '<div class="col-md-3"><label class="form-label">Data</label><input type="date" class="form-control" name="data" required value="' . h($edit['data'] ?? date('Y-m-d')) . '"></div>';
echo '<div class="col-12"><button class="btn btn-primary">' . ($edit ? 'Atualizar' : 'Cadastrar') . '</button> ';
if ($edit) echo '<a class="btn btn-secondary" href="lancamentos.php">Cancelar</a>';
echo '</div></form></div></div>';

echo '<div class="card"><div class="card-body"><table class="table table-striped dt w-100"><thead><tr><th>ID</th><th>Data</th><th>Tipo</th><th>Descrição</th><th>Categoria</th><th>Conta</th><th>Valor</th><th>Ações</th></tr></thead><tbody>';
foreach ($pdo->query('SELECT l.*, c.nome AS conta, g.nome AS categoria FROM lancamentos l JOIN contas c ON c.id=l.conta_id LEFT JOIN categorias g ON g.id=l.categoria_id ORDER BY l.data DESC, l.id DESC') as $r) {
    $rec = $r['tipo'] === 'receita';
    echo '<tr><td>' . $r['id'] . '</td><td data-order="' . h($r['data']) . '">' . date('d/m/Y', strtotime($r['data'])) . '</td>'
        . '<td><span class="badge text-bg-' . ($rec ? 'success' : 'danger') . '">' . ($rec ? 'Receita' : 'Despesa') . '</span></td>'
        . '<td>' . h($r['descricao']) . '</td><td>' . h($r['categoria']) . '</td><td>' . h($r['conta']) . '</td>'
        . '<td data-order="' . $r['valor'] . '" class="text-' . ($rec ? 'success' : 'danger') . '">' . ($rec ? '+ ' : '- ') . brl($r['valor']) . '</td>'
        . '<td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="lancamentos.php?editar=' . $r['id'] . '">Editar</a> ' . delete_button('lancamentos.php', (int)$r['id'], 'Excluir este lançamento?') . '</td></tr>';
}
echo '</tbody></table></div></div>';
echo '<script>document.addEventListener("DOMContentLoaded",function(){var t=document.getElementById("tipo"),c=document.getElementById("cat");
function f(){Array.prototype.forEach.call(c.options,function(o){if(!o.dataset.tipo)return;var ok=o.dataset.tipo===t.value;o.hidden=!ok;o.disabled=!ok;});if(c.selectedOptions[0]&&c.selectedOptions[0].disabled)c.value="";}
t.addEventListener("change",f);f();});</script>';
page_footer();

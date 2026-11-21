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
    if (($_POST['acao'] ?? '') === 'pagar') {
        $st = $pdo->prepare('SELECT valor, valor_pago FROM lancamentos WHERE id=?');
        $st->execute([$id]);
        $l = $st->fetch();
        $pg = num($_POST['pagamento'] ?? 0);
        if (!$l || $pg <= 0) flash('Informe um valor de pagamento válido.', 'warning');
        elseif ($pg > $l['valor'] - $l['valor_pago'] + 0.001) flash('O valor excede o restante (' . brl($l['valor'] - $l['valor_pago']) . ').', 'warning');
        else {
            $pdo->prepare('UPDATE lancamentos SET valor_pago=LEAST(valor, valor_pago+?) WHERE id=?')->execute([$pg, $id]);
            flash('Pagamento de ' . brl($pg) . ' registrado.');
        }
        redirect('lancamentos.php' . ($_POST['voltar'] ?? ''));
    }
    $tipo = $_POST['tipo'] ?? '';
    $desc = trim($_POST['descricao'] ?? '') ?: null;
    $valor = num($_POST['valor'] ?? 0);
    $data = $_POST['data'] ?? '';
    $conta = (int)($_POST['conta_id'] ?? 0);
    $cat = (int)($_POST['categoria_id'] ?? 0) ?: null;
    if (!in_array($tipo, ['receita', 'despesa'], true) || $valor <= 0 || !$conta || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        flash('Dados inválidos.', 'warning');
        redirect('lancamentos.php' . ($id ? '?editar=' . $id : ''));
    }
    $st = $pdo->prepare("SELECT COUNT(*) FROM contas WHERE id=? AND tipo IN ('ambas',?)");
    $st->execute([$conta, $tipo]);
    if (!$st->fetchColumn()) {
        flash('Esta conta não aceita lançamentos deste tipo.', 'warning');
        redirect('lancamentos.php' . ($id ? '?editar=' . $id : ''));
    }
    if ($cat) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM categorias WHERE id=? AND tipo=?');
        $st->execute([$cat, $tipo]);
        if (!$st->fetchColumn()) $cat = null;
    }
    $pago = trim($_POST['valor_pago'] ?? '') === '' ? 0 : max(0, min($valor, num($_POST['valor_pago'])));
    $vals = [$conta, $cat, $tipo, $desc, $valor, $pago, $data];
    if ($id) {
        $vals[] = $id;
        $pdo->prepare('UPDATE lancamentos SET conta_id=?,categoria_id=?,tipo=?,descricao=?,valor=?,valor_pago=?,data=? WHERE id=?')->execute($vals);
        flash('Lançamento atualizado.');
    } else {
        $pdo->prepare('INSERT INTO lancamentos (conta_id,categoria_id,tipo,descricao,valor,valor_pago,data) VALUES (?,?,?,?,?,?,?)')->execute($vals);
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
$contas = $pdo->query('SELECT id,nome,tipo FROM contas ORDER BY nome')->fetchAll();
$cats = $pdo->query('SELECT id,nome,tipo FROM categorias ORDER BY nome')->fetchAll();
$tipoAtual = $edit['tipo'] ?? 'despesa';

page_header('Receitas e Despesas');
if (!$contas) echo '<div class="alert alert-warning">Cadastre uma <a href="contas.php">conta</a> antes de lançar.</div>';
echo '<div class="card mb-4"><div class="card-body"><form method="post" class="row g-2" id="fl">' . csrf_field();
echo '<input type="hidden" name="id" value="' . h($edit['id'] ?? 0) . '">';
echo '<div class="col-md-2"><label class="form-label">Tipo</label><select class="form-select" name="tipo" id="tipo"><option value="receita"' . ($tipoAtual === 'receita' ? ' selected' : '') . '>Receita</option><option value="despesa"' . ($tipoAtual === 'despesa' ? ' selected' : '') . '>Despesa</option></select></div>';
echo '<div class="col-md-4"><label class="form-label">Descrição</label><input class="form-control" name="descricao" value="' . h($edit['descricao'] ?? '') . '"></div>';
echo '<div class="col-md-3"><label class="form-label">Conta</label><select class="form-select" name="conta_id" id="conta" required>';
foreach ($contas as $c) echo '<option value="' . $c['id'] . '" data-tipo="' . $c['tipo'] . '"' . (($edit['conta_id'] ?? 0) == $c['id'] ? ' selected' : '') . '>' . h($c['nome']) . '</option>';
echo '</select></div>';
echo '<div class="col-md-3"><label class="form-label">Categoria</label><select class="form-select" name="categoria_id" id="cat"><option value="">Sem categoria</option>';
foreach ($cats as $c) echo '<option value="' . $c['id'] . '" data-tipo="' . $c['tipo'] . '"' . (($edit['categoria_id'] ?? 0) == $c['id'] ? ' selected' : '') . '>' . h($c['nome']) . '</option>';
echo '</select></div>';
echo '<div class="col-md-3"><label class="form-label">Valor (R$)</label><input class="form-control" name="valor" required value="' . h(isset($edit) ? number_format($edit['valor'], 2, ',', '') : '') . '"></div>';
echo '<div class="col-md-3"><label class="form-label">Valor pago (R$)</label><input class="form-control" name="valor_pago" placeholder="Em branco = pendente" value="' . h(isset($edit) ? number_format($edit['valor_pago'], 2, ',', '') : '') . '"></div>';
echo '<div class="col-md-3"><label class="form-label">Data</label><input type="date" class="form-control" name="data" required value="' . h($edit['data'] ?? date('Y-m-d')) . '"></div>';
echo '<div class="col-12"><button class="btn btn-primary">' . ($edit ? 'Atualizar' : 'Cadastrar') . '</button> ';
if ($edit) echo '<a class="btn btn-secondary" href="lancamentos.php">Cancelar</a>';
echo '</div></form></div></div>';

$fc = (int)($_GET['fconta'] ?? 0);
$ft = in_array($_GET['ftipo'] ?? '', ['receita', 'despesa'], true) ? $_GET['ftipo'] : '';
echo '<div class="card mb-3"><div class="card-body"><form method="get" class="row g-2 align-items-end"><div class="col-md-4"><label class="form-label">Filtrar por conta</label><select class="form-select" name="fconta"><option value="">Todas as contas</option>';
foreach ($contas as $c) echo '<option value="' . $c['id'] . '"' . ($fc == $c['id'] ? ' selected' : '') . '>' . h($c['nome']) . '</option>';
echo '</select></div><div class="col-md-3"><label class="form-label">Tipo</label><select class="form-select" name="ftipo"><option value="">Receitas e despesas</option><option value="receita"' . ($ft === 'receita' ? ' selected' : '') . '>Receitas</option><option value="despesa"' . ($ft === 'despesa' ? ' selected' : '') . '>Despesas</option></select></div>';
echo '<div class="col-md-5"><button class="btn btn-primary">Filtrar</button> ' . ($fc || $ft ? '<a class="btn btn-secondary" href="lancamentos.php">Limpar</a>' : '') . '</div></form></div></div>';

echo '<div class="card"><div class="card-body"><table class="table table-striped dt w-100"><thead><tr><th>ID</th><th>Data</th><th>Tipo</th><th>Descrição</th><th>Categoria</th><th>Conta</th><th>Valor</th><th>Pago</th><th>Restante</th><th>Situação</th><th>Ações</th></tr></thead><tbody>';
$where = [];
$args = [];
if ($fc) { $where[] = 'l.conta_id=?'; $args[] = $fc; }
if ($ft) { $where[] = 'l.tipo=?'; $args[] = $ft; }
$st = $pdo->prepare('SELECT l.*, c.nome AS conta, g.nome AS categoria FROM lancamentos l JOIN contas c ON c.id=l.conta_id LEFT JOIN categorias g ON g.id=l.categoria_id' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY l.data DESC, l.id DESC');
$st->execute($args);
foreach ($st as $r) {
    $rec = $r['tipo'] === 'receita';
    $resta = round($r['valor'] - $r['valor_pago'], 2);
    $badge = $resta <= 0 ? '<span class="badge text-bg-success">Pago</span>' : ($r['valor_pago'] > 0 ? '<span class="badge text-bg-warning">Parcial</span>' : '<span class="badge text-bg-secondary">Pendente</span>');
    $acao = $resta > 0 ? '<button type="button" class="btn btn-sm btn-danger btn-pagar" data-id="' . $r['id'] . '" data-resta="' . $resta . '" data-resta-fmt="' . h(brl($resta)) . '">' . ($rec ? 'Receber' : 'Pagar') . '</button> ' : '';
    echo '<tr><td>' . $r['id'] . '</td><td data-order="' . h($r['data']) . '">' . date('d/m/Y', strtotime($r['data'])) . '</td>'
        . '<td><span class="badge text-bg-' . ($rec ? 'success' : 'danger') . '">' . ($rec ? 'Receita' : 'Despesa') . '</span></td>'
        . '<td>' . h($r['descricao']) . '</td><td>' . h($r['categoria']) . '</td><td>' . h($r['conta']) . '</td>'
        . '<td data-order="' . $r['valor'] . '" class="text-' . ($rec ? 'success' : 'danger') . '">' . ($rec ? '+ ' : '- ') . brl($r['valor']) . '</td>'
        . '<td data-order="' . $r['valor_pago'] . '">' . brl($r['valor_pago']) . '</td>'
        . '<td data-order="' . $resta . '" class="fw-bold">' . brl($resta) . '</td><td>' . $badge . '</td>'
        . '<td class="text-nowrap">' . $acao . '<a class="btn btn-sm btn-outline-primary" href="lancamentos.php?editar=' . $r['id'] . '">Editar</a> ' . delete_button('lancamentos.php', (int)$r['id'], 'Excluir este lançamento?') . '</td></tr>';
}
echo '</tbody></table></div></div>';
echo '<form method="post" id="fpagar" class="d-none">' . csrf_field() . '<input type="hidden" name="acao" value="pagar"><input type="hidden" name="id" id="pid"><input type="hidden" name="pagamento" id="ppg"><input type="hidden" name="voltar" value="' . h(($fc || $ft) ? '?' . http_build_query(array_filter(['fconta' => $fc ?: null, 'ftipo' => $ft ?: null])) : '') . '"></form>';
echo '<script>document.addEventListener("DOMContentLoaded",function(){var t=document.getElementById("tipo"),c=document.getElementById("cat");
document.addEventListener("click",function(e){var b=e.target.closest(".btn-pagar");if(!b)return;
alertify.prompt(b.textContent,"Valor a "+b.textContent.toLowerCase()+" (restante "+b.dataset.restaFmt+"). O que sobrar fica para depois.",String(b.dataset.resta).replace(".",","),function(ev,v){document.getElementById("pid").value=b.dataset.id;document.getElementById("ppg").value=v;document.getElementById("fpagar").submit();},function(){});});var t=document.getElementById("tipo"),c=document.getElementById("cat");
function f(){Array.prototype.forEach.call(c.options,function(o){if(!o.dataset.tipo)return;var ok=o.dataset.tipo===t.value;o.hidden=!ok;o.disabled=!ok;});if(c.selectedOptions[0]&&c.selectedOptions[0].disabled)c.value="";}
var k=document.getElementById("conta");
function fc(){Array.prototype.forEach.call(k.options,function(o){var ok=o.dataset.tipo==="ambas"||o.dataset.tipo===t.value;o.hidden=!ok;o.disabled=!ok;});if(k.selectedOptions[0]&&k.selectedOptions[0].disabled){for(var i=0;i<k.options.length;i++)if(!k.options[i].disabled){k.selectedIndex=i;break;}}}
t.addEventListener("change",function(){f();fc();});f();fc();});</script>';
page_footer();

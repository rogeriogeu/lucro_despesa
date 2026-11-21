<?php
require 'app.php';
require_login();
$pdo = db();

$sit = in_array($_GET['sit'] ?? '', ['abertos', 'pagos', 'todos'], true) ? $_GET['sit'] : 'abertos';
$ft = in_array($_GET['ftipo'] ?? '', ['receita', 'despesa'], true) ? $_GET['ftipo'] : '';
$voltar = '?' . http_build_query(['sit' => $sit, 'ftipo' => $ft]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $voltar = '?' . http_build_query(['sit' => $_POST['sit'] ?? 'abertos', 'ftipo' => $_POST['ftipo'] ?? '']);
    $st = $pdo->prepare('SELECT valor, valor_pago FROM lancamentos WHERE id=?');
    $st->execute([$id]);
    $l = $st->fetch();
    if (!$l) { flash('Lançamento não encontrado.', 'warning'); redirect('pagamentos.php' . $voltar); }
    $resta = round($l['valor'] - $l['valor_pago'], 2);
    $pg = ($_POST['modo'] ?? '') === 'total' ? $resta : num($_POST['pagamento'] ?? 0);
    if ($pg <= 0) flash('Informe um valor válido.', 'warning');
    elseif ($pg > $resta + 0.001) flash('O valor excede o restante (' . brl($resta) . ').', 'warning');
    else {
        $pdo->prepare('UPDATE lancamentos SET valor_pago=LEAST(valor, valor_pago+?) WHERE id=?')->execute([$pg, $id]);
        $falta = round($resta - $pg, 2);
        flash('Registrado ' . brl($pg) . '.' . ($falta > 0 ? ' Restante: ' . brl($falta) . '.' : ' Quitado!'));
    }
    redirect('pagamentos.php' . $voltar);
}

$where = [];
$args = [];
if ($sit === 'abertos') $where[] = 'l.valor_pago < l.valor';
if ($sit === 'pagos') $where[] = 'l.valor_pago >= l.valor';
if ($ft) { $where[] = 'l.tipo=?'; $args[] = $ft; }
$st = $pdo->prepare('SELECT l.*, c.nome AS conta FROM lancamentos l JOIN contas c ON c.id=l.conta_id' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY l.data, l.id');
$st->execute($args);
$rows = $st->fetchAll();
$totR = $totD = 0;
foreach ($rows as $r) { $f = $r['valor'] - $r['valor_pago']; if ($r['tipo'] === 'receita') $totR += $f; else $totD += $f; }

page_header('Pagamentos e Recebimentos');
echo '<h4 class="mb-3">Pagamentos e Recebimentos</h4>';
echo '<div class="row g-3 mb-3"><div class="col-6"><div class="card h-100"><div class="card-body"><div class="text-muted">A receber (restante)</div><div class="fs-4 fw-bold text-success text-nowrap">' . brl($totR) . '</div></div></div></div>'
    . '<div class="col-6"><div class="card h-100"><div class="card-body"><div class="text-muted">A pagar (restante)</div><div class="fs-4 fw-bold text-danger text-nowrap">' . brl($totD) . '</div></div></div></div></div>';
echo '<div class="card mb-3"><div class="card-body"><form method="get" class="row g-2 align-items-end"><div class="col-md-4"><label class="form-label">Situação</label><select class="form-select" name="sit">';
foreach (['abertos' => 'Pendentes e parciais', 'pagos' => 'Quitados', 'todos' => 'Todos'] as $k => $v) echo '<option value="' . $k . '"' . ($sit === $k ? ' selected' : '') . '>' . $v . '</option>';
echo '</select></div><div class="col-md-4"><label class="form-label">Tipo</label><select class="form-select" name="ftipo"><option value="">Receitas e despesas</option><option value="receita"' . ($ft === 'receita' ? ' selected' : '') . '>Receitas</option><option value="despesa"' . ($ft === 'despesa' ? ' selected' : '') . '>Despesas</option></select></div>';
echo '<div class="col-md-4"><button class="btn btn-primary">Filtrar</button></div></form></div></div>';

echo '<div class="card"><div class="card-body"><table class="table table-striped dt w-100"><thead><tr><th>Data</th><th>Tipo</th><th>Descrição</th><th>Conta</th><th>Total</th><th>Pago</th><th>Restante</th><th>Situação</th><th>Ações</th></tr></thead><tbody>';
foreach ($rows as $r) {
    $rec = $r['tipo'] === 'receita';
    $resta = round($r['valor'] - $r['valor_pago'], 2);
    $badge = $resta <= 0 ? '<span class="badge text-bg-success">Quitado</span>' : ($r['valor_pago'] > 0 ? '<span class="badge text-bg-warning">Parcial</span>' : '<span class="badge text-bg-secondary">Pendente</span>');
    $btn = $resta > 0 ? '<button type="button" class="btn btn-sm btn-danger btn-pagar" data-id="' . $r['id'] . '" data-resta="' . $resta . '" data-resta-fmt="' . h(brl($resta)) . '" data-desc="' . h($r['descricao']) . '" data-rec="' . (int)$rec . '">' . ($rec ? 'Receber' : 'Pagar') . '</button>' : '';
    echo '<tr><td data-order="' . h($r['data']) . '">' . date('d/m/Y', strtotime($r['data'])) . '</td>'
        . '<td><span class="badge text-bg-' . ($rec ? 'success' : 'danger') . '">' . ($rec ? 'Receita' : 'Despesa') . '</span></td>'
        . '<td>' . h($r['descricao']) . '</td><td>' . h($r['conta']) . '</td>'
        . '<td data-order="' . $r['valor'] . '">' . brl($r['valor']) . '</td><td data-order="' . $r['valor_pago'] . '">' . brl($r['valor_pago']) . '</td>'
        . '<td data-order="' . $resta . '" class="fw-bold">' . brl($resta) . '</td><td>' . $badge . '</td><td class="text-nowrap">' . $btn . '</td></tr>';
}
echo '</tbody></table></div></div>';
?>
<div class="modal fade" id="mpag" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content">
<?= csrf_field() ?>
<input type="hidden" name="id" id="pid"><input type="hidden" name="sit" value="<?= h($sit) ?>"><input type="hidden" name="ftipo" value="<?= h($ft) ?>">
<div class="modal-header"><h5 class="modal-title" id="ptitulo">Pagar</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<p class="mb-2" id="pdesc"></p>
<div class="form-check"><input class="form-check-input" type="radio" name="modo" id="mt" value="total" checked><label class="form-check-label" for="mt">Valor total restante: <b id="ptotal"></b></label></div>
<div class="form-check mb-2"><input class="form-check-input" type="radio" name="modo" id="mp" value="parcial"><label class="form-check-label" for="mp">Valor parcial (o restante fica para depois)</label></div>
<input class="form-control" name="pagamento" id="pval" placeholder="Quanto foi pago? Ex.: 50,00" disabled>
</div>
<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Confirmar</button></div>
</form></div></div>
<script>
document.addEventListener("DOMContentLoaded",function(){
var m=new bootstrap.Modal(document.getElementById("mpag")),v=document.getElementById("pval");
function modo(){var p=document.getElementById("mp").checked;v.disabled=!p;v.required=p;if(p)v.focus();}
document.getElementById("mt").onchange=modo;document.getElementById("mp").onchange=modo;
document.addEventListener("click",function(e){var b=e.target.closest(".btn-pagar");if(!b)return;
document.getElementById("pid").value=b.dataset.id;
document.getElementById("ptitulo").textContent=b.textContent;
document.getElementById("pdesc").textContent=b.dataset.desc;
document.getElementById("ptotal").textContent=b.dataset.restaFmt;
document.getElementById("mt").checked=true;v.value="";modo();m.show();});});
</script>
<?php page_footer();

<?php
require 'app.php';
require_login();
$pdo = db();

$sit = in_array($_GET['sit'] ?? '', ['abertos', 'pagos', 'todos'], true) ? $_GET['sit'] : 'abertos';
$ft = in_array($_GET['ftipo'] ?? '', ['receita', 'despesa'], true) ? $_GET['ftipo'] : '';
$voltar = '?' . http_build_query(['sit' => $sit, 'ftipo' => $ft]);

function filtro_lanc($sit, $ft) {
    $w = []; $a = [];
    if ($sit === 'abertos') $w[] = 'l.valor_pago < l.valor';
    if ($sit === 'pagos') $w[] = 'l.valor_pago >= l.valor';
    if ($ft) { $w[] = 'l.tipo=?'; $a[] = $ft; }
    return [$w, $a];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contaId = (int)($_POST['conta_id'] ?? 0);
    $tipo = in_array($_POST['tipo'] ?? '', ['receita', 'despesa'], true) ? $_POST['tipo'] : '';
    $psit = in_array($_POST['sit'] ?? '', ['abertos', 'pagos', 'todos'], true) ? $_POST['sit'] : 'abertos';
    $pft = in_array($_POST['ftipo'] ?? '', ['receita', 'despesa'], true) ? $_POST['ftipo'] : '';
    $voltar = '?' . http_build_query(['sit' => $psit, 'ftipo' => $pft]);
    [$w, $a] = filtro_lanc($psit, $pft);
    $w[] = 'l.conta_id=?'; $a[] = $contaId;
    $w[] = 'l.tipo=?'; $a[] = $tipo;
    $st = $pdo->prepare('SELECT l.id, l.valor, l.valor_pago FROM lancamentos l WHERE ' . implode(' AND ', $w) . ' ORDER BY l.data, l.id');
    $st->execute($a);
    $grupo = $st->fetchAll();
    if (!$grupo) { flash('Lançamento não encontrado.', 'warning'); redirect('pagamentos.php' . $voltar); }
    if (($_POST['acao'] ?? '') === 'estornar') {
        $pago = 0;
        $up = $pdo->prepare('UPDATE lancamentos SET valor_pago=0 WHERE id=?');
        foreach ($grupo as $l) { $pago += $l['valor_pago']; $up->execute([$l['id']]); }
        flash('Pagamento estornado: ' . brl($pago) . ' voltou para a conta.');
        redirect('pagamentos.php' . $voltar);
    }
    $resta = 0;
    foreach ($grupo as $l) $resta += max(0, round($l['valor'] - $l['valor_pago'], 2));
    $resta = round($resta, 2);
    $pg = ($_POST['modo'] ?? '') === 'total' ? $resta : num($_POST['pagamento'] ?? 0);
    if ($pg <= 0) flash('Informe um valor válido.', 'warning');
    elseif ($pg > $resta + 0.001) flash('O valor excede o restante (' . brl($resta) . ').', 'warning');
    else {
        $pdo->beginTransaction();
        $up = $pdo->prepare('UPDATE lancamentos SET valor_pago=LEAST(valor, valor_pago+?) WHERE id=?');
        $sobra = $pg;
        foreach ($grupo as $l) {
            $r1 = round($l['valor'] - $l['valor_pago'], 2);
            if ($r1 <= 0) continue;
            $x = min($r1, $sobra);
            if ($x <= 0) break;
            $up->execute([$x, $l['id']]);
            $sobra = round($sobra - $x, 2);
        }
        $pdo->commit();
        $falta = round($resta - $pg, 2);
        flash('Registrado ' . brl($pg) . '.' . ($falta > 0 ? ' Restante: ' . brl($falta) . '.' : ' Quitado!'));
    }
    redirect('pagamentos.php' . $voltar);
}

[$where, $args] = filtro_lanc($sit, $ft);
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
$grupos = [];
foreach ($rows as $r) {
    $k = $r['conta_id'] . '|' . $r['tipo'];
    if (!isset($grupos[$k])) $grupos[$k] = ['conta_id' => $r['conta_id'], 'tipo' => $r['tipo'], 'conta' => $r['conta'], 'data' => $r['data'], 'valor' => 0, 'pago' => 0, 'n' => 0];
    $grupos[$k]['valor'] += $r['valor'];
    $grupos[$k]['pago'] += $r['valor_pago'];
    $grupos[$k]['n']++;
}
foreach ($grupos as $r) {
    $rec = $r['tipo'] === 'receita';
    $r['valor'] = round($r['valor'], 2);
    $r['pago'] = round($r['pago'], 2);
    $resta = round($r['valor'] - $r['pago'], 2);
    $n = $r['n'];
    $desc = $n . ($n > 1 ? ' lançamentos' : ' lançamento');
    $badge = $resta <= 0 ? '<span class="badge text-bg-success">Quitado</span>' : ($r['pago'] > 0 ? '<span class="badge text-bg-warning">Parcial</span>' : '<span class="badge text-bg-secondary">Pendente</span>');
    $hid = '<input type="hidden" name="conta_id" value="' . (int)$r['conta_id'] . '"><input type="hidden" name="tipo" value="' . h($r['tipo']) . '"><input type="hidden" name="sit" value="' . h($sit) . '"><input type="hidden" name="ftipo" value="' . h($ft) . '">';
    $btn = $resta > 0 ? '<button type="button" class="btn btn-sm btn-danger btn-pagar" data-conta="' . (int)$r['conta_id'] . '" data-tipo="' . h($r['tipo']) . '" data-resta="' . $resta . '" data-total-fmt="' . h(brl($r['valor'])) . '" data-resta-fmt="' . h(brl($resta)) . '" data-desc="' . h($r['conta'] . ' - ' . $desc) . '" data-rec="' . (int)$rec . '">' . ($rec ? 'Receber' : 'Pagar') . '</button>' : '';
    if ($r['pago'] > 0) $btn .= ' <form method="post" class="d-inline" data-confirm="Estornar ' . h(brl($r['pago'])) . '? O valor volta para a conta e os lançamentos ficam pendentes.">' . csrf_field() . '<input type="hidden" name="acao" value="estornar">' . $hid . '<button class="btn btn-sm btn-outline-secondary">Estornar</button></form>';
    echo '<tr><td data-order="' . h($r['data']) . '">' . date('d/m/Y', strtotime($r['data'])) . '</td>'
        . '<td><span class="badge text-bg-' . ($rec ? 'success' : 'danger') . '">' . ($rec ? 'Receita' : 'Despesa') . '</span></td>'
        . '<td>' . h($desc) . '</td><td>' . h($r['conta']) . '</td>'
        . '<td data-order="' . $r['valor'] . '">' . brl($r['valor']) . '</td><td data-order="' . $r['pago'] . '">' . brl($r['pago']) . '</td>'
        . '<td data-order="' . $resta . '" class="fw-bold">' . brl($resta) . '</td><td>' . $badge . '</td><td class="text-nowrap">' . $btn . '</td></tr>';
}
echo '</tbody></table></div></div>';
?>
<div class="card position-fixed bottom-0 start-50 translate-middle-x mb-3 shadow d-none" id="selbar" style="z-index:1050;max-width:96vw"><form method="post" class="card-body d-flex flex-wrap gap-2 align-items-center">
<?= csrf_field() ?>
<input type="hidden" name="conta_id" id="pconta"><input type="hidden" name="tipo" id="ptipo"><input type="hidden" name="sit" value="<?= h($sit) ?>"><input type="hidden" name="ftipo" value="<?= h($ft) ?>"><input type="hidden" name="modo" id="pmodo" value="total">
<div><div class="fw-bold" id="pdesc"></div><small class="text-muted">Total: <span id="ptot"></span> &middot; Em aberto: <b id="ptotal"></b></small></div>
<button class="btn btn-success" id="btot">Pagar total</button>
<button type="button" class="btn btn-warning" id="bpar">Pagar parcial</button>
<input class="form-control w-auto d-none" name="pagamento" id="pval" placeholder="Valor pago. Ex.: 50,00" inputmode="decimal" disabled>
<button class="btn btn-primary d-none" id="bok">Confirmar</button>
<button type="button" class="btn btn-outline-secondary" id="bcan">Cancelar</button>
</form></div>
<script>
document.addEventListener("DOMContentLoaded",function(){
var bar=document.getElementById("selbar"),v=document.getElementById("pval"),bok=document.getElementById("bok"),sel=null;
function reset(){v.classList.add("d-none");v.disabled=true;v.required=false;v.value="";bok.classList.add("d-none");document.getElementById("pmodo").value="total";}
function fechar(){if(sel)sel.classList.remove("table-active");sel=null;bar.classList.add("d-none");reset();}
document.addEventListener("click",function(e){
var tr=e.target.closest("table.dt tbody tr");if(!tr||e.target.closest("form,a,button:not(.btn-pagar)"))return;
var b=tr.querySelector(".btn-pagar");if(!b)return;
if(sel)sel.classList.remove("table-active");sel=tr;tr.classList.add("table-active");reset();
document.getElementById("pconta").value=b.dataset.conta;document.getElementById("ptipo").value=b.dataset.tipo;
document.getElementById("pdesc").textContent=b.dataset.desc;
document.getElementById("ptot").textContent=b.dataset.totalFmt;
document.getElementById("ptotal").textContent=b.dataset.restaFmt;
v.dataset.max=b.dataset.resta;bar.classList.remove("d-none");});
document.getElementById("bpar").onclick=function(){document.getElementById("pmodo").value="parcial";v.classList.remove("d-none");v.disabled=false;v.required=true;bok.classList.remove("d-none");v.focus();};
document.getElementById("btot").onclick=function(){document.getElementById("pmodo").value="total";v.required=false;v.disabled=true;};
document.getElementById("bcan").onclick=fechar;
});
</script><?php page_footer();

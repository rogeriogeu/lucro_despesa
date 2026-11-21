<?php
require 'app.php';
require_login();
$pdo = db();

$desde = [
    'diario' => 'DATE_SUB(CURDATE(), INTERVAL 29 DAY)',
    'mensal' => "DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 11 MONTH), '%Y-%m-01')",
    'anual'  => "'2000-01-01'",
];
$fmt = ['diario' => '%Y-%m-%d', 'mensal' => '%Y-%m', 'anual' => '%Y'];
$dados = [];
foreach ($desde as $p => $ini) {
    $barras = $pdo->query("SELECT DATE_FORMAT(data,'{$fmt[$p]}') AS chave,
        COALESCE(SUM(CASE WHEN tipo='receita' THEN valor END),0) AS receita,
        COALESCE(SUM(CASE WHEN tipo='despesa' THEN valor END),0) AS despesa
        FROM lancamentos WHERE data >= $ini GROUP BY chave ORDER BY chave")->fetchAll();
    $pizza = ['receita' => [], 'despesa' => []];
    foreach ($pdo->query("SELECT l.tipo, COALESCE(g.nome,'Sem categoria') AS categoria, SUM(l.valor) AS total
        FROM lancamentos l LEFT JOIN categorias g ON g.id=l.categoria_id
        WHERE l.data >= $ini GROUP BY l.tipo, categoria ORDER BY total DESC") as $r) {
        $pizza[$r['tipo']][] = ['nome' => $r['categoria'], 'total' => (float)$r['total']];
    }
    $dados[$p] = ['barras' => $barras, 'pizza' => $pizza];
}
$tot = $pdo->query("SELECT COALESCE(SUM(CASE WHEN tipo='receita' THEN valor_pago END),0) AS r, COALESCE(SUM(CASE WHEN tipo='despesa' THEN valor_pago END),0) AS d FROM lancamentos")->fetch();
$lanc = $pdo->query("SELECT COALESCE(SUM(CASE WHEN tipo='receita' THEN valor END),0) AS r, COALESCE(SUM(CASE WHEN tipo='despesa' THEN valor END),0) AS d FROM lancamentos")->fetch();
$inicial = (float)$pdo->query('SELECT COALESCE(SUM(saldo_inicial),0) FROM contas')->fetchColumn();
$mes = $pdo->query("SELECT COALESCE(SUM(CASE WHEN tipo='receita' THEN valor_pago END),0) AS r, COALESCE(SUM(CASE WHEN tipo='despesa' THEN valor_pago END),0) AS d FROM lancamentos WHERE DATE_FORMAT(data,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')")->fetch();

$pend = $pdo->query("SELECT COALESCE(SUM(CASE WHEN tipo='receita' THEN valor-valor_pago END),0) AS r, COALESCE(SUM(CASE WHEN tipo='despesa' THEN valor-valor_pago END),0) AS d FROM lancamentos")->fetch();

page_header('Dashboard');
echo '<h4 class="mb-3">Dashboard</h4><div class="row g-3 mb-4">';
foreach ([['Receitas (total)', $lanc['r'], 'success'], ['Despesas (total)', $lanc['d'], 'danger'], ['Resultado do mês', $mes['r'] - $mes['d'], $mes['r'] - $mes['d'] >= 0 ? 'success' : 'danger'], ['Saldo das contas', $lanc['r'] - $lanc['d'], 'dark'], ['A receber (restante)', $pend['r'], 'success'], ['A pagar (restante)', $pend['d'], 'danger']] as [$t, $v, $c]) {
    echo '<div class="col-6 col-md-3' . (strpos($t, 'restante') ? ' col-lg-3' : '') . '"><div class="card h-100"><div class="card-body"><div class="text-muted">' . $t . '</div><div class="fs-4 fw-bold text-nowrap text-' . $c . '">' . brl($v) . '</div></div></div></div>';
}
echo '</div>';
?>
<div class="card mb-4"><div class="card-body">
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3"><h6 class="mb-0">Receitas × Despesas</h6>
<div class="btn-group btn-group-sm" id="grupo"><button class="btn btn-primary" data-p="diario">Dia (30 dias)</button><button class="btn btn-outline-primary" data-p="mensal">Mês (12 meses)</button><button class="btn btn-outline-primary" data-p="anual">Ano</button></div></div>
<div style="height:300px"><canvas id="barras"></canvas></div><p class="text-muted text-center mt-2 d-none" id="vazioB">Nenhum lançamento neste período.</p></div></div>
<div class="row g-3">
<div class="col-md-6"><div class="card h-100"><div class="card-body"><h6>Receitas por categoria</h6><div style="height:300px"><canvas id="pizzaR"></canvas></div><p class="text-muted text-center mt-2 d-none" id="vazioR">Sem receitas no período.</p></div></div></div>
<div class="col-md-6"><div class="card h-100"><div class="card-body"><h6>Despesas por categoria</h6><div style="height:300px"><canvas id="pizzaD"></canvas></div><p class="text-muted text-center mt-2 d-none" id="vazioD">Sem despesas no período.</p></div></div></div>
</div>
<script src="chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded',function(){
var D=<?= json_encode($dados) ?>,g={};
var m=function(v){return 'R$ '+Number(v).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2})};
var rot=function(k,p){var a=k.split('-');return p==='diario'?a[2]+'/'+a[1]:p==='mensal'?a[1]+'/'+a[0]:k};
var rotulos={id:'rotulos',afterDatasetsDraw:function(ch){var x=ch.ctx,pie=ch.config.type==='pie';x.save();x.font='bold 11px sans-serif';x.textAlign='center';ch.data.datasets.forEach(function(ds,i){var meta=ch.getDatasetMeta(i);if(meta.hidden)return;meta.data.forEach(function(el,j){var v=+ds.data[j];if(!v)return;var s=m(v);if(pie){var a=(el.startAngle+el.endAngle)/2,r=(el.innerRadius+el.outerRadius)/2*1.1;if(el.endAngle-el.startAngle<0.25)return;x.fillStyle='#fff';x.textBaseline='middle';x.fillText(s,el.x+Math.cos(a)*r*0.75,el.y+Math.sin(a)*r*0.75);}else{x.fillStyle='#333';x.textBaseline='bottom';x.fillText(s,el.x,el.y-3);}});});x.restore();}};
var cores=['#c62828','#ef6c00','#f9a825','#2e7d32','#00838f','#1565c0','#6a1b9a','#ad1457','#4e342e','#546e7a'];
function pizza(id,vazio,L){var c=document.getElementById(id);if(g[id])g[id].destroy();
document.getElementById(vazio).classList.toggle('d-none',L.length>0);c.parentNode.classList.toggle('d-none',!L.length);if(!L.length)return;
g[id]=new Chart(c,{type:'pie',plugins:[rotulos],data:{labels:L.map(function(x){return x.nome}),datasets:[{data:L.map(function(x){return x.total}),backgroundColor:L.map(function(x,i){return cores[i%cores.length]})}]},options:{maintainAspectRatio:false,plugins:{legend:{position:'right'},tooltip:{callbacks:{label:function(c){return c.label+': '+m(c.raw)}}}}}});}
function mostrar(p){var d=D[p],B=d.barras;if(g.b)g.b.destroy();document.getElementById('vazioB').classList.toggle('d-none',B.length>0);
g.b=new Chart(document.getElementById('barras'),{type:'bar',plugins:[rotulos],data:{labels:B.map(function(x){return rot(x.chave,p)}),datasets:[{label:'Receitas',data:B.map(function(x){return +x.receita}),backgroundColor:'#2e7d32'},{label:'Despesas',data:B.map(function(x){return +x.despesa}),backgroundColor:'#c62828'}]},options:{maintainAspectRatio:false,layout:{padding:{top:18}},scales:{y:{beginAtZero:true,ticks:{callback:function(v){return m(v)}}}},plugins:{tooltip:{callbacks:{label:function(c){return c.dataset.label+': '+m(c.raw)}}}}}});
pizza('pizzaR','vazioR',d.pizza.receita);pizza('pizzaD','vazioD',d.pizza.despesa);
document.querySelectorAll('#grupo button').forEach(function(b){b.className='btn '+(b.dataset.p===p?'btn-primary':'btn-outline-primary')});}
document.querySelectorAll('#grupo button').forEach(function(b){b.onclick=function(){mostrar(b.dataset.p)}});
mostrar('diario');});
</script>
<?php page_footer();

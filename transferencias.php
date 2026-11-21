<?php
require 'app.php';
require_login();
$pdo = db();

if (!transferencias_ok()) {
    page_header('Transferências');
    echo '<div class="alert alert-warning">O módulo de transferências está indisponível: a tabela <code>transferencias</code> não existe no banco. Execute o trecho correspondente de <code>schema.sql</code> no servidor.</div>';
    page_footer();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['acao'] ?? '') === 'excluir') {
        $pdo->prepare('DELETE FROM transferencias WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
        flash('Transferência estornada: valor devolvido à conta de origem.');
        redirect('transferencias.php');
    }
    $o = (int)($_POST['origem_id'] ?? 0);
    $d = (int)($_POST['destino_id'] ?? 0);
    $valor = num($_POST['valor'] ?? 0);
    $data = $_POST['data'] ?? '';
    $desc = trim($_POST['descricao'] ?? '') ?: null;
    $ok = $pdo->prepare('SELECT COUNT(*) FROM contas WHERE id IN (?,?)');
    $ok->execute([$o, $d]);
    if (!$o || !$d || $o === $d) flash('Escolha duas contas diferentes.', 'warning');
    elseif ($ok->fetchColumn() < 2) flash('Conta inválida.', 'warning');
    elseif ($valor <= 0) flash('Informe um valor maior que zero.', 'warning');
    elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) flash('Informe a data.', 'warning');
    else {
        $pdo->prepare('INSERT INTO transferencias (origem_id,destino_id,valor,descricao,data) VALUES (?,?,?,?,?)')->execute([$o, $d, $valor, $desc, $data]);
        flash('Transferência realizada.');
    }
    redirect('transferencias.php');
}

$contas = $pdo->query('SELECT id,nome FROM contas ORDER BY nome')->fetchAll();
page_header('Transferências');
echo '<div class="card mb-4"><div class="card-body"><form method="post" class="row g-2">' . csrf_field();
foreach (['origem_id' => 'De (conta de origem)', 'destino_id' => 'Para (conta de destino)'] as $n => $lb) {
    echo '<div class="col-md-3"><label class="form-label">' . $lb . '</label><select class="form-select" name="' . $n . '" required><option value="">Selecione...</option>';
    foreach ($contas as $c) echo '<option value="' . $c['id'] . '"' . ($n === 'origem_id' && $c['nome'] === 'Salário' ? ' selected' : '') . '>' . h($c['nome']) . '</option>';
    echo '</select></div>';
}
echo '<div class="col-md-2"><label class="form-label">Valor (R$)</label><input class="form-control" name="valor" required placeholder="0,00"></div>';
echo '<div class="col-md-2"><label class="form-label">Data</label><input type="date" class="form-control" name="data" required value="' . date('Y-m-d') . '"></div>';
echo '<div class="col-md-8"><label class="form-label">Descrição (opcional)</label><input class="form-control" name="descricao" maxlength="150"></div>';
echo '<div class="col-md-4 d-flex align-items-end"><button class="btn btn-primary">Transferir</button></div></form></div></div>';
echo '<div class="card"><div class="card-body"><table class="table table-striped dt w-100"><thead><tr><th>ID</th><th>Data</th><th>De</th><th>Para</th><th>Valor</th><th>Descrição</th><th>Ações</th></tr></thead><tbody>';
$sql = 'SELECT t.*, o.nome AS origem, d.nome AS destino FROM transferencias t JOIN contas o ON o.id=t.origem_id JOIN contas d ON d.id=t.destino_id ORDER BY t.data DESC, t.id DESC';
foreach ($pdo->query($sql) as $r) {
    echo '<tr><td>' . $r['id'] . '</td><td data-order="' . h($r['data']) . '">' . date('d/m/Y', strtotime($r['data'])) . '</td><td>' . h($r['origem']) . '</td><td>' . h($r['destino']) . '</td><td class="fw-bold">' . brl($r['valor']) . '</td><td>' . h($r['descricao']) . '</td><td><form method="post" class="d-inline" data-confirm="Estornar esta transferência? O valor volta para a conta de origem.">' . csrf_field() . '<input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="' . $r['id'] . '"><button class="btn btn-sm btn-outline-secondary">Estornar</button></form></td></tr>';
}
echo '</tbody></table></div></div>';
page_footer();

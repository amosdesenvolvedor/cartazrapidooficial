<?php
session_start();
require __DIR__ . '/db.php';
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'superadmin') {
    header('Location: login.php');
    exit;
}

$alert = null;
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editData = null;

if ($editId) {
    $stmtEdit = $pdo->prepare("SELECT * FROM plans WHERE id = ? LIMIT 1");
    $stmtEdit->execute([$editId]);
    $editData = $stmtEdit->fetch();
}

// Exclusão por POST (prioritária) ou GET (compatibilidade)
$deleteId = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int) $_POST['delete_id'];
} elseif (isset($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];
}
if ($deleteId) {
    try {
        $stmtDel = $pdo->prepare("DELETE FROM plans WHERE id = ?");
        $stmtDel->execute([$deleteId]);
        if ($stmtDel->rowCount() > 0) {
            $alert = ['type' => 'success', 'msg' => 'Plano removido com sucesso.'];
        } else {
            $alert = ['type' => 'warning', 'msg' => 'Plano não encontrado para remover.'];
        }
    } catch (PDOException $e) {
        $alert = ['type' => 'danger', 'msg' => 'Erro ao deletar: ' . $e->getMessage()];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $duration = (int)($_POST['duration_days'] ?? 0);
    $cycle = trim($_POST['billing_cycle'] ?? 'monthly');
    $desc = trim($_POST['description'] ?? '');
    $pid = (int)($_POST['plan_id'] ?? 0);

    if ($name && $duration > 0) {
        try {
            if ($pid) {
                $stmt = $pdo->prepare("UPDATE plans SET name=?, price=?, duration_days=?, billing_cycle=?, description=? WHERE id=?");
                $stmt->execute([$name, $price, $duration, $cycle, $desc, $pid]);
                $alert = ['type' => 'success', 'msg' => 'Plano atualizado.'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO plans (name, price, duration_days, billing_cycle, description) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $price, $duration, $cycle, $desc]);
                $alert = ['type' => 'success', 'msg' => 'Plano criado.'];
            }
        } catch (PDOException $e) {
            $alert = ['type' => 'danger', 'msg' => 'Erro ao salvar: ' . $e->getMessage()];
        }
    } else {
        $alert = ['type' => 'warning', 'msg' => 'Informe nome e duração.'];
    }
}

$plans = $pdo->query("SELECT * FROM plans ORDER BY price, id")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Admin - Planos</title>
    <link rel="icon" type="image/x-icon" href="/public/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <style>
        body { font-family: 'Montserrat', sans-serif; background: #f8fafb; }
        .sidebar { background: #111927; color: #e9eef7; min-height: 100vh; padding: 22px 18px; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <aside class="col-md-3 col-lg-2 sidebar">
            <div class="d-flex align-items-center mb-2">
                <div>
                    <div class="fw-bold">Cartaz Rápido</div>
                    <small class="text-muted">Painel administrativo</small>
                </div>
                <a class="btn btn-sm btn-outline-danger ms-auto" href="logout.php">Sair</a>
            </div>
            <div class="list-group mt-3 list-group-flush">
                <a class="list-group-item list-group-item-action" href="admin.php">Acesso</a>
                <a class="list-group-item list-group-item-action" href="admin_clientes.php">Clientes</a>
                <a class="list-group-item list-group-item-action active" aria-current="true" href="admin_planos.php">Planos</a>
                <a class="list-group-item list-group-item-action" href="index.php">Voltar ao gerador</a>
            </div>
        </aside>
        <main class="col-md-9 col-lg-10 py-4">
            <h4 class="mb-3">Planos</h4>
            <?php if ($alert): ?>
                <div class="alert alert-<?= htmlspecialchars($alert['type']) ?>"><?= htmlspecialchars($alert['msg']) ?></div>
            <?php endif; ?>
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card p-3">
                        <h5 class="mb-0"><?= $editData ? "Editar plano #{$editData['id']}" : 'Novo plano' ?></h5>
                        <form class="mt-3" method="post">
                            <input type="hidden" name="plan_id" value="<?= (int)($editData['id'] ?? 0) ?>">
                            <div class="mb-3">
                                <label class="form-label">Nome</label>
                                <input class="form-control" name="name" value="<?= htmlspecialchars($editData['name'] ?? '') ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Preço (R$)</label>
                                <input class="form-control" type="number" step="0.01" min="0" name="price" value="<?= htmlspecialchars($editData['price'] ?? '0.00') ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Duração (dias)</label>
                                <input class="form-control" type="number" min="1" name="duration_days" value="<?= htmlspecialchars($editData['duration_days'] ?? '30') ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Ciclo</label>
                                <select class="form-select" name="billing_cycle">
                                    <?php $cycle = $editData['billing_cycle'] ?? 'monthly'; ?>
                                    <option value="demo" <?= $cycle === 'demo' ? 'selected' : '' ?>>Demo</option>
                                    <option value="monthly" <?= $cycle === 'monthly' ? 'selected' : '' ?>>Mensal</option>
                                    <option value="annual" <?= $cycle === 'annual' ? 'selected' : '' ?>>Anual</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Descrição</label>
                                <input class="form-control" name="description" value="<?= htmlspecialchars($editData['description'] ?? '') ?>">
                            </div>
                            <div class="d-grid gap-2">
                                <button class="btn btn-primary" type="submit"><?= $editData ? 'Atualizar' : 'Salvar' ?></button>
                                <?php if ($editData): ?>
                                    <a class="btn btn-outline-secondary" href="admin_planos.php">Cancelar edição</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="mb-0">Lista de planos</h5>
                            <span class="text-muted small"><?= count($plans) ?></span>
                        </div>
                        <?php if (empty($plans)): ?>
                            <div class="alert alert-light border">Nenhum plano cadastrado.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Nome</th>
                                        <th>Preço</th>
                                        <th>Dias</th>
                                        <th>Ciclo</th>
                                        <th></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($plans as $p): ?>
                                        <tr>
                                            <td><?= (int)$p['id'] ?></td>
                                            <td><?= htmlspecialchars($p['name']) ?></td>
                                            <td>R$ <?= number_format((float)$p['price'], 2, ',', '.') ?></td>
                                            <td><?= (int)$p['duration_days'] ?></td>
                                            <td><?= htmlspecialchars($p['billing_cycle']) ?></td>
                                            <td class="text-end">
                                                <a class="btn btn-sm btn-outline-primary" href="admin_planos.php?edit=<?= (int)$p['id'] ?>">Editar</a>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Deseja remover este plano?');">
                                                    <input type="hidden" name="delete_id" value="<?= (int)$p['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Excluir</button>
                                                </form>
                                                <!-- fallback GET para compatibilidade -->
                                                <a class="btn btn-sm btn-outline-danger d-none" href="admin_planos.php?delete=<?= (int)$p['id'] ?>">Excluir</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
</body>
</html>

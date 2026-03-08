<?php
session_start();
require __DIR__ . '/db.php';
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'superadmin') {
    header('Location: login.php');
    exit;
}

function slugify($str) {
    $str = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
    $str = strtolower(trim($str));
    $str = preg_replace('/[^a-z0-9]+/', '-', $str);
    $str = trim($str, '-');
    return $str ?: 'user';
}

$alert = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = trim($_POST['role'] ?? 'cliente');
    $password = trim($_POST['password'] ?? '');

    if ($name && $email && $password) {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $slugBase = slugify($name);
            $slug = $slugBase;
            $suffix = 1;
            while (true) {
                $check = $pdo->prepare('SELECT id FROM users WHERE slug = ? LIMIT 1');
                $check->execute([$slug]);
                if (!$check->fetchColumn()) break;
                $slug = $slugBase . '-' . $suffix++;
            }
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, slug, role) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$name, $email, $hash, $slug, $role]);
            $alert = ['type' => 'success', 'msg' => 'Usuário cadastrado com sucesso.'];
        } catch (PDOException $e) {
            $alert = ['type' => 'danger', 'msg' => 'Erro ao salvar: ' . $e->getMessage()];
        }
    } else {
        $alert = ['type' => 'warning', 'msg' => 'Preencha nome, e-mail e senha.'];
    }
}

if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    if ($id > 0) {
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
        header('Location: admin.php');
        exit;
    }
}

$users = $pdo->query('
    SELECT u.id, u.name, u.email, u.role, u.slug, u.created_at, c.id AS client_id
    FROM users u
    LEFT JOIN clients_users c ON c.user_id = u.id
    ORDER BY u.created_at DESC
')->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Admin - Cartaz Rápido</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/public/favicon.ico">
    <style>
        body { font-family: 'Montserrat', sans-serif; background: #f8fafb; }
        .sidebar { background: #111927; color: #e9eef7; min-height: 100vh; padding: 22px 18px; }
        .card { border: none; box-shadow: 0 12px 28px rgba(0,0,0,0.08); background: #fdfdfd; }
        .table thead { background: #f1f5f9; }
        .table-hover tbody tr:hover { background: #f8fafc; }
        .table-striped-custom tbody tr:nth-of-type(odd) { background: #f8fbff; }
        .table-striped-custom tbody tr:nth-of-type(even) { background: #ffffff; }
        .name-cell { text-transform: capitalize; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <aside class="col-md-3 col-lg-2 sidebar min-vh-100 py-3">
            <div class="px-3 mb-3 text-white d-flex align-items-center">
                <div>
                    <div class="fw-bold">Cartaz Rápido</div>
                    <small class="text-muted">Painel administrativo</small>
                </div>
                <a class="btn btn-sm btn-outline-danger ms-auto" href="logout.php">Sair</a>
            </div>
            <div class="list-group list-group-flush">
                <a class="list-group-item list-group-item-action active" aria-current="true" href="admin.php">Acesso</a>
                <a class="list-group-item list-group-item-action" href="admin_clientes.php">Clientes</a>
                <a class="list-group-item list-group-item-action" href="admin_planos.php">Planos</a>
                <a class="list-group-item list-group-item-action" href="index.php">Voltar ao gerador</a>
            </div>
        </aside>
        <main class="col-md-9 col-lg-10 py-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0">Acesso</h4>
            </div>

    <?php if ($alert): ?>
        <div class="alert alert-<?= htmlspecialchars($alert['type']) ?>"><?= htmlspecialchars($alert['msg']) ?></div>
    <?php endif; ?>

            <div class="row g-4">
                <div class="col-xl-4 col-lg-5" id="cadastro">
                    <div class="card p-3">
                        <h5 class="mb-0">Cadastro de acesso</h5>
                        <form method="post" class="mt-3">
                            <div class="mb-3">
                                <label class="form-label">Nome</label>
                                <input class="form-control" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">E-mail</label>
                                <input class="form-control" type="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Senha</label>
                                <input class="form-control" type="password" name="password" required>
                                <div class="form-text">Será armazenada com hash.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tipo</label>
                                <select class="form-select" name="role">
                                    <option value="cliente">Cliente</option>
                                    <option value="superadmin">Superadmin</option>
                                </select>
                            </div>
                            <button class="btn btn-primary w-100" type="submit">Salvar</button>
                        </form>
                    </div>
                </div>
                <div class="col-xl-8 col-lg-7">
                    <div class="card p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="mb-0">Lista de acesso</h5>
                                <small class="text-muted">Total: <?= count($users) ?></small>
                            </div>
                        </div>
                        <?php if (empty($users)): ?>
                            <div class="alert alert-light border">Nenhum usuário cadastrado.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped-custom align-middle mb-0">
                                    <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Nome</th>
                                        <th>E-mail</th>
                                        <th>Tipo</th>
                                        <th>Slug</th>
                                        <th>Cliente</th>
                                        <th>Criado</th>
                                        <th></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?= (int) $user['id'] ?></td>
                                            <td class="name-cell"><?= htmlspecialchars($user['name']) ?></td>
                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                            <td><?= htmlspecialchars($user['role'] ?? '') ?></td>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars($user['slug'] ?? '') ?></span></td>
                                            <td><?= htmlspecialchars($user['client_id'] ? 'Vinculado' : '—') ?></td>
                                            <td><?= htmlspecialchars($user['created_at'] ?? '') ?></td>
                                            <td class="text-end">
                                                <?php if (!empty($user['client_id'])): ?>
                                                    <a class="btn btn-sm btn-outline-primary me-1" href="admin_clientes.php?client_id=<?= (int)$user['client_id'] ?>">Editar cliente</a>
                                                <?php else: ?>
                                                    <a class="btn btn-sm btn-outline-secondary me-1" href="admin_clientes.php">Criar cliente</a>
                                                <?php endif; ?>
                                                <a class="btn btn-sm btn-outline-danger" href="?delete=<?= (int)$user['id'] ?>" onclick="return confirm('Deseja remover?')">Excluir</a>
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

<?php
session_start();
require __DIR__ . '/db.php';

function slugifyReg($str) {
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
    $password = trim($_POST['password'] ?? '');
    $role = 'cliente';

    if ($name && $email && $password) {
        try {
            $pdo->beginTransaction();

            // Insere em clients_users.
            $stmtClient = $pdo->prepare('INSERT INTO clients_users (name, email, role) VALUES (?, ?, ?)');
            $stmtClient->execute([$name, $email, $role]);

            // Gera slug único para users.
            $slugBase = slugifyReg($name);
            $slug = $slugBase;
            $suffix = 1;
            while (true) {
                $check = $pdo->prepare('SELECT id FROM users WHERE slug = ? LIMIT 1');
                $check->execute([$slug]);
                if (!$check->fetchColumn()) break;
                $slug = $slugBase . '-' . $suffix++;
            }

            // Insere em users (acesso).
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmtUser = $pdo->prepare('INSERT INTO users (name, email, password_hash, slug, role) VALUES (?, ?, ?, ?, ?)');
            $stmtUser->execute([$name, $email, $hash, $slug, $role]);

            $pdo->commit();
            $alert = ['type' => 'success', 'msg' => 'Cadastro realizado com sucesso.'];
        } catch (PDOException $e) {
            $pdo->rollBack();
            $alert = ['type' => 'danger', 'msg' => 'Erro ao salvar: ' . $e->getMessage()];
        }
    } else {
        $alert = ['type' => 'warning', 'msg' => 'Preencha nome, e-mail e senha.'];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Cartaz Rápido</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="icon" type="image/x-icon" href="/public/favicon.ico">
    <style>
        body { background: #0f172a; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .register-card { width: 380px; border: none; border-radius: 14px; box-shadow: 0 16px 32px rgba(0,0,0,0.25); }
    </style>
</head>
<body>
<div class="card register-card p-4 bg-dark text-white">
    <h4 class="mb-3 text-center">Cadastro de Cliente</h4>
    <?php if ($alert): ?>
        <div class="alert alert-<?= htmlspecialchars($alert['type']) ?>"><?= htmlspecialchars($alert['msg']) ?></div>
    <?php endif; ?>
    <form method="post">
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
            <input class="form-control" type="password" name="password" id="registerPassword" required>
            <div class="form-check mt-2 d-flex align-items-center gap-2">
                <input class="form-check-input bg-dark border-secondary" type="checkbox" id="registerShowPassword">
                <label class="form-check-label text-white" for="registerShowPassword">Mostrar senha</label>
            </div>
            <div class="form-text text-light">Sua senha será armazenada com hash.</div>
        </div>
        <button class="btn btn-primary w-100" type="submit">Registrar</button>
    </form>
    <div class="mt-3 text-center">
        <a class="text-decoration-none text-light" href="login.php">Voltar ao login</a>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var toggleShow = document.getElementById('registerShowPassword');
    var passwordInput = document.getElementById('registerPassword');
    if (toggleShow && passwordInput) {
        toggleShow.addEventListener('change', function () {
            passwordInput.type = this.checked ? 'text' : 'password';
            passwordInput.focus({preventScroll: true});
        });
    }
});
</script>
</body>
</html>

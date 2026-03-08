<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/payments/helpers.php';

$error = null;

if (isset($_SESSION['user'])) {
    $role = $_SESSION['user']['role'] ?? 'cliente';
    if ($role === 'superadmin') {
        header('Location: admin.php');
        exit;
    }
    $redirect = 'index.php';
    if ($role === 'cliente') {
        $client = ensureClientForUser($pdo, (int)$_SESSION['user']['id'], $_SESSION['user']['name'] ?? '', $_SESSION['user']['email'] ?? '');
        $activeSubscription = getActiveSubscription($pdo, (int)$client['id']);
        if ($activeSubscription) {
            $redirect = 'template1.php';
        }
    }
    header('Location: ' . $redirect);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';

    if ($email && $pass) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($pass, $user['password_hash'])) {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
            ];
            if ($user['role'] === 'superadmin') {
                header('Location: admin.php');
                exit;
            }

            $redirect = 'index.php';
            if ($user['role'] === 'cliente') {
                $client = ensureClientForUser($pdo, (int)$user['id'], $user['name'], $user['email']);
                $activeSubscription = getActiveSubscription($pdo, (int)$client['id']);
                if ($activeSubscription) {
                    $redirect = 'template1.php';
                }
            }

            header('Location: ' . $redirect);
            exit;
        } else {
            $error = 'Credenciais inválidas.';
        }
    } else {
        $error = 'Informe e-mail e senha.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Cartaz Rápido</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
     <link rel="icon" type="image/x-icon" href="/public/favicon.ico">
    <style>
        body { background: #0f172a; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { width: 360px; border: none; border-radius: 14px; box-shadow: 0 16px 32px rgba(0,0,0,0.25); }
    </style>
</head>
<body>
<div class="card login-card p-4 bg-dark text-white">
    <h4 class="mb-3 text-center"> Cartaz Rápido</h4>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="mb-3">
            <label class="form-label">E-mail</label>
            <input type="email" class="form-control" name="email" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Senha</label>
            <input type="password" class="form-control" name="password" id="loginPassword" required>
            <div class="form-check mt-2 d-flex align-items-center gap-2">
                <input class="form-check-input bg-dark border-secondary" type="checkbox" id="loginShowPassword">
                <label class="form-check-label text-white" for="loginShowPassword">Mostrar senha</label>
            </div>
        </div>
        <button class="btn btn-primary w-100" type="submit">Entrar</button>
    </form>
    <div class="mt-3 text-center">
        <a class="text-decoration-none text-light" href="register.php">Registre-se</a>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var toggleShow = document.getElementById('loginShowPassword');
    var passwordInput = document.getElementById('loginPassword');
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

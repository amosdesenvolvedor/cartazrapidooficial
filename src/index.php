<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
require __DIR__ . '/db.php';
require __DIR__ . '/payments/helpers.php';

$alert = null;
$userId = (int)$_SESSION['user']['id'];

// Carrega dados do usuário e cliente vinculado
$stmtUser = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ? LIMIT 1");
$stmtUser->execute([$userId]);
$userData = $stmtUser->fetch();

$stmtClient = $pdo->prepare("SELECT * FROM clients_users WHERE user_id = ? LIMIT 1");
$stmtClient->execute([$userId]);
$clientData = $stmtClient->fetch();

if (!$clientData) {
    $clientData = ensureClientForUser($pdo, $userData['id'], $userData['name'], $userData['email']);
}

$clientId = (int)($clientData['id'] ?? 0);
$activeSubscription = $clientId ? getActiveSubscription($pdo, $clientId) : null;
$currentPlanId = $activeSubscription['plan_id'] ?? ($clientData['current_plan_id'] ?? null);
$hasPlan = (bool)$activeSubscription;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $razao = trim($_POST['razao_social'] ?? '');
    $fantasia = trim($_POST['fantasia'] ?? '');
    $cpfCnpj = trim($_POST['cpf_cnpj'] ?? '');
    $ie = trim($_POST['inscricao_estadual'] ?? '');
    $logradouro = trim($_POST['logradouro'] ?? '');
    $numero = trim($_POST['numero'] ?? '');
    $bairro = trim($_POST['bairro'] ?? '');
    $cep = trim($_POST['cep'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $pais = trim($_POST['pais'] ?? 'Brasil');

    if ($nome && $email) {
        $stmtEmailCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmtEmailCheck->execute([$email]);
        $existingUserId = (int)$stmtEmailCheck->fetchColumn();
        if ($existingUserId && $existingUserId !== $userId) {
            $alert = ['type' => 'warning', 'msg' => 'O e-mail informado já está em uso por outro usuário.'];
        } else {
            try {
                $updUser = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                $updUser->execute([$nome, $email, $userId]);

                if ($clientData) {
                    $updClient = $pdo->prepare("UPDATE clients_users SET name=?, email=?, razao_social=?, fantasia=?, cpf_cnpj=?, inscricao_estadual=?, logradouro=?, numero=?, bairro=?, cep=?, cidade=?, pais=?, current_plan_id=? WHERE id=?");
                    $updClient->execute([$razao ?: $nome, $email, $razao ?: $nome, $fantasia, $cpfCnpj, $ie, $logradouro, $numero, $bairro, $cep, $cidade, $pais, $currentPlanId, $clientData['id']]);
                } else {
                    $insClient = $pdo->prepare("INSERT INTO clients_users (name, email, role, user_id, razao_social, fantasia, cpf_cnpj, inscricao_estadual, logradouro, numero, bairro, cep, cidade, pais, current_plan_id) VALUES (?, ?, 'cliente', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $insClient->execute([$razao ?: $nome, $email, $userId, $razao ?: $nome, $fantasia, $cpfCnpj, $ie, $logradouro, $numero, $bairro, $cep, $cidade, $pais, $currentPlanId]);
                }

                // atualiza sessão
                $_SESSION['user']['name'] = $nome;
                $_SESSION['user']['email'] = $email;

                $alert = ['type' => 'success', 'msg' => 'Dados atualizados com sucesso.'];
                // recarrega dados
                $stmtClient->execute([$userId]);
                $clientData = $stmtClient->fetch();
                $clientId = $clientData['id'] ?? 0;
            } catch (PDOException $e) {
                $alert = ['type' => 'danger', 'msg' => 'Erro ao salvar: ' . $e->getMessage()];
            }
        }
    } else {
        $alert = ['type' => 'warning', 'msg' => 'Informe nome e e-mail.'];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cartaz Rápido - Perfil do Cliente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800&display=swap" rel="stylesheet">
     <link rel="icon" type="image/x-icon" href="/public/favicon.ico">
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=AW-11414289884"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'AW-11414289884');
    </script>
    <style>
        body { background: #f3f6f8; font-family: 'Montserrat', sans-serif; }
        .sidebar { background: #111927; color: #e9eef7; min-height: 100vh; padding: 22px 18px; box-shadow: 6px 0 22px rgba(0,0,0,0.2); }
        .brand { font-weight: 800; letter-spacing: 0.4px; color: #ffd43b; }
        .card-preview { border: none; border-radius: 14px; box-shadow: 0 16px 32px rgba(0,0,0,0.14); overflow: hidden; transition: transform .1s ease; }
        .card-preview:hover { transform: translateY(-4px); }
        .pill { display: inline-block; background: #eef2ff; color: #1f2937; padding: 8px 12px; border-radius: 12px; font-weight: 700; letter-spacing: 0.4px; }
        .profile-card { border: none; border-radius: 16px; box-shadow: 0 18px 32px rgba(0,0,0,0.12); }
        .label-muted { color: #6b7280; font-size: 13px; text-transform: uppercase; letter-spacing: 0.4px; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-3 col-md-4 sidebar">
            <div class="d-flex align-items-center mb-4">
                <div>
                    <div class="brand">Cartaz Rápido</div>
                    <div class="text-muted small">Perfil do cliente</div>
                </div>
                <a href="logout.php" class="ms-auto btn btn-sm btn-outline-danger">Sair</a>
            </div>
            <div class="mb-4">
                <div class="small text-uppercase text-muted">Usuário</div>
                <div class="fw-bold" id="sidebarNome"><?= htmlspecialchars($userData['name'] ?? '') ?></div>
                <div class="text-muted small"><?= htmlspecialchars($userData['email'] ?? '') ?></div>
                <div class="mt-2">
                    <a
                        class="btn btn-sm btn-success w-100"
                        href="https://wa.me/5569992507789?text=Preciso%20de%20suporte%20para%20confirmar%20meu%20pagamento."
                        target="_blank"
                        rel="noopener noreferrer"
                    >Suporte (WhatsApp)</a>
                </div>
            </div>
            <div class="mb-4">
                <div class="small text-uppercase text-muted">Plano</div>
                <?php if ($activeSubscription): ?>
                    <div class="small mb-1 fw-bold"><?= htmlspecialchars($activeSubscription['plan_name']) ?></div>
                    <div class="text-muted small">Expira em <?= date('d/m/Y', strtotime($activeSubscription['expires_at'])) ?></div>
                <?php else: ?>
                    <div class="text-warning small mb-2">Nenhum plano ativo.</div>
                    <a class="btn btn-sm btn-outline-light" href="select_plan.php">Escolher plano</a>
                <?php endif; ?>
            </div>
            <div class="list-group">
                <a class="list-group-item list-group-item-action active" aria-current="true" href="index.php">Home</a>
                <a class="list-group-item list-group-item-action" href="template1.php">Modelo 01 - Clássico</a>
                <a class="list-group-item list-group-item-action" href="template2.php">Modelo 02 - Curvas</a>
                <a class="list-group-item list-group-item-action" href="template3.php">Modelo 03 - Curvas Sem Contorno</a>
                <a class="list-group-item list-group-item-action" href="template4.php">Modelo 04 - Aviso</a>
                <a class="list-group-item list-group-item-action" href="template5.php">Modelo 05 - Curvas Fundo Personalizado</a>
                <?php if (($_SESSION['user']['role'] ?? '') === 'superadmin'): ?>
                    <a class="list-group-item list-group-item-action" href="admin.php">Painel Admin</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-9 col-md-8 py-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0 fw-bold">Perfil do cliente</h4>
                <?php if ($alert): ?>
                    <span class="badge bg-<?= htmlspecialchars($alert['type']) ?>"><?= htmlspecialchars($alert['msg']) ?></span>
                <?php elseif (!$activeSubscription): ?>
                    <span class="badge bg-warning text-dark">Plano inativo</span>
                <?php endif; ?>
            </div>
            <div class="card profile-card p-4">
                <?php if (!$activeSubscription): ?>
                    <div class="alert alert-warning d-flex justify-content-between align-items-center">
                        <div>Você não possui um plano ativo. Ative um plano para acessar os modelos.</div>
                        <a class="btn btn-sm btn-primary" href="select_plan.php">Escolher plano</a>
                    </div>
                <?php else: ?>
                <?php $planExpiry = $activeSubscription['expires_at'] ?? null; ?>
                <div class="alert alert-success d-flex justify-content-between align-items-center">
                    <div>
                            <div class="small text-uppercase text-muted mb-1">Plano ativo</div>
                            <div class="fw-bold mb-1"><?= htmlspecialchars($activeSubscription['plan_name']) ?></div>
                            <?php if ($planExpiry): ?>
                                <div class="text-muted small">Expira em <?= date('d/m/Y', strtotime($planExpiry)) ?></div>
                            <?php endif; ?>
                        </div>
                        <a class="btn btn-sm btn-outline-light" href="select_plan.php">Trocar plano</a>
                    </div>
                <?php endif; ?>
                <form method="post" class="row g-3">
                    <div class="col-md-6">
                        <label class="label-muted">Nome</label>
                        <input class="form-control" name="nome" value="<?= htmlspecialchars($userData['name'] ?? '') ?>" placeholder="Ex: Mercadinho Central" required>
                    </div>
                    <div class="col-md-6">
                        <label class="label-muted">Contato / E-mail</label>
                        <input class="form-control" type="email" name="email" value="<?= htmlspecialchars($userData['email'] ?? '') ?>" placeholder="Ex: contato@cliente.com" required>
                    </div>
                    <div class="col-md-6">
                        <label class="label-muted">Razão social</label>
                        <input class="form-control" name="razao_social" value="<?= htmlspecialchars($clientData['razao_social'] ?? '') ?>" placeholder="Ex: Empresa Exemplo LTDA">
                    </div>
                    <div class="col-md-6">
                        <label class="label-muted">Nome fantasia / Apelido</label>
                        <input class="form-control" name="fantasia" value="<?= htmlspecialchars($clientData['fantasia'] ?? '') ?>" placeholder="Ex: Mercado Exemplo">
                    </div>
                    <div class="col-md-6">
                        <label class="label-muted">CPF/CNPJ</label>
                        <input class="form-control" name="cpf_cnpj" value="<?= htmlspecialchars($clientData['cpf_cnpj'] ?? '') ?>" placeholder="Somente números">
                    </div>
                    <div class="col-md-6">
                        <label class="label-muted">Inscrição estadual</label>
                        <input class="form-control" name="inscricao_estadual" value="<?= htmlspecialchars($clientData['inscricao_estadual'] ?? '') ?>" placeholder="Ex: 123.456.789.000">
                    </div>
                    <div class="col-md-6">
                        <label class="label-muted">Logradouro</label>
                        <input class="form-control" name="logradouro" value="<?= htmlspecialchars($clientData['logradouro'] ?? '') ?>" placeholder="Rua, Avenida...">
                    </div>
                    <div class="col-md-3">
                        <label class="label-muted">Número</label>
                        <input class="form-control" name="numero" value="<?= htmlspecialchars($clientData['numero'] ?? '') ?>" placeholder="123">
                    </div>
                    <div class="col-md-3">
                        <label class="label-muted">Bairro</label>
                        <input class="form-control" name="bairro" value="<?= htmlspecialchars($clientData['bairro'] ?? '') ?>" placeholder="Bairro">
                    </div>
                    <div class="col-md-4">
                        <label class="label-muted">CEP</label>
                        <input class="form-control" name="cep" value="<?= htmlspecialchars($clientData['cep'] ?? '') ?>" placeholder="00000-000">
                    </div>
                    <div class="col-md-4">
                        <label class="label-muted">Cidade</label>
                        <input class="form-control" name="cidade" value="<?= htmlspecialchars($clientData['cidade'] ?? '') ?>" placeholder="Cidade">
                    </div>
                    <div class="col-md-4">
                        <label class="label-muted">País</label>
                        <input class="form-control" name="pais" value="<?= htmlspecialchars($clientData['pais'] ?? 'Brasil') ?>" placeholder="Brasil">
                    </div>
                    <div class="col-md-6">
                        <label class="label-muted">Expira em</label>
                        <?php $expirationFieldValue = $activeSubscription['expires_at'] ? date('Y-m-d', strtotime($activeSubscription['expires_at'])) : ''; ?>
                        <input class="form-control" type="date" name="expira_em" value="<?= htmlspecialchars($expirationFieldValue) ?>">
                        <div class="small text-muted">Atualiza a data de expiração do plano ativo.</div>
                    </div>
                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-success" type="submit">Salvar perfil</button>
                        <a class="btn btn-outline-secondary" href="index.php">Cancelar</a>
                    </div>
                </form>
                <div class="mt-4">
                    <div class="label-muted mb-1">Acessos rápidos</div>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-primary btn-sm" href="template1.php">Abrir Template 01</a>
                        <a class="btn btn-outline-warning btn-sm" href="template2.php">Abrir Template 02</a>
                        <a class="btn btn-outline-danger btn-sm" href="template3.php">Abrir Template 03</a>
                        <a class="btn btn-outline-info btn-sm" href="template4.php">Abrir Template 04</a>
                        <?php if (($_SESSION['user']['role'] ?? '') === 'superadmin'): ?>
                            <a class="btn btn-outline-dark btn-sm" href="admin.php">Painel Admin</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>

<?php
session_start();
require __DIR__ . '/db.php';
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'superadmin') {
    header('Location: login.php');
    exit;
}

$alert = null;
$isInlineExpirationUpdate = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_expiration']);
$isInlinePlanUpdate = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_plan']);

// Carrega dados para edição se houver user_id.
$editClientId = isset($_GET['client_id']) ? (int) $_GET['client_id'] : 0;
$linkUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
$linkedUser = null;
$editData = null;
$editQuery = "
    SELECT
        c.id,
        u.id as user_id,
        u.name as usuario,
        u.email,
        c.*,
        s.id AS subscription_id,
        s.plan_id AS subscription_plan_id,
        s.expires_at
    FROM clients_users c
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN (
        SELECT s1.*
        FROM subscriptions s1
        INNER JOIN (
            SELECT client_user_id, MAX(expires_at) AS max_exp
            FROM subscriptions
            WHERE status = 'paid'
            GROUP BY client_user_id
        ) s2 ON s2.client_user_id = s1.client_user_id AND s2.max_exp = s1.expires_at
        WHERE s1.status = 'paid'
    ) s ON s.client_user_id = c.id
    WHERE c.id = ?
    LIMIT 1
";
$stmtEdit = $pdo->prepare($editQuery);

/** @return array|false */
function fetchEditClientData($stmt, int $clientId)
{
    $stmt->execute([$clientId]);
    return $stmt->fetch();
}

if ($editClientId) {
    $editData = fetchEditClientData($stmtEdit, $editClientId);
    if ($editData) {
        $linkUserId = (int)($editData['user_id'] ?? 0);
        if ($linkUserId) {
            $linkedUser = [
                'id' => $linkUserId,
                'name' => $editData['usuario'] ?? '',
                'email' => $editData['email'] ?? '',
            ];
        }
    } else {
        $linkUserId = 0;
    }
} elseif ($linkUserId) {
    $stmtUser = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ? LIMIT 1");
    $stmtUser->execute([$linkUserId]);
    $linkedUser = $stmtUser->fetch();
    if (!$linkedUser) {
        $linkUserId = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isInlinePlanUpdate) {
        $subscriptionId = (int)($_POST['subscription_id'] ?? 0);
        $planId = (int)($_POST['plan_id'] ?? 0);
        $clientId = (int)($_POST['client_id'] ?? 0);
        if ($subscriptionId && $planId) {
            try {
                $stmtPlanCheck = $pdo->prepare("SELECT id FROM plans WHERE id = ? LIMIT 1");
                $stmtPlanCheck->execute([$planId]);
                if ($stmtPlanCheck->fetchColumn()) {
                    $stmtSubPlan = $pdo->prepare("UPDATE subscriptions SET plan_id = ? WHERE id = ?");
                    $stmtSubPlan->execute([$planId, $subscriptionId]);
                    if ($clientId) {
                        $stmtClientPlan = $pdo->prepare("UPDATE clients_users SET current_plan_id = ? WHERE id = ?");
                        $stmtClientPlan->execute([$planId, $clientId]);
                    }
                    $alert = ['type' => 'success', 'msg' => 'Plano atualizado com sucesso.'];
                } else {
                    $alert = ['type' => 'warning', 'msg' => 'Plano selecionado inválido.'];
                }
            } catch (PDOException $e) {
                $alert = ['type' => 'danger', 'msg' => 'Erro ao atualizar plano: ' . $e->getMessage()];
            }
        } else {
            $alert = ['type' => 'warning', 'msg' => 'Selecione um plano válido.'];
        }
    } elseif ($isInlineExpirationUpdate) {
        $subscriptionId = (int)($_POST['subscription_id'] ?? 0);
        $expiresInput = trim($_POST['expira_em'] ?? '');
        $selectedPlanId = (int)($_POST['plan_id'] ?? 0);
        if ($subscriptionId && $expiresInput) {
            $dt = DateTime::createFromFormat('Y-m-d', $expiresInput);
            if ($dt) {
                try {
                    $expiresAt = $dt->setTime(23, 59, 59)->format('Y-m-d H:i:s');
                    $stmtSub = $pdo->prepare("UPDATE subscriptions SET expires_at = ? WHERE id = ?");
                    $stmtSub->execute([$expiresAt, $subscriptionId]);
                    $alert = ['type' => 'success', 'msg' => 'Expiração do plano atualizada.'];
                } catch (PDOException $e) {
                    $alert = ['type' => 'danger', 'msg' => 'Erro ao atualizar expiração: ' . $e->getMessage()];
                }
            } else {
                $alert = ['type' => 'warning', 'msg' => 'Data de expiração inválida.'];
            }
        } else {
            $alert = ['type' => 'warning', 'msg' => 'Selecione um plano e defina uma data.'];
        }
    } else {
        $razao = trim($_POST['razao_social'] ?? '');
        $fantasia = trim($_POST['fantasia'] ?? '');
        $cpfCnpj = trim($_POST['cpf_cnpj'] ?? '');
        $ie = trim($_POST['inscricao_estadual'] ?? '');
        $logra = trim($_POST['logradouro'] ?? '');
        $numero = trim($_POST['numero'] ?? '');
        $bairro = trim($_POST['bairro'] ?? '');
        $cep = trim($_POST['cep'] ?? '');
        $cidade = trim($_POST['cidade'] ?? '');
        $pais = trim($_POST['pais'] ?? 'Brasil');
        $subscriptionId = (int)($_POST['subscription_id'] ?? 0);
        $expiresInput = trim($_POST['expira_em'] ?? '');
        $clientId = (int)($_POST['client_id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);
        $selectedPlanId = (int)($_POST['plan_id'] ?? 0);

        // Recupera vínculo existente (para manter user/email ao editar)
        $existing = null;
        if ($clientId) {
            $stmtExisting = $pdo->prepare("SELECT user_id, email FROM clients_users WHERE id = ? LIMIT 1");
            $stmtExisting->execute([$clientId]);
            $existing = $stmtExisting->fetch();
            $userId = (int)($existing['user_id'] ?? 0);
        }
        // Busca email do usuário (se houver vínculo)
        $userEmail = null;
        if ($userId) {
            $stmtUserEmail = $pdo->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
            $stmtUserEmail->execute([$userId]);
            $userEmail = $stmtUserEmail->fetchColumn();
        }

        if ($razao && $cpfCnpj) {
            try {
                $planToSave = $selectedPlanId ?: ($editData['subscription_plan_id'] ?? $editData['current_plan_id'] ?? null);
                if ($clientId) {
                    $stmt = $pdo->prepare("UPDATE clients_users SET name=?, email=?, user_id=?, razao_social=?, fantasia=?, cpf_cnpj=?, inscricao_estadual=?, logradouro=?, numero=?, bairro=?, cep=?, cidade=?, pais=?, current_plan_id=? WHERE id=?");
                    $stmt->execute([$razao, $userEmail ?? ($existing['email'] ?? null), $userId ?: null, $razao, $fantasia, $cpfCnpj, $ie, $logra, $numero, $bairro, $cep, $cidade, $pais, $planToSave, $clientId]);
                    $alert = ['type' => 'success', 'msg' => 'Cliente atualizado.'];
                } else {
                    $stmt = $pdo->prepare("INSERT INTO clients_users (name, email, role, user_id, razao_social, fantasia, cpf_cnpj, inscricao_estadual, logradouro, numero, bairro, cep, cidade, pais, current_plan_id) VALUES (?, ?, 'cliente', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$razao, $userEmail, $userId ?: null, $razao, $fantasia, $cpfCnpj, $ie, $logra, $numero, $bairro, $cep, $cidade, $pais, $planToSave]);
                    if (!$clientId) {
                        $clientId = (int)$pdo->lastInsertId();
                    }
                    $alert = ['type' => 'success', 'msg' => 'Cliente cadastrado.'];
                }
                if ($subscriptionId && $selectedPlanId) {
                    $stmtSubPlan = $pdo->prepare("UPDATE subscriptions SET plan_id = ? WHERE id = ?");
                    $stmtSubPlan->execute([$selectedPlanId, $subscriptionId]);
                }
                $planForSubscription = $planToSave ?: null;
                if (!$subscriptionId && $clientId && $planForSubscription) {
                    $expiresAtForInsert = null;
                    if ($expiresInput) {
                        $dtInsert = DateTime::createFromFormat('Y-m-d', $expiresInput);
                        if ($dtInsert) {
                            $expiresAtForInsert = $dtInsert->setTime(23, 59, 59)->format('Y-m-d H:i:s');
                        }
                    }
                    $stmtSubInsert = $pdo->prepare("
                        INSERT INTO subscriptions (
                            client_user_id, plan_id, mp_preference_id, status, total_amount, started_at, expires_at
                        ) VALUES (?, ?, ?, 'paid', 0, NOW(), ?)
                    ");
                    $preferenceId = 'admin-' . $clientId . '-' . uniqid();
                    $stmtSubInsert->execute([$clientId, $planForSubscription, $preferenceId, $expiresAtForInsert]);
                    $subscriptionId = (int)$pdo->lastInsertId();
                }
                if ($subscriptionId && $expiresInput) {
                    $dt = DateTime::createFromFormat('Y-m-d', $expiresInput);
                    if ($dt) {
                        $expiresAt = $dt->setTime(23, 59, 59)->format('Y-m-d H:i:s');
                        $stmtSub = $pdo->prepare("UPDATE subscriptions SET expires_at = ? WHERE id = ?");
                        $stmtSub->execute([$expiresAt, $subscriptionId]);
                    }
                }
                if ($clientId) {
                    $editClientId = $clientId;
                    $editData = fetchEditClientData($stmtEdit, $clientId);
                    if ($editData) {
                        $linkUserId = (int)($editData['user_id'] ?? 0);
                        if ($linkUserId) {
                            $stmtUser = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ? LIMIT 1");
                            $stmtUser->execute([$linkUserId]);
                            $linkedUser = $stmtUser->fetch();
                        } else {
                            $linkedUser = null;
                        }
                    } else {
                        $linkUserId = 0;
                        $linkedUser = null;
                    }
                }
            } catch (PDOException $e) {
                $alert = ['type' => 'danger', 'msg' => 'Erro ao salvar: ' . $e->getMessage()];
            }
        } else {
            $alert = ['type' => 'warning', 'msg' => 'Preencha razão social e CPF/CNPJ.'];
        }
    }
}

$planOptions = $pdo->query("SELECT id, name FROM plans ORDER BY name ASC")->fetchAll();

$clientes = $pdo->query("
    SELECT
        u.id AS user_id,
        u.name AS usuario,
        u.email,
        c.id AS client_id,
        c.razao_social,
        c.fantasia,
        c.cpf_cnpj,
        c.cidade,
        c.pais,
        c.current_plan_id,
        s.id AS subscription_id,
        s.plan_id AS subscription_plan_id,
        p.name AS plano,
        s.expires_at AS plano_expira_em
    FROM users u
    LEFT JOIN clients_users c ON c.user_id = u.id
    LEFT JOIN (
        SELECT s1.*
        FROM subscriptions s1
        INNER JOIN (
            SELECT client_user_id, MAX(expires_at) AS max_exp
            FROM subscriptions
            WHERE status = 'paid'
            GROUP BY client_user_id
        ) s2 ON s2.client_user_id = s1.client_user_id AND s2.max_exp = s1.expires_at
        WHERE s1.status = 'paid'
    ) s ON s.client_user_id = c.id
    LEFT JOIN plans p ON p.id = s.plan_id
    ORDER BY u.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Admin - Clientes</title>
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
                <a class="list-group-item list-group-item-action active" aria-current="true" href="admin_clientes.php">Clientes</a>
                <a class="list-group-item list-group-item-action" href="admin_planos.php">Planos</a>
                <a class="list-group-item list-group-item-action" href="index.php">Voltar ao gerador</a>
            </div>
        </aside>
        <main class="col-md-9 col-lg-10 py-4">
            <h4 class="mb-3">Clientes</h4>
            <?php if ($alert): ?>
                <div class="alert alert-<?= htmlspecialchars($alert['type']) ?>"><?= htmlspecialchars($alert['msg']) ?></div>
            <?php endif; ?>
            <div class="row g-4">
                <div class="col-xl-5 col-lg-6">
                    <div class="card p-3">
                        <h5 class="mb-0"><?= $editData ? 'Editar cliente' : 'Novo cliente' ?></h5>
                        <form class="mt-3" method="post">
                            <input type="hidden" name="client_id" value="<?= (int)($editData['id'] ?? 0) ?>">
                            <input type="hidden" name="user_id" value="<?= (int)$linkUserId ?>">
                            <input type="hidden" name="subscription_id" value="<?= (int)($editData['subscription_id'] ?? 0) ?>">
                            <?php if (!empty($linkUserId)): ?>
                                <div class="alert alert-info py-2">
                                    Vinculando ao usuário: <?= htmlspecialchars($linkedUser['name'] ?? '') ?> (<?= htmlspecialchars($linkedUser['email'] ?? '') ?>)
                                </div>
                            <?php endif; ?>
                            <div class="mb-3">
                                <label class="form-label">Razão social</label>
                                <input class="form-control" name="razao_social" placeholder="Ex: Empresa Exemplo LTDA" value="<?= htmlspecialchars($editData['razao_social'] ?? '') ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nome fantasia / Apelido</label>
                                <input class="form-control" name="fantasia" placeholder="Ex: Mercado Exemplo" value="<?= htmlspecialchars($editData['fantasia'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">CPF/CNPJ</label>
                                <input class="form-control" name="cpf_cnpj" placeholder="Somente números" value="<?= htmlspecialchars($editData['cpf_cnpj'] ?? '') ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Inscrição estadual</label>
                                <input class="form-control" name="inscricao_estadual" placeholder="Ex: 123.456.789.000" value="<?= htmlspecialchars($editData['inscricao_estadual'] ?? '') ?>">
                            </div>
                            <h5 class="mt-4 mb-2">Endereço</h5>
                            <div class="mb-3">
                                <label class="form-label">Logradouro do estabelecimento</label>
                                <input class="form-control" name="logradouro" placeholder="Rua, Avenida..." value="<?= htmlspecialchars($editData['logradouro'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Número do estabelecimento</label>
                                <input class="form-control" name="numero" placeholder="Ex: 123" value="<?= htmlspecialchars($editData['numero'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Bairro</label>
                                <input class="form-control" name="bairro" placeholder="Bairro" value="<?= htmlspecialchars($editData['bairro'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">CEP</label>
                                <input class="form-control" name="cep" placeholder="Ex: 00000-000" value="<?= htmlspecialchars($editData['cep'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Cidade</label>
                                <input class="form-control" name="cidade" placeholder="Cidade" value="<?= htmlspecialchars($editData['cidade'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">País</label>
                                <input class="form-control" name="pais" value="<?= htmlspecialchars($editData['pais'] ?? 'Brasil') ?>">
                            </div>
                            <?php $selectedPlanValue = (int)($editData['subscription_plan_id'] ?? $editData['current_plan_id'] ?? 0); ?>
                            <div class="mb-3">
                                <label class="form-label">Plano</label>
                                <?php if (!empty($planOptions)): ?>
                                    <select class="form-select" name="plan_id">
                                        <option value="">Manter plano atual</option>
                                        <?php foreach ($planOptions as $plan): ?>
                                            <?php $planId = (int)$plan['id']; ?>
                                            <option value="<?= $planId ?>" <?= $planId === $selectedPlanValue ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($plan['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <div class="form-control-plaintext text-muted">Nenhum plano cadastrado.</div>
                                <?php endif; ?>
                            </div>
                            <?php $expiresValue = !empty($editData['expires_at']) ? htmlspecialchars(date('Y-m-d', strtotime($editData['expires_at']))) : ''; ?>
                            <div class="mb-3">
                                <label class="form-label">Expira em</label>
                                <input class="form-control" type="date" name="expira_em" value="<?= $expiresValue ?>">
                                <small class="text-muted">Atualiza a data de expiração do plano ativo.</small>
                            </div>
                            <div class="d-grid gap-2">
                                <button class="btn btn-primary" type="submit"><?= $editData ? 'Atualizar' : 'Salvar' ?></button>
                                <?php if ($editData): ?>
                                    <a class="btn btn-outline-secondary" href="admin_clientes.php">Cancelar edição</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="col-xl-7 col-lg-6">
                    <div class="card p-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="mb-0">Clientes (por acesso)</h5>
                            <span class="text-muted small"><?= count($clientes) ?> registros</span>
                        </div>
                        <?php if (empty($clientes)): ?>
                            <div class="alert alert-light border">Nenhum cliente cadastrado.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped align-middle mb-0">
                                    <thead class="table-light">
                                    <tr>
                                        <th>Acesso</th>
                                        <th>Cliente</th>
                                        <th>Razão social</th>
        <th>Fantasia</th>
                                        <th>CPF/CNPJ</th>
                                        <th>Cidade</th>
                                        <th>Plano ativo</th>
                                        <th>Expira em</th>
                                        <th>Usuário</th>
                                        <th></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($clientes as $c): ?>
                                        <tr>
                                            <td><?= (int)($c['user_id'] ?? 0) ?></td>
                                            <td><?= $c['client_id'] ? (int)$c['client_id'] : '—' ?></td>
                                            <td><?= htmlspecialchars($c['razao_social'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($c['fantasia'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($c['cpf_cnpj'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($c['cidade'] ?? '') ?></td>
                                            <td>
                                                <div class="d-flex flex-column gap-2">
                                                    <div class="fw-bold"><?= htmlspecialchars($c['plano'] ?? '—') ?></div>
                                                    <?php if (!empty($c['subscription_id']) && !empty($planOptions)): ?>
                                                        <?php $currentPlanOption = (int)($c['subscription_plan_id'] ?? $c['current_plan_id'] ?? 0); ?>
                                                        <form method="post" class="d-flex align-items-center gap-2 flex-wrap">
                                                            <input type="hidden" name="subscription_id" value="<?= (int)$c['subscription_id'] ?>">
                                                            <input type="hidden" name="client_id" value="<?= (int)($c['client_id'] ?? 0) ?>">
                                                            <input type="hidden" name="update_plan" value="1">
                                                            <select name="plan_id" class="form-select form-select-sm" style="min-width: 180px;">
                                                                <?php foreach ($planOptions as $plan): ?>
                                                                    <option value="<?= (int)$plan['id'] ?>" <?= (int)$plan['id'] === $currentPlanOption ? 'selected' : '' ?>>
                                                                        <?= htmlspecialchars($plan['name']) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <button class="btn btn-sm btn-outline-primary" type="submit">Salvar</button>
                                                        </form>
                                                    <?php elseif (!empty($c['client_id'])): ?>
                                                        <small class="text-muted">Associe um plano para habilitar alterações.</small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php $expirationDateValue = $c['plano_expira_em'] ? date('Y-m-d', strtotime($c['plano_expira_em'])) : ''; ?>
                                                <form method="post" class="d-flex align-items-center gap-1 flex-wrap">
                                                    <input type="hidden" name="subscription_id" value="<?= (int)($c['subscription_id'] ?? 0) ?>">
                                                    <input type="hidden" name="update_expiration" value="1">
                                                    <input class="form-control form-control-sm" style="max-width: 150px;" type="date" name="expira_em" value="<?= $expirationDateValue ?>">
                                                    <button class="btn btn-sm btn-outline-primary" type="submit">Salvar</button>
                                                </form>
                                                <?php if (empty($c['subscription_id'])): ?>
                                                    <small class="text-muted">Sem assinatura ativa.</small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($c['usuario'] ?? '') ?></td>
                                            <td class="text-end">
                                                <?php if (!empty($c['client_id'])): ?>
                                                    <a class="btn btn-sm btn-outline-primary" href="admin_clientes.php?client_id=<?= (int)$c['client_id'] ?>">Editar</a>
                                                <?php else: ?>
                                                    <a class="btn btn-sm btn-outline-secondary" href="admin_clientes.php?user_id=<?= (int)$c['user_id'] ?>">Vincular</a>
                                                <?php endif; ?>
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

<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
if (($_SESSION['user']['role'] ?? '') !== 'cliente') {
    header('Location: index.php');
    exit;
}
require __DIR__ . '/db.php';
require __DIR__ . '/payments/helpers.php';

$userId = (int)$_SESSION['user']['id'];
$userName = $_SESSION['user']['name'] ?? '';
$userEmail = $_SESSION['user']['email'] ?? '';

$client = ensureClientForUser($pdo, $userId, $userName, $userEmail);
$clientId = (int)$client['id'];
$activeSubscription = getActiveSubscription($pdo, $clientId);

$freeTrialAvailable = empty($client['free_trial_used']);
$planNames = ['Plano Gratuito 10 dias', 'Plano Mensal', 'Plano Anual 12x'];
$placeholders = implode(',', array_fill(0, count($planNames), '?'));
$stmtPlans = $pdo->prepare("SELECT * FROM plans WHERE name IN ($placeholders)");
$stmtPlans->execute($planNames);
$plans = [];
while ($row = $stmtPlans->fetch()) {
    $plans[$row['name']] = $row;
}

$cards = [];
if ($freeTrialAvailable && isset($plans['Plano Gratuito 10 dias'])) {
    $cards[] = $plans['Plano Gratuito 10 dias'];
}
if (isset($plans['Plano Mensal'])) {
    $cards[] = $plans['Plano Mensal'];
}
if (isset($plans['Plano Anual 12x'])) {
    $cards[] = $plans['Plano Anual 12x'];
}

$planCatalog = [];
foreach ($cards as $plan) {
    if (!isset($plan['id'])) {
        continue;
    }
    $planCatalog[(int)$plan['id']] = [
        'id' => (int)$plan['id'],
        'name' => $plan['name'],
        'price' => (float)$plan['price'],
        'description' => $plan['description'] ?? '',
        'billing_cycle' => $plan['billing_cycle'],
        'plan_type' => resolvePlanType($plan),
        'duration_days' => (int)$plan['duration_days'],
    ];
}
$planCatalogJson = json_encode($planCatalog, JSON_UNESCAPED_UNICODE);

    $alert = null;
if (isset($_GET['msg'])) {
    $alert = ['type' => 'info', 'msg' => $_GET['msg']];
} elseif (isset($_GET['error'])) {
    $alert = ['type' => 'danger', 'msg' => $_GET['error']];
} elseif (isset($_GET['expired'])) {
    $alert = ['type' => 'warning', 'msg' => 'Seu período gratuito terminou. Escolha um plano para continuar usando os modelos.'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escolha de Plano</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
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
        body { background: #0f172a; color: #e5e7eb; font-family: 'Montserrat', sans-serif; }
        .hero { padding: 36px 0; }
        .plan-card { border: none; border-radius: 14px; box-shadow: 0 18px 32px rgba(0,0,0,0.18); background: #111827; color: #e5e7eb; }
        .badge-free { background: #22c55e; }
        .price { font-size: 32px; font-weight: 800; }
        .highlight { color: #fbbf24; font-weight: 800; }
        .plan-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 18px; }
        .secure-field { height: 48px; border-radius: 8px; background: #0e1321; border: 1px solid #1f2937; }
        .secure-field iframe { height: 100%; width: 100%; border: 0; }
        #boleto-form label { color: #e5e7eb; font-size: 0.7rem; font-weight: 600; }
        #boleto-form .form-control { background: #0f172a; color: #f8fafc; border-color: #334155; }
        #boleto-form .form-control:focus { background: #0f172a; color: #f8fafc; border-color: #38bdf8; box-shadow: 0 0 0 .15rem rgba(56, 189, 248, .25); }
    </style>
</head>
<body>
<div class="container hero">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="fw-bold mb-1">Escolha o plano para continuar</h2>
            <div class="text-muted">Selecione a opção que melhor atende seu negócio.</div>
        </div>
        <a class="btn btn-outline-light" href="index.php">Voltar ao painel</a>
    </div>
    <div class="alert alert-warning mb-3">
        <strong>Cartões em manutenção.</strong> Os pagamentos com cartão de débito e crédito estão temporariamente indisponíveis. Lamentamos o transtorno; enquanto isso, você pode usar o Pix para concretizar sua assinatura.
    </div>
    <?php if ($activeSubscription): ?>
        <?php $activePlanExpiry = $activeSubscription['expires_at'] ?? null; ?>
        <div class="alert alert-success text-dark">
            <div>
                <div class="small text-uppercase text-muted mb-1">Plano ativo</div>
                <div class="fw-bold mb-1"><?= htmlspecialchars($activeSubscription['plan_name']) ?></div>
                <?php if ($activePlanExpiry): ?>
                    <div class="text-dark small">Expira em <?= date('d/m/Y', strtotime($activePlanExpiry)) ?></div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($alert): ?>
        <div class="alert alert-<?= htmlspecialchars($alert['type']) ?>"><?= htmlspecialchars($alert['msg']) ?></div>
    <?php endif; ?>

    <div class="plan-grid">
    <?php foreach ($cards as $plan): ?>
        <?php
            $isFree = ((float)$plan['price']) === 0.0;
            $isAnnual = $plan['billing_cycle'] === 'annual';
            $displayTitle = $isAnnual ? 'Plano de 12 meses' : $plan['name'];
            $formattedPrice = number_format((float)$plan['price'], 2, ',', '.');
            $annualWarning = '(parcelamento pode gerar acréscimos)';
        ?>
        <div class="card plan-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h4 class="fw-bold mb-0"><?= htmlspecialchars($displayTitle) ?></h4>
                <?php if ($isFree): ?>
                    <span class="badge badge-free text-dark">Gratuito</span>
                <?php endif; ?>
            </div>
            <?php if ($isFree): ?>
                <div class="price mb-1">R$ 0,00</div>
                <div class="text-muted mb-2"><?= (int)$plan['duration_days'] ?> dias de acesso</div>
            <?php elseif ($isAnnual): ?>
                <div class="price mb-1">R$ 29,90/mês</div>
                <div class="text-muted mb-2">365 dias de acesso</div>
                <div class="text-muted mb-2">Pague através de PIX, Boleto, Cartão de Débito ou Crédito.</div>
            <?php else: ?>
                <div class="price mb-1">R$ <?= $formattedPrice ?></div>
                <div class="text-muted mb-2"><?= (int)$plan['duration_days'] ?> dias de acesso</div>
            <?php endif; ?>
            <?php if (!$isAnnual && $plan['description']): ?>
                <p class="mb-3"><?= htmlspecialchars($plan['description']) ?></p>
            <?php endif; ?>
            <?php if ($isAnnual): ?>
                <div class="highlight mb-2"><?= htmlspecialchars($annualWarning) ?></div>
            <?php endif; ?>
            <?php if ($isFree): ?>
                <button class="btn btn-primary w-100 js-free-plan" type="button" data-plan-id="<?= (int)$plan['id'] ?>">
                    Ativar gratuito
                </button>
            <?php else: ?>
                <button class="btn btn-primary w-100 js-plan-select" type="button" data-plan-id="<?= (int)$plan['id'] ?>">
                    <?= $isAnnual ? 'Assinar plano anual' : 'Assinar plano' ?>
                </button>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    </div>
    <div id="payment-panel" class="card mt-4 d-none">
        <div class="card-body">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <h5 id="payment-panel-title" class="mb-1"></h5>
                    <p id="payment-panel-subtitle" class="text-muted mb-0"></p>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="payment-panel-close">Cancelar</button>
            </div>
            <div id="payment-feedback" class="mt-3"></div>
            <div id="pix-payment-panel" class="mt-4">
                <div class="card plan-card p-3">
                    <div class="fw-bold mb-2">Pix</div>
                    <p class="text-muted small mb-3">Ao gerar o Pix, você receberá um QR Code válido por 12 horas.</p>
                    <button type="button" id="pix-submit-button" class="btn btn-outline-primary w-100">Gerar Pix</button>
                </div>
            </div>

            <div id="pix-details" class="mt-3"></div>
        </div>
    </div>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const planCatalog = <?= $planCatalogJson ?: '{}' ?>;
            const userEmail = <?= json_encode($userEmail) ?>;
            const paymentPanel = document.getElementById('payment-panel');
            const planTitle = document.getElementById('payment-panel-title');
            const planSubtitle = document.getElementById('payment-panel-subtitle');
            const feedbackArea = document.getElementById('payment-feedback');
            const pixDetails = document.getElementById('pix-details');
            const pixSubmitButton = document.getElementById('pix-submit-button');

            let activePlan = null;

            document.querySelectorAll('.js-plan-select').forEach(button => {
                button.addEventListener('click', () => openPaymentPanel(button.dataset.planId));
            });

            document.querySelectorAll('.js-free-plan').forEach(button => {
                button.addEventListener('click', () => activateFreePlan(button.dataset.planId));
            });

            pixSubmitButton.addEventListener('click', submitPixPayment);

            function openPaymentPanel(planId) {
                const plan = planCatalog[planId];
                if (!plan) {
                    showFeedback('danger', 'Plano inválido.');
                    return;
                }
                activePlan = plan;
                planTitle.textContent = plan.name;
                planSubtitle.textContent = `R$ ${plan.price.toFixed(2).replace('.', ',')} · ${plan.duration_days} dias`;
                feedbackArea.innerHTML = '';
                pixDetails.innerHTML = '';
                paymentPanel.classList.remove('d-none');
            }

            async function submitPixPayment() {
                if (!activePlan) {
                    return;
                }
                showFeedback('info', 'Gerando Pix...');
                pixSubmitButton.disabled = true;
                pixDetails.innerHTML = '';

                try {
                    const response = await fetch('create_pix_payment.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            plan_id: activePlan.id,
                            payment_type: 'pix',
                            transaction_amount: activePlan.price,
                            payer_email: userEmail,
                        }),
                    });

                    const body = await parseJsonResponse(response);
                    console.log('create_pix_payment response', response.status, body);

                    if (!response.ok || !body.success) {
                        showFeedback('danger', body.error || 'Não foi possível gerar o Pix.');
                        return;
                    }
                    showFeedback('success', 'Pix gerado! Finalize o pagamento no app ou portal do Mercado Pago.');
                    renderPixInstructions(body.details ?? {});
                } catch (error) {
                    console.error('Erro ao chamar create_pix_payment.php', error);
                    showFeedback('danger', error.message || 'Erro ao processar o Pix.');
                } finally {
                    pixSubmitButton.disabled = false;
                }
            }

            function renderPixInstructions(details) {
                pixDetails.innerHTML = '';
                if (!details || Object.keys(details).length === 0) {
                    return;
                }
                const card = document.createElement('div');
                card.className = 'card plan-card p-3';
                const heading = document.createElement('div');
                heading.className = 'fw-bold mb-2';
                heading.textContent = 'Pix registrado';
                card.appendChild(heading);

                if (details.qr_code_base64) {
                    const img = document.createElement('img');
                    img.src = `data:image/png;base64,${details.qr_code_base64}`;
                    img.alt = 'QR Code Pix';
                    img.style.maxWidth = '260px';
                    img.style.display = 'block';
                    img.style.marginBottom = '8px';
                    card.appendChild(img);
                }

                if (details.qr_code) {
                    const label = document.createElement('p');
                    label.className = 'mb-1';
                    label.textContent = 'Copie o código Pix:';
                    card.appendChild(label);
                    const pre = document.createElement('pre');
                    pre.className = 'small bg-dark text-white p-2 rounded';
                    pre.textContent = details.qr_code;
                    card.appendChild(pre);
                }

                const pixUrl = details.external_resource_url || details.ticket_url;
                if (pixUrl) {
                    const controls = document.createElement('div');
                    controls.className = 'd-flex flex-wrap gap-2 mt-2';

                    const link = document.createElement('a');
                    link.href = pixUrl;
                    link.target = '_blank';
                    link.rel = 'noopener';
                    link.className = 'btn btn-sm btn-link p-0';
                    link.textContent = 'Abrir pagamento';

                    const supportButton = document.createElement('a');
                    supportButton.href = 'https://wa.me/5569992507789?text=Preciso%20de%20suporte%20para%20confirmar%20meu%20pagamento%20Pix.';
                    supportButton.target = '_blank';
                    supportButton.rel = 'noopener';
                    supportButton.className = 'btn btn-sm btn-success';
                    supportButton.textContent = 'Suporte WhatsApp';

                    controls.appendChild(link);
                    controls.appendChild(supportButton);
                    card.appendChild(controls);
                }

                if (details.transaction_id) {
                    const tx = document.createElement('p');
                    tx.className = 'mt-2 mb-0 small text-muted';
                    tx.textContent = `ID da transação: ${details.transaction_id}`;
                    card.appendChild(tx);
                }

                pixDetails.appendChild(card);
            }

            async function parseJsonResponse(response) {
                if (response.ok) {
                    const contentType = response.headers.get('content-type') || '';
                    if (!contentType.includes('application/json')) {
                        return { success: true };
                    }
                }
                try {
                    return await response.json();
                } catch (error) {
                    if (response.ok) {
                        return { success: true };
                    }
                    return { success: false, error: 'Resposta inesperada do servidor.' };
                }
            }

            function showFeedback(type, message) {
                feedbackArea.innerHTML = '';
                if (!message) {
                    return;
                }
                const alert = document.createElement('div');
                alert.className = `alert alert-${type}`;
                alert.textContent = message;
                feedbackArea.appendChild(alert);
            }

            function activateFreePlan(planId) {
                const plan = planCatalog[planId];
                if (!plan) {
                    showFeedback('danger', 'Plano gratuito inválido.');
                    return;
                }
                showFeedback('info', 'Ativando plano gratuito...');
                fetch('create_payment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ plan_id: planId }),
                })
                    .then(response => response.json())
                    .then(body => {
                        if (!body.success) {
                            showFeedback('danger', body.error || 'Não foi possível ativar o trial gratuito.');
                            return;
                        }
                        window.location.href = 'index.php?msg=Plano+gratuito+ativado';
                    })
                    .catch(error => {
                        showFeedback('danger', error.message || 'Erro ao ativar plano gratuito.');
                    });
            }

        });
    </script>

</div>
</body>
</html>

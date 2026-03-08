<?php
declare(strict_types=1);

use MercadoPago\Exceptions\MPApiException;
use Payments\CardPaymentService;
use Payments\PixPaymentService;

session_start();

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo 'Acesso negado';
    exit;
}
if (($_SESSION['user']['role'] ?? '') !== 'cliente') {
    http_response_code(403);
    echo 'Somente clientes podem contratar planos';
    exit;
}

require __DIR__ . '/db.php';
require __DIR__ . '/payments/helpers.php';
require __DIR__ . '/payments/AbstractPaymentService.php';
require __DIR__ . '/payments/CardPaymentService.php';
require __DIR__ . '/payments/PixPaymentService.php';
require __DIR__ . '/payments/payment_flow_helpers.php';

try {
    $userId = (int)$_SESSION['user']['id'];
    $userName = $_SESSION['user']['name'] ?? '';
    $userEmail = $_SESSION['user']['email'] ?? '';

    $client = ensureClientForUser($pdo, $userId, $userName, $userEmail);
    $clientId = (int)$client['id'];
    $freeTrialUsed = !empty($client['free_trial_used']);

    $rawPayload = file_get_contents('php://input');
    $data = json_decode($rawPayload, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $planId = (int)($data['plan_id'] ?? 0);
    if (!$planId) {
        throw new InvalidArgumentException('Plano inválido');
    }

    $plan = getPlan($pdo, $planId);
    if (!$plan) {
        throw new RuntimeException('Plano não encontrado');
    }

    $planType = resolvePlanType($plan);
    if ($plan['billing_cycle'] === 'demo' && $freeTrialUsed) {
        throw new RuntimeException('Seu período gratuito já foi utilizado');
    }

    $planPrice = (float)$plan['price'];
    if ($planPrice <= 0) {
        $now = new DateTimeImmutable('now');
        $trialEnds = $now->modify('+' . (int)$plan['duration_days'] . ' days');
        $trialToken = 'TRIAL-' . uniqid('', true);

        $ins = $pdo->prepare(<<<'SQL'
            INSERT INTO subscriptions (client_user_id, plan_id, mp_preference_id, preapproval_id, status, plan_type, total_amount, started_at, expires_at, trial_ends_at)
            VALUES (?, ?, ?, NULL, 'trial', 'trial', 0, ?, ?, ?)
        SQL
        );
        $ins->execute([
            $clientId,
            $planId,
            $trialToken,
            $now->format('Y-m-d H:i:s'),
            $trialEnds->format('Y-m-d H:i:s'),
            $trialEnds->format('Y-m-d H:i:s'),
        ]);

        updateCurrentPlan($pdo, $clientId, $planId);
        markFreeTrialUsed($pdo, $clientId);

        jsonResponse(200, [
            'success' => true,
            'trial' => true,
            'message' => 'Plano gratuito ativado',
        ]);
    }

    $mpAccessToken = getenv('MP_ACCESS_TOKEN');
    if (!$mpAccessToken) {
        throw new RuntimeException('Token de acesso Mercado Pago não configurado');
    }

    $paymentType = strtolower(trim((string)($data['payment_type'] ?? '')));
    if (!in_array($paymentType, ['card', 'pix'], true)) {
        throw new InvalidArgumentException('Tipo de pagamento inválido');
    }

    $payer = buildPayerData($client, $data);

    $pending = insertPendingSubscription($pdo, $clientId, $planId, $planType, $planPrice);
    $subscriptionId = $pending['subscription_id'];
    $externalReference = $pending['external_reference'];

    $metadata = [
        'client_user_id' => $clientId,
        'plan_type' => $planType,
    ];

    if ($paymentType === 'card') {
        $token = trim($data['token'] ?? '');
        $paymentMethodId = trim($data['payment_method_id'] ?? '');
        $issuerId = trim($data['issuer_id'] ?? '');
        $installments = max(1, (int)($data['installments'] ?? 1));
        $deviceSessionId = trim($data['device_session_id'] ?? '');

        if ($token === '' || $paymentMethodId === '') {
            throw new InvalidArgumentException('Token do cartão ou método de pagamento ausente');
        }

        $service = new CardPaymentService($mpAccessToken);
        $payment = $service->create($subscriptionId, $plan, $payer, [
            'token' => $token,
            'payment_method_id' => $paymentMethodId,
            'issuer_id' => $issuerId,
            'installments' => $installments,
            'device_session_id' => $deviceSessionId,
            'external_reference' => $externalReference,
            'metadata' => $metadata,
        ]);
    } else {
        $service = new PixPaymentService($mpAccessToken);
        $payment = $service->create($subscriptionId, $plan, $payer, [
            'description' => $plan['description'] ?? $plan['name'] ?? 'Plano CartazRápido',
            'external_reference' => $externalReference,
            'metadata' => $metadata,
        ]);
    }

    logMercadoPagoEvent('payment-request', [
        'subscription_id' => $subscriptionId,
        'plan_id' => $planId,
        'plan_type' => $planType,
        'payment_type' => $paymentType,
    ]);

    $paymentStatus = strtolower((string)($payment->status ?? ''));
    $mappedStatus = resolvePaymentStatus($paymentStatus);
    $now = new DateTimeImmutable('now');
    $durationMonths = $plan['billing_cycle'] === 'annual' ? 12 : 1;
    $expiresAt = $now->modify("+{$durationMonths} months")->format('Y-m-d H:i:s');
    $startedAt = $now->format('Y-m-d H:i:s');
    $nextPaymentAt = $mappedStatus === 'paid' ? $expiresAt : null;

    $paymentMethod = $paymentType === 'card' ? 'credit_card' : 'pix';

    $updateSubscription = $pdo->prepare(<<<'SQL'
        UPDATE subscriptions
        SET mp_preference_id = ?, mp_payment_id = ?, payment_method = ?, total_amount = ?, status = ?, plan_type = ?, started_at = ?, expires_at = ?, next_payment_at = ?, updated_at = NOW()
        WHERE id = ?
    SQL
    );
    $updateSubscription->execute([
        $externalReference,
        $payment->id,
        $paymentMethod,
        $payment->transaction_amount,
        $mappedStatus,
        $planType,
        $startedAt,
        $expiresAt,
        $nextPaymentAt,
        $subscriptionId,
    ]);

    if ($mappedStatus === 'paid') {
        updateCurrentPlan($pdo, $clientId, $planId);
    }

    $transactionData = extractTransactionData($payment);
    $details = [];
    if ($transactionData !== null) {
        $details['qr_code'] = $transactionData['qr_code'] ?? null;
        $details['qr_code_base64'] = $transactionData['qr_code_base64'] ?? null;
        $details['ticket_url'] = $transactionData['ticket_url'] ?? null;
        $details['transaction_id'] = $transactionData['transaction_id'] ?? null;
    }

    $transactionDetailsPayload = null;
    if (isset($payment->transaction_details)) {
        $transactionDetailsPayload = json_decode(json_encode($payment->transaction_details, JSON_UNESCAPED_UNICODE), true) ?: null;
    }
    if ($transactionDetailsPayload !== null) {
        $details['external_resource_url'] = $transactionDetailsPayload['external_resource_url'] ?? null;
    }

    logMercadoPagoEvent('payment-response', [
        'subscription_id' => $subscriptionId,
        'payment_id' => $payment->id,
        'payment_method' => $paymentMethod,
        'status' => $paymentStatus,
        'next_payment_at' => $nextPaymentAt,
        'payment_type' => $paymentType,
    ]);

    $responsePayload = [
        'subscription_id' => $subscriptionId,
        'payment_id' => $payment->id,
        'payment_method' => $paymentMethod,
        'status' => $paymentStatus,
        'next_payment_at' => $nextPaymentAt,
        'payment_flow' => $paymentType,
        'details' => $details,
    ];

    $responseStatusCode = 200;
    if ($paymentStatus !== 'approved') {
        $responseStatusCode = 400;
        $responsePayload['success'] = false;
        $responsePayload['error'] = sprintf('Pagamento não aprovado (status: %s).', $paymentStatus ?: 'desconhecido');
    } else {
        $responsePayload['success'] = true;
    }

    jsonResponse($responseStatusCode, $responsePayload);
} catch (\Throwable $e) {
    $apiResponse = null;
    for ($current = $e; $current !== null; $current = $current->getPrevious()) {
        if ($current instanceof MPApiException) {
            $response = $current->getApiResponse();
            $apiResponse = [
                'status_code' => $response->getStatusCode(),
                'content' => $response->getContent(),
            ];
            break;
        }
    }

    $logPayload = [
        'message' => $e->getMessage(),
        'stack' => $e->getTraceAsString(),
    ];

    if ($apiResponse !== null) {
        $logPayload['api_response'] = $apiResponse;
    }

    logMercadoPagoEvent('payment-error', $logPayload);
    jsonResponse(500, ['success' => false, 'error' => $e->getMessage()]);
}

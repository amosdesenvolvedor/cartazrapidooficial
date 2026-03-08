<?php
declare(strict_types=1);

use DateTimeImmutable;
use MercadoPago\Exceptions\MPApiException;
use Payments\PixPaymentService;

session_start();

if (!isset($_SESSION['user'])) {
    jsonResponse(401, ['success' => false, 'error' => 'Acesso negado']);
}
if (($_SESSION['user']['role'] ?? '') !== 'cliente') {
    jsonResponse(403, ['success' => false, 'error' => 'Somente clientes podem contratar planos']);
}

require __DIR__ . '/db.php';
require __DIR__ . '/payments/helpers.php';
require __DIR__ . '/payments/payment_flow_helpers.php';
require __DIR__ . '/payments/AbstractPaymentService.php';
require __DIR__ . '/payments/PixPaymentService.php';

try {
    $userId = (int)$_SESSION['user']['id'];
    $userName = $_SESSION['user']['name'] ?? '';
    $userEmail = $_SESSION['user']['email'] ?? '';

    $client = ensureClientForUser($pdo, $userId, $userName, $userEmail);
    $clientId = (int)$client['id'];

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
    $planPrice = (float)$plan['price'];
    if ($planPrice <= 0) {
        throw new RuntimeException('Plano sem cobrança não pode gerar Pix');
    }

    $mpAccessToken = getenv('MP_ACCESS_TOKEN');
    if (!$mpAccessToken) {
        throw new RuntimeException('Token de acesso Mercado Pago não configurado');
    }

    $payerPayload = [
        'payer_email' => $data['payer_email'] ?? $userEmail,
    ];
    $payer = buildPayerData($client, $payerPayload);
    $pending = insertPendingSubscription($pdo, $clientId, $planId, $planType, $planPrice);
    $subscriptionId = $pending['subscription_id'];
    $externalReference = $pending['external_reference'];

    $metadata = [
        'client_user_id' => $clientId,
        'plan_type' => $planType,
    ];

    $description = trim($data['description'] ?? $plan['description'] ?? $plan['name'] ?? 'Plano CartazRápido');

    $service = new PixPaymentService($mpAccessToken);
    $payment = $service->create($subscriptionId, $plan, $payer, [
        'description' => $description,
        'external_reference' => $externalReference,
        'metadata' => $metadata,
    ]);
    logMercadoPagoEvent('payment-debug', [
        'subscription_id' => $subscriptionId,
        'payment' => json_decode(json_encode($payment, JSON_UNESCAPED_UNICODE), true),
    ]);

    logMercadoPagoEvent('payment-request', [
        'subscription_id' => $subscriptionId,
        'plan_id' => $planId,
        'plan_type' => $planType,
        'payment_type' => 'pix',
    ]);

    $paymentStatus = strtolower((string)($payment->status ?? ''));
    $mappedStatus = resolvePaymentStatus($paymentStatus);
    $now = new DateTimeImmutable('now');
    $durationMonths = $plan['billing_cycle'] === 'annual' ? 12 : 1;
    $expiresAt = $now->modify("+{$durationMonths} months")->format('Y-m-d H:i:s');
    $startedAt = $now->format('Y-m-d H:i:s');
    $nextPaymentAt = $mappedStatus === 'paid' ? $expiresAt : null;

    $updateSubscription = $pdo->prepare(<<<'SQL'
        UPDATE subscriptions
        SET mp_preference_id = ?, mp_payment_id = ?, payment_method = ?, total_amount = ?, status = ?, plan_type = ?, started_at = ?, expires_at = ?, next_payment_at = ?, updated_at = NOW()
        WHERE id = ?
    SQL
    );
    $updateSubscription->execute([
        $externalReference,
        $payment->id,
        'pix',
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
        'payment_method' => 'pix',
        'status' => $paymentStatus,
        'next_payment_at' => $nextPaymentAt,
        'payment_type' => 'pix',
    ]);

    $isPixFlow = true;
    $success = $isPixFlow || $mappedStatus === 'paid';
    $responsePayload = [
        'subscription_id' => $subscriptionId,
        'payment_id' => $payment->id,
        'payment_method' => 'pix',
        'status' => $paymentStatus,
        'next_payment_at' => $nextPaymentAt,
        'payment_flow' => 'pix',
        'details' => $details,
        'success' => $success,
    ];

    $responseStatusCode = $success ? 200 : 400;
    if (!$success) {
        $responsePayload['error'] = sprintf('Pagamento não aprovado (status: %s).', $paymentStatus ?: 'desconhecido');
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

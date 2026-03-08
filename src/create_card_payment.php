<?php
declare(strict_types=1);

use DateTimeImmutable;
use MercadoPago\Exceptions\MPApiException;
use Payments\CardPaymentService;

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
require __DIR__ . '/payments/CardPaymentService.php';

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
        throw new RuntimeException('Plano sem cobrança não pode ser pago por cartão');
    }

    $mpAccessToken = getenv('MP_ACCESS_TOKEN');
    if (!$mpAccessToken) {
        throw new RuntimeException('Token de acesso Mercado Pago não configurado');
    }

    $payer = buildPayerData($client, $data);
    $pending = insertPendingSubscription($pdo, $clientId, $planId, $planType, $planPrice);
    $subscriptionId = $pending['subscription_id'];
    $externalReference = $pending['external_reference'];

    $metadata = [
        'client_user_id' => $clientId,
        'plan_type' => $planType,
    ];

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

    logMercadoPagoEvent('payment-request', [
        'subscription_id' => $subscriptionId,
        'plan_id' => $planId,
        'plan_type' => $planType,
        'payment_type' => 'card',
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
        'credit_card',
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
        'payment_method' => 'credit_card',
        'status' => $paymentStatus,
        'next_payment_at' => $nextPaymentAt,
        'payment_type' => 'card',
    ]);

    $responsePayload = [
        'subscription_id' => $subscriptionId,
        'payment_id' => $payment->id,
        'payment_method' => 'credit_card',
        'status' => $paymentStatus,
        'next_payment_at' => $nextPaymentAt,
        'payment_flow' => 'card',
        'details' => $details,
        'success' => $mappedStatus === 'paid',
    ];

    $responseStatusCode = $mappedStatus === 'paid' ? 200 : 400;
    if (!$responsePayload['success']) {
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

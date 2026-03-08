<?php
declare(strict_types=1);

use Payments\PaymentService;

require __DIR__ . '/../db.php';
require __DIR__ . '/../payments/helpers.php';
require __DIR__ . '/../payments/PaymentService.php';

$mpAccessToken = getenv('MP_ACCESS_TOKEN');
if (!$mpAccessToken) {
    http_response_code(500);
    echo 'MP_ACCESS_TOKEN não configurado';
    exit;
}

$rawPayload = file_get_contents('php://input');
if ($rawPayload === false) {
    http_response_code(400);
    echo 'payload ausente';
    exit;
}

$payload = json_decode($rawPayload, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo 'payload inválido';
    exit;
}

$webhookSecret = getenv('MP_WEBHOOK_SECRET');
$signatureHeader = $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
if ($webhookSecret && $signatureHeader !== '') {
    $expected = 'sha256=' . hash_hmac('sha256', $rawPayload, $webhookSecret);
    if (!hash_equals($expected, $signatureHeader)) {
        http_response_code(401);
        echo 'assinatura inválida';
        exit;
    }
}

$topic = strtolower($payload['type'] ?? $payload['topic'] ?? '');
$action = strtolower($payload['action'] ?? '');
$notificationIdParts = array_filter([
    $payload['id'] ?? '',
    $payload['data']['id'] ?? '',
    $topic,
    $action,
]);
$notificationId = substr(implode('_', $notificationIdParts), 0, 250);

if ($notificationId === '') {
    http_response_code(400);
    echo 'notification_id ausente';
    exit;
}

$exists = $pdo->prepare("SELECT 1 FROM payment_notifications WHERE notification_id = ? LIMIT 1");
$exists->execute([$notificationId]);
if ($exists->fetch()) {
    http_response_code(200);
    echo 'notificação duplicada';
    exit;
}

$paymentId = (string)($payload['data']['id'] ?? $payload['id'] ?? '');
if ($paymentId === '') {
    http_response_code(400);
    echo 'payment id ausente';
    exit;
}

$payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
$insertNotification = $pdo->prepare("
    INSERT INTO payment_notifications (notification_id, payment_id, topic, payload)
    VALUES (?, ?, ?, ?)
");
$insertNotification->execute([$notificationId, $paymentId, $topic ?: 'payment', $payloadJson]);

$paymentService = new PaymentService($mpAccessToken);
$paymentData = $paymentService->fetchPayment($paymentId);
if (!$paymentData) {
    updateNotificationStatus(null, null, 'skipped', $pdo, $notificationId);
    http_response_code(404);
    echo 'pagamento não encontrado';
    exit;
}

$externalReference = (string)($paymentData['external_reference'] ?? '');
$subscriptionId = null;
if (preg_match('/subscription_(\d+)/', $externalReference, $matches)) {
    $subscriptionId = (int)$matches[1];
}

if (!$subscriptionId) {
    updateNotificationStatus(null, $externalReference, 'skipped', $pdo, $notificationId);
    http_response_code(200);
    echo 'referência da assinatura ausente';
    exit;
}

$stmt = $pdo->prepare("SELECT s.*, p.billing_cycle FROM subscriptions s LEFT JOIN plans p ON p.id = s.plan_id WHERE s.id = ? LIMIT 1");
$stmt->execute([$subscriptionId]);
$subscription = $stmt->fetch();
if (!$subscription) {
    updateNotificationStatus(null, $externalReference, 'skipped', $pdo, $notificationId);
    http_response_code(404);
    echo 'assinatura não encontrada';
    exit;
}

$statusMap = [
    'approved' => 'paid',
    'authorized' => 'authorized',
    'in_process' => 'pending',
    'rejected' => 'pending',
    'cancelled' => 'cancelled',
    'refunded' => 'cancelled',
];
$status = strtolower($paymentData['status'] ?? 'pending');
$mappedStatus = $statusMap[$status] ?? 'pending';

$durationMonths = ($subscription['billing_cycle'] ?? '') === 'annual' ? 12 : 1;
$now = new DateTimeImmutable('now');
$expiresAt = $now->modify("+{$durationMonths} months")->format('Y-m-d H:i:s');
$nextPaymentAt = $mappedStatus === 'paid' ? $expiresAt : null;

$updateSubscription = $pdo->prepare("
    UPDATE subscriptions
    SET status = ?, mp_payment_id = ?, payment_method = ?, total_amount = ?, started_at = COALESCE(started_at, NOW()), expires_at = ?, next_payment_at = ?, updated_at = NOW()
    WHERE id = ?
");
$updateSubscription->execute([
    $mappedStatus,
    $paymentId,
    $paymentData['payment_method_id'] ?? null,
    $paymentData['transaction_amount'] ?? 0,
    $expiresAt,
    $nextPaymentAt,
    $subscriptionId,
]);

updateCurrentPlan($pdo, (int)$subscription['client_user_id'], (int)$subscription['plan_id']);

logWebhook('webhook.payment', [
    'subscription_id' => $subscriptionId,
    'payment_id' => $paymentId,
    'status' => $status,
    'mapped_status' => $mappedStatus,
    'next_payment_at' => $nextPaymentAt,
]);

updateNotificationStatus($subscriptionId, $externalReference, 'processed', $pdo, $notificationId);

http_response_code(200);
echo 'ok';

function updateNotificationStatus(?int $subscriptionId, ?string $preferenceId, string $status, PDO $pdo, string $notificationId): void
{
    $stmt = $pdo->prepare("
        UPDATE payment_notifications
        SET subscription_id = ?, preference_id = ?, status = ?, updated_at = NOW()
        WHERE notification_id = ?
    ");
    $stmt->execute([$subscriptionId, $preferenceId, $status, $notificationId]);
}

function logWebhook(string $label, array $context): void
{
    $dir = sys_get_temp_dir() . '/cartaz_mercadopago';
    if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
        return;
    }
    if (!is_writable($dir)) {
        return;
    }
    $line = sprintf(
        "[%s] %s %s%s",
        (new DateTime())->format(DateTime::ATOM),
        $label,
        json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        PHP_EOL
    );
    file_put_contents($dir . '/mercadopago.log', $line, FILE_APPEND | LOCK_EX);
}

<?php
declare(strict_types=1);

use DateTimeImmutable;

function logMercadoPagoEvent(string $label, array $payload): void
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
        (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
        $label,
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        PHP_EOL
    );
    file_put_contents($dir . '/mercadopago.log', $line, FILE_APPEND | LOCK_EX);
}

function jsonResponse(int $statusCode, array $payload): void
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function buildPayerData(array $client, array $input): array
{
    $email = trim($input['payer_email'] ?? $client['email'] ?? '');
    $name = trim($input['payer_name'] ?? $client['name'] ?? '');
    $identification = preg_replace('/\D+/', '', $input['payer_identification_number'] ?? $client['cpf_cnpj'] ?? '');

    $addressFromClient = [
        'street_name' => $client['logradouro'] ?? '',
        'street_number' => $client['numero'] ?? '',
        'neighborhood' => $client['bairro'] ?? '',
        'city' => $client['cidade'] ?? '',
        'federal_unit' => $client['pais'] ?? '',
        'zip_code' => preg_replace('/\D+/', '', $client['cep'] ?? ''),
    ];

    $inputAddress = is_array($input['payer_address'] ?? null)
        ? array_merge($addressFromClient, $input['payer_address'])
        : $addressFromClient;

    $payer = ['email' => $email];
    if ($name !== '') {
        $parts = preg_split('/\s+/', $name);
        $firstName = array_shift($parts) ?: $name;
        $lastName = implode(' ', $parts) ?: $firstName;
        $payer['first_name'] = $firstName;
        $payer['last_name'] = $lastName;
    }
    if ($identification !== '') {
        $payer['identification'] = [
            'type' => strlen($identification) > 11 ? 'CNPJ' : 'CPF',
            'number' => $identification,
        ];
    }

    if (is_array($inputAddress)) {
        $fields = [
            'zip_code',
            'street_name',
            'street_number',
            'neighborhood',
            'city',
            'federal_unit',
        ];
        $address = [];
        foreach ($fields as $field) {
            $value = trim((string)($inputAddress[$field] ?? ''));
            if ($value === '') {
                continue;
            }
            $address[$field] = $value;
        }
        if ($address !== []) {
            $payer['address'] = $address;
        }
    }

    return $payer;
}

function resolvePaymentStatus(string $status): string
{
    switch ($status) {
        case 'approved':
            return 'paid';
        case 'authorized':
            return 'authorized';
        default:
            return 'pending';
    }
}

function extractTransactionData($payment): ?array
{
    $transactionData = null;

    if (isset($payment->point_of_interaction)) {
        $transactionData = getTransactionDataFromPointOfInteraction($payment->point_of_interaction);
    }

    if ($transactionData === null && isset($payment->transaction_data)) {
        $transactionData = $payment->transaction_data;
    }

    return normalizeTransactionData($transactionData);
}

function getTransactionDataFromPointOfInteraction($pointOfInteraction)
{
    if (is_array($pointOfInteraction) && array_key_exists('transaction_data', $pointOfInteraction)) {
        return $pointOfInteraction['transaction_data'];
    }
    if (is_object($pointOfInteraction) && property_exists($pointOfInteraction, 'transaction_data')) {
        return $pointOfInteraction->transaction_data;
    }
    return null;
}

function normalizeTransactionData($transactionData): ?array
{
    if ($transactionData === null) {
        return null;
    }
    if (is_array($transactionData) || is_object($transactionData)) {
        return json_decode(json_encode($transactionData, JSON_UNESCAPED_UNICODE), true) ?: null;
    }
    return null;
}

function insertPendingSubscription(PDO $pdo, int $clientId, int $planId, string $planType, float $planPrice): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO subscriptions (client_user_id, plan_id, mp_preference_id, status, plan_type, total_amount)
        VALUES (?, ?, ?, 'pending', ?, ?)
    SQL
    );
    $pendingReference = 'pending_' . uniqid('', true);
    $statement->execute([$clientId, $planId, $pendingReference, $planType, $planPrice]);
    $subscriptionId = (int)$pdo->lastInsertId();
    $externalReference = 'subscription_' . $subscriptionId;

    return [
        'pending_reference' => $pendingReference,
        'subscription_id' => $subscriptionId,
        'external_reference' => $externalReference,
    ];
}

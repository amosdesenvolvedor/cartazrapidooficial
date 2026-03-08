<?php
declare(strict_types=1);

namespace Payments;

use MercadoPago\Resources\Payment;

class CardPaymentService extends AbstractPaymentService
{
    public function __construct(string $accessToken)
    {
        parent::__construct($accessToken, 'card-payment.log');
    }

    public function create(int $subscriptionId, array $plan, array $payer, array $details): Payment
    {
        $installments = max(1, (int)($details['installments'] ?? 1));
        $issuerId = trim((string)($details['issuer_id'] ?? ''));

        $payload = [
            'transaction_amount' => (float)$plan['price'],
            'token' => (string)($details['token'] ?? ''),
            'installments' => $installments,
            'payment_method_id' => (string)($details['payment_method_id'] ?? ''),
            'issuer_id' => $issuerId !== '' ? $issuerId : null,
            'payer' => $payer,
            'binary_mode' => true,
            'statement_descriptor' => 'CARTAZRAPIDO',
            'external_reference' => (string)($details['external_reference'] ?? ''),
            'additional_info' => [
                'items' => [$this->buildItem($plan)],
            ],
            'metadata' => $this->buildMetadata($subscriptionId, $plan, $details['metadata'] ?? []),
        ];

        $deviceSessionId = trim((string)($details['device_session_id'] ?? ''));
        if ($deviceSessionId !== '') {
            $payload['device_session_id'] = $deviceSessionId;
            $payload['additional_info']['device_session_id'] = $deviceSessionId;
        }

        return $this->createPayment($payload);
    }

    protected function getLogPrefix(): string
    {
        return 'card-payment';
    }
}

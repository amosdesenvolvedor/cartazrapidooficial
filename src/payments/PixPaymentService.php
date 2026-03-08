<?php
declare(strict_types=1);

namespace Payments;

use MercadoPago\Resources\Payment;

class PixPaymentService extends AbstractPaymentService
{
    public function __construct(string $accessToken)
    {
        parent::__construct($accessToken, 'pix-payment.log');
    }

    public function create(int $subscriptionId, array $plan, array $payer, array $details): Payment
    {
        $description = trim((string)($details['description'] ?? $plan['description'] ?? $plan['name'] ?? 'Plano CartazRápido'));

        $payload = [
            'transaction_amount' => (float)$plan['price'],
            'payment_method_id' => 'pix',
            'payer' => $payer,
            'description' => $description,
            'statement_descriptor' => 'CARTAZRAPIDO',
            'external_reference' => (string)($details['external_reference'] ?? ''),
            'additional_info' => [
                'items' => [$this->buildItem($plan)],
            ],
            'metadata' => $this->buildMetadata($subscriptionId, $plan, $details['metadata'] ?? []),
        ];

        return $this->createPayment($payload);
    }

    protected function getLogPrefix(): string
    {
        return 'pix-payment';
    }
}

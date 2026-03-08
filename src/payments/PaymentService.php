<?php
declare(strict_types=1);

namespace Payments;

use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;

require_once __DIR__ . '/../vendor/autoload.php';

class PaymentService
{
    private PaymentClient $paymentClient;

    public function __construct(string $accessToken)
    {
        MercadoPagoConfig::setAccessToken($accessToken);
        $this->paymentClient = new PaymentClient();
    }

    public function fetchPayment(string $paymentId): ?array
    {
        try {
            $payment = $this->paymentClient->get((int)$paymentId);
        } catch (MPApiException $error) {
            return null;
        }

        return [
            'id' => $payment->id,
            'status' => $payment->status,
            'payment_method_id' => $payment->payment_method_id,
            'payment_type_id' => $payment->payment_type_id,
            'transaction_amount' => $payment->transaction_amount,
            'payment_method' => $payment->payment_method_id,
            'transaction_details' => $payment->transaction_details,
            'payer' => $payment->payer,
            'order_id' => is_object($payment->order) ? ($payment->order->id ?? null) : null,
            'preference_id' => $payment->preference_id,
            'bin' => is_object($payment->card) ? ($payment->card->first_six_digits ?? null) : null,
            'metadata' => (array)($payment->metadata ?? []),
            'point_of_interaction' => $payment->point_of_interaction ?? null,
            'transaction_data' => isset($payment->point_of_interaction) && is_object($payment->point_of_interaction)
                ? ($payment->point_of_interaction->transaction_data ?? null)
                : null,
        ];
    }
}

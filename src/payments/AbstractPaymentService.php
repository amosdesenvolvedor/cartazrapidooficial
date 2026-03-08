<?php
declare(strict_types=1);

namespace Payments;

use DateTimeImmutable;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Resources\Payment;

require_once __DIR__ . '/../vendor/autoload.php';

abstract class AbstractPaymentService
{
    protected PaymentClient $paymentClient;
    private string $logFile;

    public function __construct(string $accessToken, string $logFile)
    {
        MercadoPagoConfig::setAccessToken($accessToken);
        $this->logFile = $logFile;
        $this->paymentClient = new PaymentClient();
    }

    abstract protected function getLogPrefix(): string;

    final protected function createPayment(array $payload): Payment
    {
        $label = $this->getLogPrefix();
        $this->logEvent("{$label}.request", ['payload' => $payload]);

        try {
            $payment = $this->paymentClient->create($payload);
        } catch (MPApiException $error) {
            $context = [
                'payload' => $payload,
                'error_message' => $error->getMessage(),
                'status_code' => $error->getStatusCode(),
            ];
            $this->logEvent("{$label}.error", $context);
            throw new \RuntimeException('Erro ao criar pagamento Mercado Pago.', 0, $error);
        }

        $this->logEvent("{$label}.response", [
            'payload' => $payload,
            'response' => $this->serializePayment($payment),
        ]);

        return $payment;
    }

    protected function logEvent(string $label, array $payload): void
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
        file_put_contents($dir . '/' . $this->logFile, $line, FILE_APPEND | LOCK_EX);
    }

    protected function buildItem(array $plan): array
    {
        return [
            'id' => 'plan_' . ((int)($plan['id'] ?? 0)),
            'title' => $plan['name'] ?? 'Plano CartazRápido',
            'description' => $plan['description'] ?? 'Assinatura CartazRápido',
            'category_id' => 'services',
            'quantity' => 1,
            'unit_price' => (float)$plan['price'],
        ];
    }

    protected function buildMetadata(int $subscriptionId, array $plan, array $custom): array
    {
        return array_merge([
            'subscription_id' => $subscriptionId,
            'plan_id' => $plan['id'] ?? null,
        ], $custom);
    }

    protected function serializePayment(Payment $payment): array
    {
        $encoded = json_decode(json_encode($payment, JSON_UNESCAPED_UNICODE), true);
        return is_array($encoded) ? $encoded : [];
    }
}

<?php
declare(strict_types=1);

namespace Payments;

use MercadoPago\Client\PreApproval\PreApprovalClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Resources\PreApproval;

require_once __DIR__ . '/../vendor/autoload.php';

class PreapprovalService
{
    private PreApprovalClient $client;

    public function __construct(string $accessToken)
    {
        MercadoPagoConfig::setAccessToken($accessToken);
        $this->client = new PreApprovalClient();
    }

    /**
     * Cria Preapproval no Mercado Pago.
     *
     * @param array $payload Dados do preapproval.
     * @return PreApproval
     */
    public function createPreapproval(array $payload): PreApproval
    {
        try {
            return $this->client->create($payload);
        } catch (MPApiException $exc) {
            throw new \RuntimeException('Falha ao criar preapproval no Mercado Pago.', 0, $exc);
        }
    }

    public function getPreapproval(string $id): ?PreApproval
    {
        try {
            return $this->client->get($id);
        } catch (MPApiException $exc) {
            return null;
        }
    }
}

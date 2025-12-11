<?php

namespace App\Services\PaymentGateway;

use App\Models\Order;
use App\Interface\PaymentGateway\PaymentGatewayInterface;

class CashOnDeliveryGateway implements PaymentGatewayInterface
{
    public function createCheckout(array $data)
    {
        return (object)[
            'url' => null,
            'id'  => null
        ];
    }

    public function handleWebhook(string $payload, ?string $sigHeader = null)
    {
        return response('COD does not use webhook', 200);
    }
}

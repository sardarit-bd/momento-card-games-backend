<?php

namespace App\Services\PaymentGateway;

use App\Services\PaymentGateway\StripeGateway;
use App\Services\PaymentGateway\CashOnDeliveryGateway;
use App\Interface\PaymentGateway\PaymentGatewayInterface;

class PaymentGatewayFactory
{
    public static function make(string $gateway): PaymentGatewayInterface
    {
        return match ($gateway) {
            'stripe'      => new StripeGateway(),
            'cod', 'cash_on_delivery' => new CashOnDeliveryGateway(),
            default       => throw new \Exception("Payment gateway not supported."),
        };
    }
}

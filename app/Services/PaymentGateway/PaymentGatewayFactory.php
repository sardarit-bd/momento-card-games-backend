<?php

namespace App\Services\PaymentGateway;

use App\Services\PaymentGateway\StripeGateway;
use App\Interface\PaymentGateway\PaymentGatewayInterface;

class PaymentGatewayFactory
{
    public static function make(string $gateway): PaymentGatewayInterface
    {
        return match ($gateway) {
            'stripe'      => new StripeGateway(),
            default       => throw new \Exception("Payment gateway not supported."),
        };
    }
}

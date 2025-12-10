<?php

namespace App\Http\Controllers\PaymentGateway;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\PaymentGateway\PaymentGatewayFactory;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sig = $request->header('Stripe-Signature');

        $gateway = PaymentGatewayFactory::make('stripe');

        return $gateway->handleWebhook($payload, $sig);
    }

}

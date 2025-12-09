<?php

namespace App\Services\PaymentGateway;

use Stripe\StripeClient;
use App\Interface\PaymentGateway\PaymentGatewayInterface;

class StripeGateway implements PaymentGatewayInterface
{
    protected $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(env('STRIPE_SECRET'));
    }

    public function createCheckout(array $data)
    {
        $lineItems = [];

        foreach ($data['items'] as $item) {
            $lineItems[] = [
                'price_data' => [
                    'currency'    => 'usd',
                    'unit_amount' => $item['price'],
                    'product_data' => ['name' => $item['name']]
                ],
                'quantity' => $item['qty']
            ];
        }

        return $this->stripe->checkout->sessions->create([
            'payment_method_types' => ['card'],
            'mode' => 'payment',
            'line_items' => $lineItems,
            'success_url' => $data['success_url'],
            'cancel_url'  => $data['cancel_url'],
        ]);
    }

    public function handleWebhook($payload)
    {
        // Handle Stripe webhook
    }
}

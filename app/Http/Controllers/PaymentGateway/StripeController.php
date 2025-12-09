<?php

namespace App\Http\Controllers\PaymentGateway;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\PaymentGateway\PaymentGatewayFactory;

class StripeController extends Controller
{
    public function createCheckoutSession(Request $request)
    {
        $gatewayName = $request->gateway ?? 'stripe';

        $gateway = PaymentGatewayFactory::make($gatewayName);

        $session = $gateway->createCheckout([
            'items' => $request->items,
            'success_url' => url('/payment/success'),
            'cancel_url'  => url('/payment/cancel'),
        ]);

        return response()->json([
            'checkout_url' => $session->url ?? null,
            'session_id'   => $session->id ?? null,
        ]);
    }
}

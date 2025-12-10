<?php

namespace App\Http\Controllers\PaymentGateway;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\PaymentGateway\PaymentGatewayFactory;
use App\Services\PaymentGateway\StripeGatewayService;


class StripeController extends Controller
{
    protected StripeGatewayService $stripeGatewayService;

    public function __construct(StripeGatewayService $stripeGatewayService)
    {
        $this->stripeGatewayService = $stripeGatewayService;
    }


    public function createCheckoutSession(Request $request)
    {
        return $this->stripeGatewayService->createCheckoutSession($request);
    }
    

    public function success(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Payment successful!',
            'session_id' => $request->session_id,
            'order_id' => $request->order_id,
        ]);
    }

    public function cancel()
    {
        return response()->json([
            'status' => 'canceled',
            'message' => 'Payment canceled by user.'
        ]);
    }
}

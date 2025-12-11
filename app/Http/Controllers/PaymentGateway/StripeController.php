<?php

namespace App\Http\Controllers\PaymentGateway;

use App\Models\Order;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
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
        ]);
    }


    public function cancel(Request $request)
    {
        $orderId = $request->query('order_id');

        if ($orderId) {
            $order = Order::find($orderId);

            if ($order) {
                $payment = $order->orderHasPaids()
                    ->where('status', 'pending')
                    ->orderByDesc('created_at')
                    ->first();

                if ($payment) {
                    $payment->update([
                        'status' => 'pending',
                        'notes'  => 'Payment canceled by user during Stripe checkout.',
                    ]);
                }
            }
        }

        return response()->json([
            'status'  => 'canceled',
            'message' => 'Payment has been canceled. Your order is still saved and can be retried within 24 hours.',
        ]);
    }
}

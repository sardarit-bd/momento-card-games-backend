<?php

namespace App\Services\PaymentGateway;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StripeGatewayService
{
    public function createCheckoutSession(Request $request)
    {
        $request->validate([
            'name'    => 'required|string',
            'email'   => 'required|email',
            'phone'   => 'required|string',
            'address' => 'required|string',
            'city'    => 'nullable|string',
            'zipcode' => 'nullable|string',
            'items'   => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.qty'        => 'required|integer|min:1',
            'items.*.price'      => 'required|numeric|min:0',
            'items.*.name'       => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            // Create order
            $order = Order::create([
                'name'           => $request->name,
                'email'          => $request->email,
                'phone'          => $request->phone,
                'address'        => $request->address,
                'city'           => $request->city,
                'zipcode'        => $request->zipcode,
                'total'          => $this->calcTotal($request->items),
                'status'         => 'pending',
                'is_paid'        => false,
            ]);

            // Create order items
            foreach ($request->items as $item) {
                $order->orderItems()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'quantity'   => $item['qty'],
                ]);
            }

            $gateway = PaymentGatewayFactory::make($request->gateway ?? 'stripe');

            $stripeItems = array_map(function ($it) {
                return [
                    'name' => $it['name'],
                    'qty'  => $it['qty'],
                    'price'=> round($it['price'] * 100),
                ];
            }, $request->items);

            $session = $gateway->createCheckout([
                'items'       => $stripeItems,
                'order_id'    => $order->id,
                'success_url' => env('APP_URL') . '/payment/success',
                'cancel_url'  => env('APP_URL') . '/payment/cancel',
                'currency'    => 'usd',
            ]);

            DB::commit();

            return response()->json([
                'checkout_url' => $session->url ?? null,
                'session_id'   => $session->id ?? null,
                'order_id'     => $order->id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }


    private function calcTotal(array $items)
    {
        $sum = 0;
        foreach ($items as $it) {
            $sum += floatval($it['price']) * intval($it['qty']);
        }
        return $sum;
    }
}
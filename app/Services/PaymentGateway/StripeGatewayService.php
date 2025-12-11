<?php

namespace App\Services\PaymentGateway;

use App\Models\Order;
use App\Models\Product;
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
            'gateway' => 'required|string|in:stripe,cod,cash_on_delivery',
            'items'   => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.qty'        => 'required|integer|min:1',
            'items.*.price'      => 'nullable|numeric|min:0',
            'items.*.name'       => 'required|string',
        ]);

        DB::beginTransaction();

        try {
            $validatedItems = [];
            $trustedTotal = 0;

            foreach ($request->items as $item) {
                $product = Product::where('status', 'active')
                    ->findOrFail($item['product_id']);

                $sellingPrice = $product->offer_price > 0 
                    ? $product->offer_price 
                    : $product->price;

                $quantity = (int) $item['qty'];

                $lineTotal = $sellingPrice * $quantity;
                $trustedTotal += $lineTotal;

                $validatedItems[] = [
                    'product_id' => $product->id,
                    'name'       => $product->name,
                    'qty'        => $quantity,
                    'price'      => $sellingPrice,
                    'total'      => $lineTotal,
                ];
            }

            $order = Order::create([
                'name'           => $request->name,
                'email'          => $request->email,
                'phone'          => $request->phone,
                'address'        => $request->address,
                'city'           => $request->city,
                'zipcode'        => $request->zipcode,
                'total'          => $trustedTotal,
                'status'         => 'pending',
                'is_paid'        => false,
            ]);

            foreach ($validatedItems as $item) {
                $order->orderItems()->create([
                    'product_id' => $item['product_id'],
                    'quantity'   => $item['qty'],
                ]);
            }

            $order->orderHasPaids()->create([
                'amount' => $order->total,
                'method' => $request->gateway,
                'status' => 'pending',
                'transaction_id' => null,
                'notes' => $request->gateway === 'stripe' 
                    ? 'Stripe checkout session created. Awaiting payment.'
                    : 'Cash on Delivery - Awaiting delivery',
            ]);

            // Handle gateway
            $gateway = PaymentGatewayFactory::make($request->gateway);

            // Cash on Delivery
            if ($request->gateway === 'cod' || $request->gateway === 'cash_on_delivery') {
                $order->orderHasPaids()->create([
                    'amount'         => $order->total,
                    'method'         => 'cod',
                    'status'         => 'pending',
                    'transaction_id' => null,
                    'notes'          => 'Cash on Delivery',
                ]);

                DB::commit();

                return response()->json([
                    'status'   => 'success',
                    'gateway'  => 'cod',
                    'message'  => 'Order placed successfully using Cash on Delivery.',
                    'order_id' => $order->id,
                ]);
            }

            $stripeItems = array_map(function ($it) {
                return [
                    'name' => $it['name'],
                    'qty'  => $it['qty'],
                    'price'=> round($it['price'] * 100),
                ];
            }, $validatedItems);

            $session = $gateway->createCheckout([
                'items'       => $stripeItems,
                'order_id'    => $order->id,
                'metadata' => ['order_id' => $order->id],
                    'expires_at' => now()->addHour(1)->timestamp,
                    'after_expiration' => [
                        'recovery' => ['enabled' => true],
                    ],
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
}
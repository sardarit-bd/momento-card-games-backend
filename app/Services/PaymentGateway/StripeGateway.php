<?php

namespace App\Services\PaymentGateway;

use App\Models\Order;
use Stripe\StripeClient;
use Stripe\Webhook;
use App\Interface\PaymentGateway\PaymentGatewayInterface;
use Illuminate\Support\Facades\Log;

class StripeGateway implements PaymentGatewayInterface
{
    protected $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(env('STRIPE_SECRET'));
    }

    /**
     * This method creates a Stripe checkout session
     * @param array $data
     */
    public function createCheckout(array $data)
    {
        $lineItems = [];

        foreach ($data['items'] as $item) {
            $lineItems[] = [
                'price_data' => [
                    'currency'    => $data['currency'] ?? 'usd',
                    'unit_amount' => intval($item['price']),
                    'product_data' => ['name' => $item['name']]
                ],
                'quantity' => intval($item['qty'])
            ];
        }

        $session = $this->stripe->checkout->sessions->create([
            'payment_method_types' => ['card'],
            'mode' => 'payment',
            'line_items' => $lineItems,
            'success_url' => $data['success_url'] . '?session_id={CHECKOUT_SESSION_ID}&order_id=' . $data['order_id'],
            'cancel_url'  => $data['cancel_url'],
            'metadata' => [
                'order_id' => $data['order_id'],
            ],
        ]);

        return $session;
    }

    /**
     * This method handle webhook payload from Stripe.
     */
    // public function handleWebhook(string $payload, ?string $sigHeader = null)
    // {
    //     $webhookSecret = env('STRIPE_WEBHOOK_SECRET');

    //     try {
    //         if ($webhookSecret && $sigHeader) {
    //             $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
    //         } else {
    //             $event = json_decode($payload);
    //         }
    //     } catch (\UnexpectedValueException $e) {
    //         Log::error('Stripe webhook invalid payload: ' . $e->getMessage());
    //         return response('Invalid payload', 400);
    //     } catch (\Stripe\Exception\SignatureVerificationException $e) {
    //         Log::error('Stripe webhook signature verification failed: ' . $e->getMessage());
    //         return response('Invalid signature', 400);
    //     }

    //     // Handle the checkout.session.completed event
    //     if ($event->type === 'checkout.session.completed') {
    //         $session = $event->data->object;

    //         $orderId = $session->metadata->order_id ?? null;
    //         if (!$orderId) {
    //             Log::error('Stripe webhook: order_id missing in metadata');
    //             return response('order_id missing', 400);
    //         }

    //         $order = Order::with(['orderItems.product', 'orderHasPaids'])->find($orderId);

    //         if (!$order) {
    //             Log::error("Stripe webhook: Order not found: {$orderId}");
    //             return response('Order not found', 404);
    //         }

    //         // idempotency: check if already paid
    //         if ($order->is_paid) {
    //             // Already processed — respond 200 so Stripe will not retry
    //             return response('Already processed', 200);
    //         }

    //         // mark paid and create payment record
    //         $order->update([
    //             'is_paid' => true,
    //             'status'  => 'completed',
    //         ]);

    //         // record payment
    //         $order->orderHasPaids()->create([
    //             'amount'         => isset($session->amount_total) ? ($session->amount_total / 100) : $order->total,
    //             'method'         => 'stripe',
    //             'status'         => 'completed',
    //             'transaction_id' => $session->payment_intent ?? $session->id ?? null,
    //             'notes'          => '',
    //         ]);

    //         // optional: dispatch event, notifications, emails, broadcasting etc.
    //         // event(new \App\Events\OrderPaid($order));
    //     }

    //     // Always return a 200 to Stripe when handled correctly
    //     return response('Webhook handled', 200);
    // }
    public function handleWebhook(string $payload, ?string $sigHeader = null)
    {
        $webhookSecret = env('STRIPE_WEBHOOK_SECRET');

        try {
            $event = $webhookSecret && $sigHeader
                ? Webhook::constructEvent($payload, $sigHeader, $webhookSecret)
                : json_decode($payload);

            $eventType = $event->type;

            // Successful checkout
            if ($eventType === 'checkout.session.completed') {
                $session = $event->data->object;
                $orderId = $session->metadata->order_id ?? null;
                $order = Order::with('orderHasPaids')->find($orderId);

                if ($order && !$order->is_paid) {
                    $order->update(['is_paid' => true, 'status' => 'completed']);
                    $order->orderHasPaids()->create([
                        'amount'         => $session->amount_total / 100,
                        'method'         => 'stripe',
                        'status'         => 'completed',
                        'transaction_id' => $session->payment_intent,
                        'notes'          => '',
                    ]);
                }
            }

            // Failed payment
            if ($eventType === 'payment_intent.payment_failed') {
                $paymentIntent = $event->data->object;
                $orderId = $paymentIntent->metadata->order_id ?? null;
                $order = \App\Models\Order::find($orderId);

                if ($order) {
                    $order->orderHasPaids()->create([
                        'amount'         => $paymentIntent->amount / 100,
                        'method'         => 'stripe',
                        'status'         => 'failed',
                        'transaction_id' => $paymentIntent->id,
                        'notes'          => $paymentIntent->last_payment_error->message ?? '',
                    ]);
                    $order->update(['status' => 'failed']);
                }
            }

            return response('Webhook handled', 200);

        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return response('Invalid signature', 400);
        } catch (\Exception $e) {
            Log::error('Stripe webhook error: ' . $e->getMessage());
            return response('Webhook error', 500);
        }
    }


    /**
     * Helper to build the JSON the frontend expects.
     * Note: not used by webhook (webhook returns only 200). This is public to be called by OrderController.
     */
    // public function buildOrderResponse(Order $order)
    // {
    //     $images = [];

    //     foreach ($order->orderItems as $item) {
    //         // adjust this to your actual product image column or path
    //         $imagePath = $item->product->image ?? null;
    //         dd($item->product->image[0]);

    //         if ($imagePath) {
    //             $fullPath = public_path($imagePath);
    //             if (!file_exists($fullPath)) {
    //                 $fullPath = storage_path('app/public/' . ltrim($imagePath, '/'));
    //             }

    //             if (file_exists($fullPath)) {
    //                 $mime = mime_content_type($fullPath) ?: 'image/png';
    //                 $b64  = base64_encode(file_get_contents($fullPath));
    //                 $images[] = "data:{$mime};base64,{$b64}";
    //             }
    //         }
    //     }

    //     return [
    //         'AllProductImage' => $images,
    //         'City'            => $order->city ?? '',
    //         'address'         => $order->address,
    //         'email'           => $order->email,
    //         'name'            => $order->name,
    //         'payment_method'  => 'stripe',
    //         'phone'           => $order->phone,
    //         'roundTotolPrice' => $order->total,
    //         'zipcode'         => $order->zipcode ?? '',
    //     ];
    // }

    public function buildOrderResponse(Order $order)
    {
        $images = [];

        foreach ($order->orderItems as $item) {
            $imagePath = $item->product->image ?? null;

            if (!$imagePath) {
                continue; // skip if no image path
            }

            // Try public folder first
            $fullPath = public_path($imagePath);

            // If not exists, try storage/app/public
            if (!file_exists($fullPath)) {
                $fullPath = storage_path('app/public/' . ltrim($imagePath, '/'));
            }

            // Only process if file actually exists
            if (file_exists($fullPath)) {
                $mime = mime_content_type($fullPath) ?: 'image/png';
                $b64  = base64_encode(file_get_contents($fullPath));
                $images[] = "data:{$mime};base64,{$b64}";
            } else {
                // Log missing files to debug later
                Log::warning("Product image not found for OrderItem ID {$item->id}: {$imagePath}");
            }
        }

        return [
            'AllProductImage' => $images,
            'City'            => $order->city ?? '',
            'address'         => $order->address,
            'email'           => $order->email,
            'name'            => $order->name,
            'payment_method'  => 'stripe',
            'phone'           => $order->phone,
            'roundTotolPrice' => $order->total,
            'zipcode'         => $order->zipcode ?? '',
        ];
    }


}

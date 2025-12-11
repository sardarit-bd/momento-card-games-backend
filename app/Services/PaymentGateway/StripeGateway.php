<?php

namespace App\Services\PaymentGateway;

use Stripe\Webhook;
use App\Models\Order;
use Stripe\StripeClient;
use App\Mail\AbandonedCartRecovery;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Interface\PaymentGateway\PaymentGatewayInterface;

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
            'success_url' => $data['success_url'],
            'cancel_url'  => $data['cancel_url'],
            'metadata' => [
                'order_id' => $data['order_id'],
            ],
            'expires_at' => now()->addHour(1)->timestamp,
            'after_expiration' => [
                'recovery' => ['enabled' => true],
            ],
        ]);

        return $session;
    }

    /**
     * This method handle webhook payload from Stripe.
     */
    public function handleWebhook(string $payload, ?string $sigHeader = null)
    {
        $webhookSecret = env('STRIPE_WEBHOOK_SECRET');

        try {
            $event = $webhookSecret && $sigHeader
                ? Webhook::constructEvent($payload, $sigHeader, $webhookSecret)
                : json_decode($payload);

            $eventType = $event->type;

            // Successful payment
            if ($eventType === 'checkout.session.completed') {
                $session = $event->data->object;

                if ($session->payment_status === 'paid') {
                    $orderId = $session->metadata->order_id ?? null;
                    $order = Order::with('orderHasPaids')->find($orderId);

                    if ($order && !$order->is_paid) {
                        // Update existing pending payment record
                        $payment = $order->orderHasPaids()
                            ->where('method', 'stripe')
                            ->where('status', 'pending')
                            ->latest()
                            ->first();

                        if ($payment) {
                            $payment->update([
                                'status'         => 'completed',
                                'transaction_id' => $session->payment_intent ?? $session->id,
                                'notes'          => 'Payment completed successfully via Stripe.',
                            ]);
                        }

                        // Update order
                        $order->update([
                            'is_paid' => true,
                            'status'  => 'completed',
                        ]);
                    }
                }
            }

            // Session expired (abandoned or canceled)
            if ($eventType === 'checkout.session.expired') {
                $session = $event->data->object;
                $orderId = $session->metadata->order_id ?? null;
                $order = Order::find($orderId);

                if ($order) {
                    $payment = $order->orderHasPaids()
                        ->where('method', 'stripe')
                        ->where('status', 'pending')
                        ->latest()
                        ->first();

                    if ($payment) {
                        $notes = 'Checkout session expired.';
                        $sendRecoveryEmail = false;
                        $recoveryUrl = $session->after_expiration->recovery->url ?? null;

                        if ($recoveryUrl) {
                            $notes .= ' User abandoned cart — recovery link generated.';
                            $sendRecoveryEmail = true;
                        } else {
                            $notes .= ' User likely canceled immediately.';
                        }

                        $payment->update([
                            'status'         => 'failed',
                            'transaction_id' => $session->id,
                            'notes'          => $notes,
                        ]);
                        
                        if ($sendRecoveryEmail && $recoveryUrl) {
                            Mail::to($order->email)->queue(
                                new AbandonedCartRecovery($order, $recoveryUrl)
                            );
                        }
                    }
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
     * @param Order $order
     */
    public function buildOrderResponse(Order $order)
    {
        $images = [];

        foreach ($order->orderItems as $item) {
            $imagePath = $item->product->image ?? null;

            if (!$imagePath) {
                continue; 
            }

            $fullPath = public_path($imagePath);

            if (!file_exists($fullPath)) {
                $fullPath = storage_path('app/public/' . ltrim($imagePath, '/'));
            }

            if (file_exists($fullPath)) {
                $mime = mime_content_type($fullPath) ?: 'image/png';
                $b64  = base64_encode(file_get_contents($fullPath));
                $images[] = "data:{$mime};base64,{$b64}";
            } else {
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

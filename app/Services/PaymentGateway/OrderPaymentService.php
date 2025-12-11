<?php

namespace App\Services\PaymentGateway;

use App\Models\Order;

class OrderPaymentService
{
    public function syncOrderPaymentStatus(Order $order): void
    {
        // Refresh the relationship to get latest data
        $order->loadMissing('orderHasPaids');

        $payments = $order->orderHasPaids;

        // Calculate total successfully paid amount
        $totalPaid = $payments
            ->where('status', 'completed')
            ->sum('amount');

        $orderTotal = (float) $order->total;

        // 1. Update is_paid: true if ANY payment is completed
        $hasCompletedPayment = $payments->contains('status', 'completed');

        // 2. Update order status
        if ($hasCompletedPayment && $totalPaid >= $orderTotal) {
            $newStatus = 'completed';
        } elseif ($payments->pluck('status')->contains('failed') && ! $hasCompletedPayment) {
            // Optional: if all attempts failed and no success
            $newStatus = 'canceled'; // or keep 'pending' — your choice
        } else {
            $newStatus = 'pending';
        }

        // Only update if something changed (prevents unnecessary saves & events)
        if ($order->is_paid !== $hasCompletedPayment || $order->status !== $newStatus) {
            $order->update([
                'is_paid' => $hasCompletedPayment,
                'status' => $newStatus,
            ]);
            // Note: We use update() instead of save() to bypass model events if needed
            // Alternatively: $order->saveQuietly(); // Laravel 9+
        }
    }
}
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

        $totalPaid = $payments
            ->where('status', 'completed')
            ->sum('amount');

        $orderTotal = (float) $order->total;

        $hasCompletedPayment = $payments->contains('status', 'completed');

        if ($hasCompletedPayment && $totalPaid >= $orderTotal) {
            $newStatus = 'completed';
        } elseif ($payments->pluck('status')->contains('failed') && ! $hasCompletedPayment) {
            $newStatus = 'canceled';
        } else {
            $newStatus = 'pending';
        }

        // Only update if something changed (prevents unnecessary saves & events)
        if ($order->is_paid !== $hasCompletedPayment || $order->status !== $newStatus) {
            $order->update([
                'is_paid' => $hasCompletedPayment,
                'status' => $newStatus,
            ]);
        }
    }
}
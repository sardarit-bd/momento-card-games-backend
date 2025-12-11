<?php

namespace App\Observers;

use App\Models\OrderHasPaid;
use App\Services\PaymentGateway\OrderPaymentService;

class OrderHasPaidObserver
{
    protected OrderPaymentService $paymentService;

    public function __construct(OrderPaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function updated(OrderHasPaid $payment): void
    {
        // Sync only if status or amount changed (important for corrections)
        if ($payment->wasChanged(['status', 'amount'])) {
            $this->paymentService->syncOrderPaymentStatus($payment->order);
        }
    }

    public function created(OrderHasPaid $payment): void
    {
        // If a new payment is created as 'completed' (e.g., manual entry)
        if ($payment->status === 'completed') {
            $this->paymentService->syncOrderPaymentStatus($payment->order);
        }
    }

    // Optional: Handle deletions (e.g., if admin deletes a completed payment)
    public function deleted(OrderHasPaid $payment): void
    {
        $this->paymentService->syncOrderPaymentStatus($payment->order);
    }
}
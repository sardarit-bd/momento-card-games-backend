<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use App\Models\OrderHasPaid;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class AdminOrderPaymentController extends Controller
{
    /**
     * Diplay payments for a specific order
    */
    // AdminOrderController.php or similar
    public function payments($id)
    {
        $order = Order::findOrFail($id);
        $payments = $order->orderHasPaids()->latest()->get();

        if (!$payments) {
            return response()->json([
                'success' => false,
                'message' => 'No payments found for this order'
            ], 404);
        }

        return response()->json([
            'data' => $payments
        ]);
    }

    /**
     * Update payment status and sync with order status
    */
    // public function updateStatus(Request $request, $id)
    // {
    //     $request->validate([
    //         'status' => 'required|in:pending,completed,failed,canceled',
    //     ]);

    //     $payment = OrderHasPaid::findOrFail($id);
    //     $payment->status = $request->status;
    //     $payment->save();

    //     $order = $payment->order;
    //     if ($order) {
    //         $order->status = $payment->status;
    //         $order->save();
    //     }

    //     return response()->json([
    //         'message' => 'Payment status updated successfully',
    //         'payment' => $payment,
    //         'order'   => $order,
    //     ]);
    // }

    public function updateStatus(OrderHasPaid $orderHasPaid, Request $request)
    {
        $request->validate([
            'status' => 'required|in:pending,completed,failed',
            'transaction_id' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $orderHasPaid->update($request->only(['status', 'transaction_id', 'notes']));

        return response()->json([
            'message' => 'Payment status updated successfully',
            'data' => $orderHasPaid->fresh(),
        ]);
    }

}

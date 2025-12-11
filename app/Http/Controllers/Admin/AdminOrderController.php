<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\OrderResource;

class AdminOrderController extends Controller
{
    /**
     * Display a listing of the orders.
     */
    public function index(Request $request): JsonResponse
    {
        // Authorization using Gate/Policy (better approach)
        try {
            // Query optimization with pagination
            $orders = Order::with(['orderItems.product', 'user:id,name,email'])
                ->when($request->status, function ($query) use ($request) {
                    $query->where('status', $request->status);
                })
                ->latest('id')
                ->paginate(10);

            return $this->successResponse(
                'Orders retrieved successfully',
                OrderResource::collection($orders)
            );

        } catch (\Exception $e) {
        return $this->errorResponse('Failed to retrieve orders: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified order.
     */
    public function show(Order $order): JsonResponse
    {
        try {
            $order->load(['orderItems.product', 'user']);

            return $this->successResponse(
                'Order retrieved successfully',
                new OrderResource($order)
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Order not found', 404);
        }
    }
    /**
     * Update order status.
     */
    public function update(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'name'          => 'sometimes|nullable|string|max:255',
            'email'         => 'sometimes|nullable|email',
            'phone'         => 'sometimes|nullable|string|max:20',
            'address'       => 'sometimes|nullable|string|max:500',
            'status'        => 'sometimes|nullable|in:pending,completed',
            'is_paid'       => 'sometimes|boolean',
            'is_customized' => 'sometimes|boolean',

            // Nested validations
            'items'                  => 'sometimes|array',
            'items.*.id'             => 'sometimes|exists:order_items,id',
            'items.*.product_id'     => 'required_with:items.*|exists:products,id',
            'items.*.quantity'       => 'required_with:items.*|integer|min:1',

            'payments'               => 'sometimes|array',
            'payments.*.id'          => 'sometimes|exists:order_has_paids,id',
            'payments.*.amount'      => 'sometimes|numeric|min:0',
            'payments.*.method'      => 'sometimes|string',
            'payments.*.status'      => 'sometimes|in:pending,completed,failed',
            'payments.*.transaction_id' => 'nullable|string',
            'payments.*.notes'       => 'nullable|string',
        ]);


        try {
            // ✅ Update the main order
            $order->update($request->only([
                'name','email','phone','address','status','is_paid','is_customized'
            ]) + ['updated_by' => Auth::user()->id]);

            // ✅ Update order items if provided
            if ($request->has('items')) {
                foreach ($request->items as $item) {
                    if (isset($item['id'])) {
                        // Update existing item
                        $orderItem = $order->orderItems()->find($item['id']);
                        if ($orderItem) {
                            $orderItem->update($item);
                        }
                    } else {
                        // Create new item
                        $order->orderItems()->create($item);
                    }
                }
            }

            // ✅ Update payments if provided
            if ($request->has('payments')) {
                foreach ($request->payments as $payment) {
                    if (isset($payment['id'])) {
                        $orderPayment = $order->orderHasPaids()->find($payment['id']);
                        if ($orderPayment) {
                            $orderPayment->update($payment);
                        }
                    } else {
                        $order->orderHasPaids()->create($payment);
                    }
                }
            }

            $order->load(['orderItems.product', 'orderHasPaids', 'user']);

            return $this->successResponse(
                'Order updated successfully',
                new OrderResource($order)
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update order: '.$e->getMessage(), 500);
        }
    }


    //cancel order
    public function orderCancel($id)
    {
        $order = Order::find($id);

        if (!$order) {
            return $this->errorResponse('Order not found', 404);
        }

        if (in_array($order->status, ['canceled', 'completed'])) {
            return response()->json([
                'success' => false,
                'message' => 'This order cannot be cancelled.'
            ], 400);
        }

        try {
            DB::beginTransaction();

            $order->status = 'canceled';
            $data = $order->save();

            // update order payments status
            foreach ($order->orderHasPaids as $payment) {
                $payment->status = 'failed';
                $payment->save();
            }

            DB::commit();

            return $this->successResponse(
                'Order Cancelled successfully',
                $data
            );


        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order cancellation failed: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'admin_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel order. Please try again.'
            ], 500);
        }
    }



    /**
     * Return success response.
     */
    private function successResponse(string $message, $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'status' => $status,
            'message' => $message,
            'data' => $data
        ], $status);
    }

    /**
     * Return error response.
     */
    private function errorResponse(string $message, int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'status' => $status,
            'message' => $message,
        ], $status);
    }

}

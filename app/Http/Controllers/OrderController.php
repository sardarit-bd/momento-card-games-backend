<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Class OrderController
 *
 * Handles order creation, listing, and details.
 * Includes payment trace handling on order creation.
 *
 * @package App\Http\Controllers
 */
class OrderController extends Controller
{
    /**
     * Fetch all orders for the authenticated user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $user = Auth::user();

        $orders = Order::with(['orderItems.product', 'orderHasPaids'])
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Orders fetched successfully',
            'data'    => ['orders' => $orders],
        ]);
    }

public function store(Request $request)
{
    $user = Auth::user();

    // Validate request
    $validator = Validator::make($request->all(), [
        'name'              => 'required|string|max:255',
        'email'             => 'required|email',
        'phone'             => 'required|string|max:50',
        'address'           => 'required|string|max:500',
        'order_items'       => 'required|array|min:1',
        'order_items.*.product_id'   => 'required|exists:products,id',
        'order_items.*.quantity'     => 'required|integer|min:1',
        'order_items.*.price'        => 'nullable|numeric|min:0',
        'order_items.*.FinalPDF'     => 'nullable|array',
        'order_items.*.FinalProduct' => 'nullable|array',
        'payment_method'    => 'required|string|in:cash,cod,card,stripe,bkash',
        'payment_status'    => 'nullable|string|in:pending,completed,failed',
        'transaction_id'    => 'nullable|string|max:100',
        'notes'             => 'nullable|string',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'status'  => 422,
            'message' => 'Validation failed',
            'errors'  => $validator->errors(),
        ]);
    }

    DB::beginTransaction();

    try {
        $total = 0;

        // Create initial order (total will be updated after calculating)
        $order = Order::create([
            'user_id' => $user->id,
            'name'    => $request->name,
            'email'   => $request->email,
            'phone'   => $request->phone,
            'address' => $request->address,
            'total'   => 0,
            'status'  => 'pending',
        ]);

        foreach ($request->order_items as $item) {
            $quantity = $item['quantity'] ?? 0;
            $price    = $item['price'] ?? 0;

            // Update total
            $total += $quantity * $price;

            // Create order item
            $orderItem = $order->orderItems()->create([
                'product_id' => $item['product_id'],
                'quantity'   => $quantity,
                'price'      => $price,
            ]);

            // Save FinalProduct images if exist
            if (!empty($item['FinalProduct'])) {
                $orderItem->update([
                    'customization_images' => json_encode($item['FinalProduct']),
                ]);
            }

            // Save FinalPDF if exist → decode base64 and save file
            if (!empty($item['FinalPDF']['data'])) {
                $pdfData = base64_decode($item['FinalPDF']['data']);
                $fileName = 'custom_pdf_' . time() . '_' . $item['product_id'] . '.pdf';
                $filePath = 'customized_files/' . $fileName;

                Storage::disk('public')->put($filePath, $pdfData);

                $order->update([
                    'is_customized'   => true,
                    'customized_file' => $filePath,
                ]);
            }
        }

        // Update order total
        $order->update(['total' => $total]);

        // Payment record
        $order->orderHasPaids()->create([
            'amount'         => $total,
            'method'         => $request->payment_method,
            'status'         => $request->payment_status,
            'transaction_id' => $request->transaction_id ?? null,
            'notes'          => $request->notes ?? null,
        ]);

        DB::commit();

        return response()->json([
            'success' => true,
            'status'  => 201,
            'message' => 'Order created successfully',
            'data'    => [
                'order' => $order->load(['orderItems.product', 'orderHasPaids']),
            ],
        ]);
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'status'  => 500,
            'message' => 'Failed to create order',
            'error'   => $e->getMessage(),
        ]);
    }
}


    /**
     * Show a single order with its items and payments.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $user = Auth::user();

        $order = Order::with(['orderItems.product', 'orderHasPaids'])
            ->where('user_id', $user->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Order fetched successfully',
            'data'    => ['order' => $order],
        ]);
    }
}

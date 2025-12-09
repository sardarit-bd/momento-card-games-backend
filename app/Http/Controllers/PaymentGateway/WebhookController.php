<?php

namespace App\Http\Controllers\PaymentGateway;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        // $event = $request->all();

        // if ($event['type'] === 'checkout.session.completed') {
        //     // Payment succeeded
        //     Order::create([
        //         'user_id' => $event['data']['object']['customer'],
        //         'amount'  => $event['data']['object']['amount_total'],
        //     ]);
        // }

        // return response()->json(['status' => 'ok']);
    }

}

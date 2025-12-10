<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentGateway\StripeController;

Route::get('/', function () {
    return view('welcome');
});


// Stripe payment routes
Route::get('/payment/success', [StripeController::class, 'success'])
    ->name('payment.success');
Route::get('/payment/cancel', [StripeController::class, 'cancel'])
    ->name('payment.cancel');

Route::get('/order/status/{order}', [OrderController::class, 'paymentStatus']);

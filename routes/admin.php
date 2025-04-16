<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\StripeConnectController;

// Stripe Connect routes
Route::prefix('stripe-connect')->group(function () {
    Route::get('/url', [StripeConnectController::class, 'getConnectUrl']);
    Route::get('/callback', [StripeConnectController::class, 'handleCallback']);
}); 
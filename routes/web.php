<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StripeController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('stripe-form', [StripeController::class, 'form'])->name('stripeForm');
Route::post('stripe-form/submit', [StripeController::class, 'submit'])->name('stripeSubmit');
Route::get('stripe-response/{id}', [StripeController::class, 'response'])->name('stripeResponse');
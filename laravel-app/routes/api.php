<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/webhooks/wasender', 'WhatsApp\WaSenderWebhookController@info')
    ->middleware('throttle:60,1')
    ->name('whatsapp.webhook.info');

Route::post('/webhooks/wasender', 'WhatsApp\WaSenderWebhookController@handle')
    ->middleware('throttle:300,1')
    ->name('whatsapp.webhook');

Route::post('/webhooks/bill-payments', 'Property\BillPaymentWebhookController@handle')
    ->middleware('throttle:120,1')
    ->name('property.bills.webhook');

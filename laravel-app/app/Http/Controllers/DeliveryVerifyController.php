<?php

namespace App\Http\Controllers;

use App\Currency;
use App\Delivery;
use App\GeneralSetting;
use App\Support\DeliveryVerifyQr;

class DeliveryVerifyController extends Controller
{
    public function show($id, $token)
    {
        $delivery = Delivery::with(['sale.customer'])->find($id);
        $error = null;
        $payload = null;

        if (! $delivery || ! DeliveryVerifyQr::isValid($delivery, $token)) {
            $error = 'This delivery note could not be verified.';
        } else {
            $sale = $delivery->sale;
            $customer = $sale ? $sale->customer : null;
            $setting = GeneralSetting::first();
            $currency = 'CFA';
            if ($setting && $setting->currency) {
                $currencyModel = Currency::find($setting->currency);
                if ($currencyModel && $currencyModel->code) {
                    $currency = $currencyModel->code;
                }
            }

            $payload = [
                'valid' => true,
                'reference' => $delivery->reference_no,
                'sale_reference' => optional($sale)->reference_no,
                'created_at' => optional($delivery->created_at)->format('d-m-Y H:i'),
                'client' => optional($customer)->name ?: '—',
                'amount' => (float) optional($sale)->grand_total,
                'currency' => $currency,
                'status' => $delivery->isSigned() ? 'Signed / Valid' : $delivery->statusLabel(),
                'signed_at' => optional($delivery->client_signed_at)->format('d-m-Y H:i'),
            ];
        }

        return view('beyond.delivery.verify', [
            'error' => $error,
            'data' => $payload,
        ]);
    }
}

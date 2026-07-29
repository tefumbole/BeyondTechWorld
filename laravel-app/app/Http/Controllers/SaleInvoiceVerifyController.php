<?php

namespace App\Http\Controllers;

use App\Currency;
use App\Customer;
use App\GeneralSetting;
use App\Sale;
use App\Support\SaleInvoiceQr;

class SaleInvoiceVerifyController extends Controller
{
    public function show($id, $token)
    {
        $sale = Sale::find($id);
        $error = null;
        $payload = null;

        if (! $sale || ! SaleInvoiceQr::isValid($sale, $token)) {
            $error = 'This invoice could not be verified. The link may be invalid or incomplete.';
        } else {
            $customer = Customer::find($sale->customer_id);
            $paid = (float) ($sale->paid_amount ?? 0);
            $grand = (float) ($sale->grand_total ?? 0);
            $pending = max(0, $grand - $paid);
            $paymentStatus = 'Pending';
            if ((int) $sale->payment_status === 4 || ($grand > 0 && $pending <= 0.0001)) {
                $paymentStatus = 'Paid';
            } elseif ((int) $sale->payment_status === 3) {
                $paymentStatus = 'Partial';
            } elseif ((int) $sale->payment_status === 2) {
                $paymentStatus = 'Due';
            }

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
                'reference' => $sale->reference_no,
                'created_at' => optional($sale->created_at)->format('d-m-Y H:i'),
                'client' => $customer->name ?? '—',
                'amount' => $grand,
                'paid' => $paid,
                'pending' => $pending,
                'payment_status' => $paymentStatus,
                'currency' => $currency,
            ];
        }

        return view('beyond.invoice.verify', [
            'error' => $error,
            'data' => $payload,
        ]);
    }
}

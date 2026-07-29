<?php

namespace App\Http\Controllers;

use App\Delivery;
use App\GeneralSetting;
use App\Product;
use App\Product_Sale;
use App\ProductVariant;
use App\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DeliverySignatureController extends Controller
{
    public function show($token)
    {
        $delivery = $this->findOpenByToken($token);
        if (! $delivery) {
            return $this->expiredResponse();
        }

        $sale = Sale::with('customer')->find($delivery->sale_id);
        $lines = $this->lineItems($delivery);
        $general_setting = GeneralSetting::first();

        return view('delivery.client_sign', compact('delivery', 'sale', 'lines', 'general_setting'));
    }

    public function sign(Request $request, $token)
    {
        $delivery = $this->findOpenByToken($token);
        if (! $delivery) {
            return $this->expiredResponse();
        }

        $data = $request->validate([
            'confirm_receipt' => 'required|accepted',
            'signer_name' => 'nullable|string|max:191',
            'signature_data' => 'required|string',
        ]);

        $sigPath = $this->storeSignature($delivery, $data['signature_data']);
        if (! $sigPath) {
            return back()->with('not_permitted', 'Please provide a valid signature (draw in the pad, then confirm).')->withInput();
        }

        $delivery->client_signature_path = $sigPath;
        $delivery->client_signed_at = now();
        $delivery->signer_name = $data['signer_name'] ?: $delivery->recieved_by;
        $delivery->status = 3; // Delivered
        $delivery->client_signature_token = null;
        $delivery->save();

        return view('delivery.client_signed', [
            'delivery' => $delivery->fresh(['sale.customer', 'user']),
            'general_setting' => GeneralSetting::first(),
        ]);
    }

    protected function findByToken($token)
    {
        $token = trim((string) $token);
        if ($token === '') {
            return null;
        }

        return Delivery::with(['sale.customer', 'user'])
            ->where('client_signature_token', $token)
            ->first();
    }

    protected function findOpenByToken($token)
    {
        $delivery = $this->findByToken($token);
        if (! $delivery || ! $delivery->isOpenForClientSignature()) {
            return null;
        }

        return $delivery;
    }

    protected function expiredResponse()
    {
        return response()->view('delivery.client_link_expired', [
            'general_setting' => GeneralSetting::first(),
        ], 410);
    }

    protected function lineItems(Delivery $delivery)
    {
        $rows = Product_Sale::where('sale_id', $delivery->sale_id)->get();
        $lines = [];
        foreach ($rows as $row) {
            $product = Product::find($row->product_id);
            $name = $product ? $product->name : 'Product';
            $code = $product ? $product->code : '';
            if ($row->variant_id) {
                $variant = ProductVariant::select('item_code')->FindExactProduct($row->product_id, $row->variant_id)->first();
                if ($variant) {
                    $code = $variant->item_code;
                }
            }
            $lines[] = [
                'code' => $code,
                'name' => $name,
                'qty' => $row->qty + 0,
            ];
        }

        return $lines;
    }

    protected function storeSignature(Delivery $delivery, $dataUrl)
    {
        if (! is_string($dataUrl) || ! preg_match('/^data:image\/(png|jpeg);base64,/', $dataUrl)) {
            return null;
        }

        $raw = substr($dataUrl, strpos($dataUrl, ',') + 1);
        $binary = base64_decode($raw, true);
        if ($binary === false || strlen($binary) < 80) {
            return null;
        }

        $dir = public_path('uploads/deliveries/signatures');
        try {
            if (! File::isDirectory($dir)) {
                File::makeDirectory($dir, 0775, true);
            }
            if (! is_writable($dir)) {
                @chmod($dir, 0775);
            }
        } catch (\Throwable $e) {
            Log::error('Delivery signature dir failed: '.$e->getMessage());

            return null;
        }

        $filename = 'dsig_'.$delivery->id.'_'.Str::random(10).'.png';
        $full = $dir.DIRECTORY_SEPARATOR.$filename;
        try {
            if (File::put($full, $binary) === false) {
                return null;
            }
            @chmod($full, 0664);
        } catch (\Throwable $e) {
            Log::error('Delivery signature write failed: '.$e->getMessage());

            return null;
        }

        return 'uploads/deliveries/signatures/'.$filename;
    }
}

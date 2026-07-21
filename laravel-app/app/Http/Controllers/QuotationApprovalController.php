<?php

namespace App\Http\Controllers;

use App\GeneralSetting;
use App\ProductQuotation;
use App\Product;
use App\Quotation;
use App\Unit;
use App\Variant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class QuotationApprovalController extends Controller
{
    public function show($token)
    {
        $quotation = $this->findByToken($token);
        if (! $quotation) {
            abort(404, 'Quotation link is invalid or has expired.');
        }

        if (in_array((int) $quotation->quotation_status, [Quotation::STATUS_APPROVED, Quotation::STATUS_REJECTED], true)) {
            return view('quotation.client_responded', [
                'quotation' => $quotation,
                'general_setting' => GeneralSetting::first(),
            ]);
        }

        $lines = $this->lineItems($quotation);
        $general_setting = GeneralSetting::first();

        return view('quotation.client_approval', compact('quotation', 'lines', 'general_setting'));
    }

    public function approve(Request $request, $token)
    {
        $quotation = $this->findByToken($token);
        if (! $quotation) {
            abort(404);
        }

        if ((int) $quotation->quotation_status !== Quotation::STATUS_AWAITING) {
            return redirect()->route('quotation.client.show', $token)
                ->with('message', 'This quotation has already been responded to.');
        }

        $data = $request->validate([
            'accept_agreement' => 'required|accepted',
            'client_comment' => 'nullable|string|max:2000',
            'signature_data' => 'required|string',
        ]);

        $sigPath = $this->storeSignature($quotation, $data['signature_data']);
        if (! $sigPath) {
            return back()->with('not_permitted', 'Please provide a valid signature.')->withInput();
        }

        $quotation->quotation_status = Quotation::STATUS_APPROVED;
        $quotation->client_signature_path = $sigPath;
        $quotation->client_signed_at = now();
        $quotation->client_comment = $data['client_comment'] ?? null;
        $quotation->client_responded_at = now();
        $quotation->save();

        return view('quotation.client_responded', [
            'quotation' => $quotation->fresh(['customer', 'biller']),
            'general_setting' => GeneralSetting::first(),
        ]);
    }

    public function reject(Request $request, $token)
    {
        $quotation = $this->findByToken($token);
        if (! $quotation) {
            abort(404);
        }

        if ((int) $quotation->quotation_status !== Quotation::STATUS_AWAITING) {
            return redirect()->route('quotation.client.show', $token)
                ->with('message', 'This quotation has already been responded to.');
        }

        $data = $request->validate([
            'client_comment' => 'required|string|max:2000',
        ]);

        $quotation->quotation_status = Quotation::STATUS_REJECTED;
        $quotation->client_comment = $data['client_comment'];
        $quotation->client_responded_at = now();
        $quotation->save();

        return view('quotation.client_responded', [
            'quotation' => $quotation->fresh(['customer', 'biller']),
            'general_setting' => GeneralSetting::first(),
        ]);
    }

    protected function findByToken($token)
    {
        $token = trim((string) $token);
        if ($token === '') {
            return null;
        }

        return Quotation::with(['customer', 'biller', 'warehouse', 'supplier'])
            ->where('client_approval_token', $token)
            ->first();
    }

    protected function lineItems(Quotation $quotation)
    {
        $rows = ProductQuotation::where('quotation_id', $quotation->id)->get();
        $lines = [];
        foreach ($rows as $row) {
            $product = Product::find($row->product_id);
            $name = $product ? $product->name : 'Product';
            if ($row->variant_id) {
                $variant = Variant::find($row->variant_id);
                if ($variant) {
                    $name .= ' ['.$variant->name.']';
                }
            }
            $unit = '';
            if ($row->sale_unit_id) {
                $u = Unit::find($row->sale_unit_id);
                $unit = $u ? $u->unit_code : '';
            }
            $lines[] = [
                'name' => $name,
                'code' => $product ? $product->code : '',
                'qty' => $row->qty,
                'unit' => $unit,
                'net_unit_price' => $row->net_unit_price,
                'total' => $row->total,
            ];
        }

        return $lines;
    }

    protected function storeSignature(Quotation $quotation, $dataUrl)
    {
        if (! preg_match('/^data:image\/(png|jpeg);base64,/', $dataUrl)) {
            return null;
        }

        $raw = substr($dataUrl, strpos($dataUrl, ',') + 1);
        $binary = base64_decode($raw);
        if ($binary === false || strlen($binary) < 100) {
            return null;
        }

        $dir = public_path('uploads/quotations/signatures');
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $filename = 'qsig_'.$quotation->id.'_'.Str::random(8).'.png';
        File::put($dir.DIRECTORY_SEPARATOR.$filename, $binary);

        return 'uploads/quotations/signatures/'.$filename;
    }
}

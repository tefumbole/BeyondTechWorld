<?php

namespace App\Http\Controllers;

use App\GeneralSetting;
use App\Product;
use App\ProductQuotation;
use App\Quotation;
use App\QuotationQuote;
use App\Unit;
use App\Variant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QuotationApprovalController extends Controller
{
    public function show($token)
    {
        $quotation = $this->findOpenByToken($token);
        if (! $quotation) {
            return $this->expiredResponse();
        }

        $lines = $this->lineItems($quotation);
        $general_setting = GeneralSetting::first();

        return view('quotation.client_approval', compact('quotation', 'lines', 'general_setting'));
    }

    public function approve(Request $request, $token)
    {
        $quotation = $this->findOpenByToken($token);
        if (! $quotation) {
            return $this->expiredResponse();
        }

        $data = $request->validate([
            'accept_agreement' => 'required|accepted',
            'client_comment' => 'nullable|string|max:2000',
            'signature_data' => 'required|string',
        ]);

        $sigPath = $this->storeSignature($quotation, $data['signature_data']);
        if (! $sigPath) {
            return back()->with('not_permitted', 'Please provide a valid signature (draw in the pad, then confirm).')->withInput();
        }

        $quotation->quotation_status = Quotation::STATUS_APPROVED;
        $quotation->client_signature_path = $sigPath;
        $quotation->client_signed_at = now();
        $quotation->client_comment = $data['client_comment'] ?? null;
        $quotation->client_responded_at = now();
        $quotation->client_approval_token = null;
        $quotation->save();

        // Force persist in case PDO string status comparisons left the row on awaiting.
        \DB::table('quotations')->where('id', $quotation->id)->update([
            'quotation_status' => Quotation::STATUS_APPROVED,
            'client_signature_path' => $sigPath,
            'client_signed_at' => now(),
            'client_responded_at' => now(),
            'client_comment' => $data['client_comment'] ?? null,
            'client_approval_token' => null,
            'updated_at' => now(),
        ]);

        $quotation = $quotation->fresh(['customer', 'biller', 'user']);
        $this->notifyStakeholders($quotation, 'approved');

        // Official PDF (staff + client signatures) is delivered only after the client signs.
        // Reject path never calls this — no PDF on rejection.
        try {
            app(QuotationController::class)->deliverQuotationPdfToClient($quotation, $quotation->customer, 'signed');
        } catch (\Throwable $e) {
            Log::warning('Post-signature quotation PDF delivery failed for '.$quotation->reference_no.': '.$e->getMessage());
        }

        return view('quotation.client_responded', [
            'quotation' => $quotation,
            'general_setting' => GeneralSetting::first(),
        ]);
    }

    public function reject(Request $request, $token)
    {
        $quotation = $this->findOpenByToken($token);
        if (! $quotation) {
            return $this->expiredResponse();
        }

        $data = $request->validate([
            'client_comment' => 'required|string|max:2000',
        ]);

        $quotation->quotation_status = Quotation::STATUS_REJECTED;
        $quotation->client_comment = $data['client_comment'];
        $quotation->client_responded_at = now();
        $quotation->client_approval_token = null;
        $quotation->save();

        $this->notifyStakeholders($quotation->fresh(), 'rejected');

        return view('quotation.client_responded', [
            'quotation' => $quotation->fresh(['customer', 'biller']),
            'general_setting' => GeneralSetting::first(),
        ]);
    }

    /**
     * Client counter-quote: overall total or per-line unit prices.
     */
    public function quote(Request $request, $token)
    {
        $quotation = $this->findOpenByToken($token);
        if (! $quotation) {
            return $this->expiredResponse();
        }

        $data = $request->validate([
            'mode' => 'required|in:overall,lines',
            'proposed_grand_total' => 'nullable|numeric|min:0.01',
            'client_note' => 'nullable|string|max:2000',
            'lines' => 'nullable|array',
            'lines.*.product_quotation_id' => 'required_with:lines|integer',
            'lines.*.proposed_net_unit_price' => 'required_with:lines|numeric|min:0',
        ]);

        $rows = ProductQuotation::where('quotation_id', $quotation->id)->get()->keyBy('id');
        if ($rows->isEmpty()) {
            return back()->with('not_permitted', 'This quotation has no line items to quote.')->withInput();
        }

        $mode = $data['mode'];
        $originalGrand = (float) $quotation->grand_total;
        $orderTax = (float) ($quotation->order_tax ?? 0);
        $shipping = (float) ($quotation->shipping_cost ?? 0);
        $orderDiscount = (float) ($quotation->order_discount ?? 0);
        $linePayload = [];
        $proposedGrand = 0.0;
        $changed = false;

        if ($mode === 'overall') {
            $proposedGrand = round((float) ($data['proposed_grand_total'] ?? 0), 2);
            if ($proposedGrand < 0.01) {
                return back()->withErrors(['proposed_grand_total' => 'Enter a proposed total greater than zero.'])->withInput();
            }
            if (abs($proposedGrand - $originalGrand) < 0.009) {
                return back()->withErrors(['proposed_grand_total' => 'Proposed total must differ from the current total.'])->withInput();
            }

            // Distribute proposed grand across lines proportionally by original line totals.
            $baseLinesSum = 0.0;
            foreach ($rows as $row) {
                $baseLinesSum += max(0.0, (float) $row->total);
            }
            // Target for product lines = proposed grand − tax − shipping + discount
            $targetLines = round($proposedGrand - $orderTax - $shipping + $orderDiscount, 2);
            if ($targetLines < 0) {
                $targetLines = 0.0;
            }
            $i = 0;
            $count = $rows->count();
            $assigned = 0.0;
            foreach ($rows as $row) {
                $i++;
                $origUnit = (float) $row->net_unit_price;
                $origTotal = (float) $row->total;
                $qty = max(0.0001, (float) $row->qty);
                if ($i === $count) {
                    $newTotal = round($targetLines - $assigned, 2);
                } else {
                    $share = $baseLinesSum > 0 ? ($origTotal / $baseLinesSum) : (1 / $count);
                    $newTotal = round($targetLines * $share, 2);
                    $assigned += $newTotal;
                }
                $newUnit = round($newTotal / $qty, 4);
                if (abs($newUnit - $origUnit) > 0.0001 || abs($newTotal - $origTotal) > 0.009) {
                    $changed = true;
                }
                $linePayload[] = [
                    'product_quotation_id' => (int) $row->id,
                    'original_net_unit_price' => $origUnit,
                    'original_total' => $origTotal,
                    'proposed_net_unit_price' => $newUnit,
                    'proposed_total' => $newTotal,
                ];
            }
            $changed = true;
        } else {
            $incoming = [];
            foreach (($data['lines'] ?? []) as $line) {
                $incoming[(int) $line['product_quotation_id']] = (float) $line['proposed_net_unit_price'];
            }
            $linesSum = 0.0;
            foreach ($rows as $row) {
                $pqId = (int) $row->id;
                if (! array_key_exists($pqId, $incoming)) {
                    return back()->withErrors(['lines' => 'Please propose a unit price for every item.'])->withInput();
                }
                $origUnit = (float) $row->net_unit_price;
                $origTotal = (float) $row->total;
                $qty = (float) $row->qty;
                $newUnit = round($incoming[$pqId], 4);
                $newTotal = round($newUnit * $qty, 2);
                $linesSum += $newTotal;
                if (abs($newUnit - $origUnit) > 0.0001) {
                    $changed = true;
                }
                $linePayload[] = [
                    'product_quotation_id' => $pqId,
                    'original_net_unit_price' => $origUnit,
                    'original_total' => $origTotal,
                    'proposed_net_unit_price' => $newUnit,
                    'proposed_total' => $newTotal,
                ];
            }
            $proposedGrand = round($linesSum + $orderTax + $shipping - $orderDiscount, 2);
            if (! $changed) {
                return back()->withErrors(['lines' => 'Change at least one unit price before submitting a quote.'])->withInput();
            }
        }

        // Supersede any prior pending quotes.
        QuotationQuote::where('quotation_id', $quotation->id)
            ->where('status', QuotationQuote::STATUS_PENDING)
            ->update(['status' => QuotationQuote::STATUS_REJECTED]);

        $quote = QuotationQuote::create([
            'quotation_id' => $quotation->id,
            'mode' => $mode,
            'proposed_grand_total' => $proposedGrand,
            'original_grand_total' => $originalGrand,
            'client_note' => $data['client_note'] ?? ($request->input('client_comment') ?: null),
            'status' => QuotationQuote::STATUS_PENDING,
        ]);
        foreach ($linePayload as $lp) {
            $quote->lines()->create($lp);
        }

        $quotation->quotation_status = Quotation::STATUS_CLIENT_QUOTE;
        $quotation->client_comment = $quote->client_note;
        $quotation->client_responded_at = now();
        $quotation->client_approval_token = null;
        $quotation->save();

        try {
            app(QuotationController::class)->notifyClientQuoteSubmitted($quotation->fresh(['customer', 'user']), $quote);
        } catch (\Throwable $e) {
            Log::warning('Client quote notify failed: '.$e->getMessage());
        }

        return view('quotation.client_responded', [
            'quotation' => $quotation->fresh(['customer', 'biller']),
            'general_setting' => GeneralSetting::first(),
            'quote' => $quote,
        ]);
    }

    protected function notifyStakeholders(Quotation $quotation, $event)
    {
        try {
            app(QuotationController::class)->notifyQuotationStakeholders($quotation, $event);
        } catch (\Throwable $e) {
            Log::warning('Quotation client-response notify failed: '.$e->getMessage());
        }
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

    /**
     * Only return a quotation that is still open for client signature.
     * Used / expired / already-responded links resolve to null.
     */
    protected function findOpenByToken($token)
    {
        $quotation = $this->findByToken($token);
        if (! $quotation || ! $quotation->isOpenForClientApproval()) {
            return null;
        }

        return $quotation;
    }

    protected function expiredResponse()
    {
        return response()->view('quotation.client_link_expired', [
            'general_setting' => GeneralSetting::first(),
        ], 410);
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
                'id' => (int) $row->id,
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
        if (! is_string($dataUrl) || ! preg_match('/^data:image\/(png|jpeg);base64,/', $dataUrl)) {
            return null;
        }

        $raw = substr($dataUrl, strpos($dataUrl, ',') + 1);
        $binary = base64_decode($raw, true);
        if ($binary === false || strlen($binary) < 80) {
            return null;
        }

        // Writable path under public/uploads (deploy ensures www-data ownership)
        $dir = public_path('uploads/quotations/signatures');
        try {
            if (! File::isDirectory($dir)) {
                File::makeDirectory($dir, 0775, true);
            }
            if (! is_writable($dir)) {
                @chmod($dir, 0775);
            }
        } catch (\Throwable $e) {
            Log::error('Quotation signature dir failed: '.$e->getMessage());

            return null;
        }

        $filename = 'qsig_'.$quotation->id.'_'.Str::random(10).'.png';
        $full = $dir.DIRECTORY_SEPARATOR.$filename;
        try {
            if (File::put($full, $binary) === false) {
                return null;
            }
            @chmod($full, 0664);
        } catch (\Throwable $e) {
            Log::error('Quotation signature write failed: '.$e->getMessage());

            return null;
        }

        return 'uploads/quotations/signatures/'.$filename;
    }
}

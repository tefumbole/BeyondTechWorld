<?php

namespace App\Services\Rental;

use App\Booking;
use App\BookingProduct;
use App\Product;
use App\ProductQuotation;
use App\Quotation;
use App\Services\WhatsApp\WhatsAppConversationService;
use App\Services\WhatsApp\WhatsAppLeadService;
use App\User;
use Illuminate\Support\Facades\Schema;

class RentalQuoteService
{
    protected $availability;
    protected $leads;
    protected $conversations;

    public function __construct(
        RentalAvailabilityService $availability,
        WhatsAppLeadService $leads,
        WhatsAppConversationService $conversations
    ) {
        $this->availability = $availability;
        $this->leads = $leads;
        $this->conversations = $conversations;
    }

    public function createDraft(array $slots, array $context)
    {
        $range = $this->availability->resolveRange($slots);
        $query = isset($slots['product']) ? $slots['product'] : (isset($slots['query']) ? $slots['query'] : '');
        if (! $range) {
            return ['success' => false, 'error' => 'missing_requirements'];
        }
        $proposalLines = $this->usableLines($slots, $context);
        $generic = in_array(strtolower(trim((string) $query)), ['sound', 'audio', 'lighting', 'light', 'lights', 'led'], true);
        if ($proposalLines && ($generic || trim((string) $query) === '' || $this->availability->search($query, 1)->isEmpty())) {
            return $this->draftFromLines($proposalLines, $range, $slots, $context);
        }
        if (trim((string) $query) === '') {
            return ['success' => false, 'error' => 'missing_requirements'];
        }
        $products = $this->availability->search($query, 1);
        $product = $products->first();
        if (! $product) {
            return ['success' => false, 'error' => 'not_found'];
        }
        $qty = isset($slots['qty']) ? (int) $slots['qty'] : 1;
        $check = $this->availability->assess($product, $qty, $range['start'], $range['end']);
        if (empty($check['availability_checked'])) {
            return ['success' => false, 'error' => 'inventory_unavailable', 'check' => $check];
        }
        if (empty($check['available'])) {
            return [
                'success' => false,
                'error' => 'unavailable',
                'check' => $check,
                'alternatives' => $this->availability->alternatives($product, $qty, $range['start'], $range['end']),
            ];
        }
        if (empty($check['priced'])) {
            return ['success' => false, 'error' => 'unpriced', 'check' => $check];
        }
        if (! Schema::hasTable('quotations')) {
            return ['success' => false, 'error' => 'inventory_unavailable'];
        }

        $customer = $this->ensureCustomer($context);
        if (! $customer) {
            return ['success' => false, 'error' => 'no_customer'];
        }

        $days = (int) $range['days'];
        $lineTotal = round($check['day_rate'] * $check['requested_qty'] * $days, 2);
        $reference = 'qr-'.date('Ymd').'-'.date('His').substr(uniqid(), -3);
        $note = 'WhatsApp rental draft for '.$check['name'].' x'.$check['requested_qty']
            .' from '.$range['start'].' to '.$range['end'].'. Staff must review before signature or booking.';
        if (! empty($slots['event_type'])) {
            $note .= ' Event: '.$slots['event_type'].'.';
        }
        if (! empty($slots['location'])) {
            $note .= ' Location: '.$slots['location'].'.';
        }

        $payload = $this->onlyColumns('quotations', [
            'reference_no' => $reference,
            'user_id' => User::query()->where('role_id', '<=', 2)->value('id'),
            'customer_id' => $customer->id,
            'item' => 1,
            'total_qty' => $check['requested_qty'],
            'total_discount' => 0,
            'total_tax' => 0,
            'total_price' => $lineTotal,
            'order_tax_rate' => 0,
            'order_tax' => 0,
            'order_discount' => 0,
            'shipping_cost' => 0,
            'grand_total' => $lineTotal,
            'quotation_status' => Quotation::STATUS_PENDING,
            'note' => $note,
            'whatsapp_conversation_id' => isset($context['conversation_id']) ? $context['conversation_id'] : null,
            'whatsapp_lead_id' => isset($context['lead']['id']) ? $context['lead']['id'] : null,
            'quotation_source' => 'whatsapp',
            'revised_from_id' => isset($slots['revised_from_id']) ? $slots['revised_from_id'] : null,
        ]);
        $quotation = Quotation::create($payload);
        $this->storeLine($quotation->id, $product, $check, $lineTotal);

        return [
            'success' => true,
            'quotation_id' => $quotation->id,
            'reference' => $quotation->reference_no,
            'grand_total' => $lineTotal,
            'status' => 'Draft',
            'days' => $days,
            'auto_send' => false,
            'awaiting_staff' => true,
            'check' => $check,
            'customer_id' => $customer->id,
        ];
    }

    public function requestBooking(array $slots, array $context)
    {
        $quoteId = isset($slots['quotation_id']) ? (int) $slots['quotation_id'] : 0;
        $quote = $quoteId && Schema::hasTable('quotations') ? Quotation::find($quoteId) : null;
        if (! $quote) {
            return ['success' => false, 'error' => 'no_quote'];
        }
        if ((int) $quote->quotation_status !== Quotation::STATUS_AWAITING || empty($quote->client_approval_token)) {
            return [
                'success' => true,
                'acceptance' => 'not_sent',
                'reference' => $quote->reference_no,
                'booking_created' => false,
            ];
        }
        $quote->rotateApprovalToken();

        return [
            'success' => true,
            'acceptance' => 'link',
            'reference' => $quote->reference_no,
            'approval_url' => $quote->approvalUrl(),
            'booking_created' => false,
        ];
    }

    public function approveAndSend(Quotation $quotation, $conversation, $userId = null)
    {
        $request = null;
        if (Schema::hasTable('whatsapp_rental_requests')) {
            $request = \App\WhatsApp\RentalRequest::where('quotation_id', $quotation->id)->orderByDesc('id')->first();
        }
        $range = $request && $request->event_date
            ? ['start' => $request->event_date->toDateString(), 'end' => $request->return_at ? $request->return_at->toDateString() : $request->event_date->copy()->addDay()->toDateString()]
            : null;
        if ($range && Schema::hasTable('product_quotation')) {
            foreach (ProductQuotation::where('quotation_id', $quotation->id)->get() as $line) {
                $product = Product::find($line->product_id);
                if (! $product) {
                    continue;
                }
                $check = $this->availability->assess($product, $line->qty, $range['start'], $range['end']);
                if (empty($check['availability_checked'])) {
                    return ['success' => false, 'error' => 'inventory_unavailable'];
                }
                if (empty($check['available'])) {
                    if ($request) {
                        $request->status = \App\WhatsApp\RentalRequest::CHECKED;
                        $request->availability_note = 'recheck_failed';
                        $request->availability_checked_at = now();
                        $request->save();
                    }

                    return ['success' => false, 'error' => 'availability_changed', 'name' => $product->name, 'available_qty' => $check['available_qty']];
                }
            }
        }
        $quotation->quotation_status = Quotation::STATUS_AWAITING;
        $quotation->save();
        $quotation->rotateApprovalToken();
        $pdfSent = false;
        $pdfError = null;
        try {
            $path = app(\App\Http\Controllers\QuotationController::class)->buildQuotationPdf($quotation->id);
            $send = $this->conversations->sendExistingDocument(
                $conversation,
                $path,
                'quotation_'.$quotation->reference_no.'.pdf',
                'Quotation '.$quotation->reference_no,
                $userId,
                'STAFF'
            );
            $pdfSent = ! empty($send['success']);
            if (! $pdfSent) {
                $pdfError = isset($send['error']) ? $send['error'] : 'send_failed';
            }
        } catch (\Throwable $e) {
            $pdfError = $e->getMessage();
        }
        if ($request) {
            $request->status = $pdfSent ? \App\WhatsApp\RentalRequest::QUOTE_SENT : \App\WhatsApp\RentalRequest::AWAITING_STAFF;
            $request->save();
            app(RentalRequestService::class)->log($request, $pdfSent ? 'quotation_sent' : 'quotation_send_failed', $pdfError ?: 'Sent', [], $userId);
        }
        if (! $pdfSent) {
            $quotation->quotation_status = Quotation::STATUS_PENDING;
            $quotation->save();

            return ['success' => false, 'error' => 'pdf_failed', 'pdf_error' => $pdfError, 'reference' => $quotation->reference_no];
        }
        $url = $quotation->approvalUrl();
        $this->conversations->assistantReply($conversation, 'Your quotation '.$quotation->reference_no.' is ready. Review and approve it here: '.$url);

        return ['success' => true, 'reference' => $quotation->reference_no, 'approval_url' => $url];
    }

    protected function usableLines(array $slots, array $context)
    {
        $conversation = isset($context['conversation']) ? $context['conversation'] : null;
        if (! $conversation) {
            return [];
        }
        $request = app(RentalRequestService::class)->active($conversation);
        if (! $request) {
            return [];
        }
        $proposal = app(RentalRecommendationService::class)->propose($request, $slots);
        $usable = [];
        foreach (isset($proposal['lines']) ? $proposal['lines'] : [] as $line) {
            if (! empty($line['product_id']) && ! empty($line['success']) && ! empty($line['available']) && ! empty($line['quantity'])) {
                $usable[] = $line;
            }
        }

        return $usable;
    }

    protected function draftFromLines(array $lines, array $range, array $slots, array $context)
    {
        if (! Schema::hasTable('quotations')) {
            return ['success' => false, 'error' => 'inventory_unavailable'];
        }
        $customer = $this->ensureCustomer($context);
        if (! $customer) {
            return ['success' => false, 'error' => 'no_customer'];
        }
        $stored = [];
        $grand = 0;
        $qtySum = 0;
        $names = [];
        foreach ($lines as $line) {
            $product = Product::find($line['product_id']);
            if (! $product) {
                continue;
            }
            $qty = isset($line['quantity']) ? (int) $line['quantity'] : 1;
            $check = $this->availability->assess($product, $qty, $range['start'], $range['end']);
            if (empty($check['availability_checked']) || empty($check['available']) || empty($check['priced'])) {
                continue;
            }
            $lineTotal = round($check['day_rate'] * $check['requested_qty'] * (int) $range['days'], 2);
            $stored[] = [$product, $check, $lineTotal];
            $grand += $lineTotal;
            $qtySum += (int) $check['requested_qty'];
            $names[] = $check['name'];
        }
        if ($stored === []) {
            return ['success' => false, 'error' => 'unavailable'];
        }
        $note = 'WhatsApp rental draft for '.implode(', ', $names)
            .' from '.$range['start'].' to '.$range['end'].'. Staff must review before signature or booking.';
        if (! empty($slots['event_type'])) {
            $note .= ' Event: '.$slots['event_type'].'.';
        }
        if (! empty($slots['location'])) {
            $note .= ' Location: '.$slots['location'].'.';
        }
        $payload = $this->onlyColumns('quotations', [
            'reference_no' => 'qr-'.date('Ymd').'-'.date('His').substr(uniqid(), -3),
            'user_id' => User::query()->where('role_id', '<=', 2)->value('id'),
            'customer_id' => $customer->id,
            'item' => count($stored),
            'total_qty' => $qtySum,
            'total_discount' => 0,
            'total_tax' => 0,
            'total_price' => $grand,
            'order_tax_rate' => 0,
            'order_tax' => 0,
            'order_discount' => 0,
            'shipping_cost' => 0,
            'grand_total' => $grand,
            'quotation_status' => Quotation::STATUS_PENDING,
            'note' => $note,
            'whatsapp_conversation_id' => isset($context['conversation_id']) ? $context['conversation_id'] : null,
            'whatsapp_lead_id' => isset($context['lead']['id']) ? $context['lead']['id'] : null,
            'quotation_source' => 'whatsapp',
            'revised_from_id' => isset($slots['revised_from_id']) ? $slots['revised_from_id'] : null,
        ]);
        $quotation = Quotation::create($payload);
        foreach ($stored as $row) {
            $this->storeLine($quotation->id, $row[0], $row[1], $row[2]);
        }

        return [
            'success' => true,
            'quotation_id' => $quotation->id,
            'reference' => $quotation->reference_no,
            'grand_total' => $grand,
            'status' => 'Draft',
            'days' => (int) $range['days'],
            'auto_send' => false,
            'awaiting_staff' => true,
            'customer_id' => $customer->id,
        ];
    }

    protected function storeLine($quotationId, Product $product, array $check, $lineTotal)
    {
        if (! Schema::hasTable('product_quotation')) {
            return;
        }
        ProductQuotation::create($this->onlyColumns('product_quotation', [
            'quotation_id' => $quotationId,
            'product_id' => $product->id,
            'qty' => $check['requested_qty'],
            'sale_unit_id' => 0,
            'variant_id' => null,
            'net_unit_price' => $check['day_rate'],
            'discount' => 0,
            'tax_rate' => 0,
            'tax' => 0,
            'total' => $lineTotal,
        ]));
    }

    protected function ensureCustomer(array $context)
    {
        $phone = isset($context['phone']) ? $context['phone'] : '';
        if ($phone === '') {
            return null;
        }
        $existing = $this->leads->findExistingCustomer($phone);
        if ($existing) {
            $this->relink($context);

            return $existing;
        }
        $payload = $this->onlyColumns('customers', [
            'name' => isset($context['contact_name']) && $context['contact_name'] ? $context['contact_name'] : $phone,
            'phone_number' => $phone,
            'is_active' => true,
            'address' => 'NAN',
            'city' => 'NAN',
            'customer_group_id' => 1,
        ]);
        if (! isset($payload['name'])) {
            return null;
        }
        $customer = \App\Customer::create($payload);
        $this->relink($context);

        return $customer;
    }

    protected function relink(array $context)
    {
        $conversation = isset($context['conversation']) ? $context['conversation'] : null;
        if ($conversation && $conversation->contact) {
            $this->conversations->syncIdentityLinks($conversation->contact);
        }
    }

    protected function onlyColumns($table, array $payload)
    {
        $out = [];
        foreach ($payload as $key => $value) {
            if (Schema::hasColumn($table, $key)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}

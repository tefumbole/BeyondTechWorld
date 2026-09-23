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
        if (! $range || trim((string) $query) === '') {
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
        $cap = (float) config('assistant.rental_auto_quote_max', 500000);
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
            'auto_send' => $lineTotal <= $cap,
            'over_cap' => $lineTotal > $cap,
            'check' => $check,
            'customer_id' => $customer->id,
        ];
    }

    public function requestBooking(array $slots, array $context)
    {
        $quoteId = isset($slots['quotation_id']) ? (int) $slots['quotation_id'] : 0;
        if (! $quoteId || ! Schema::hasTable('quotations') || ! Schema::hasTable('bookings')) {
            return ['success' => false, 'error' => 'no_quote'];
        }
        $quote = Quotation::find($quoteId);
        if (! $quote) {
            return ['success' => false, 'error' => 'no_quote'];
        }
        $range = $this->availability->resolveRange($slots);
        $query = isset($slots['product']) ? $slots['product'] : '';
        $products = $query !== '' ? $this->availability->search($query, 1) : collect();
        $product = $products->first();
        $reference = 'wa-bk-'.date('Ymd').'-'.date('His').substr(uniqid(), -3);
        $note = 'WhatsApp confirmation of draft '.$quote->reference_no.'. Staff must approve before this reserves equipment.';
        $payload = $this->onlyColumns('bookings', [
            'reference_no' => $reference,
            'user_id' => $quote->user_id,
            'customer_id' => $quote->customer_id,
            'warehouse_id' => isset($quote->warehouse_id) ? $quote->warehouse_id : null,
            'biller_id' => isset($quote->biller_id) ? $quote->biller_id : null,
            'item' => 1,
            'total_qty' => $quote->total_qty,
            'total_discount' => 0,
            'total_tax' => 0,
            'total_price' => $quote->total_price,
            'order_tax_rate' => 0,
            'order_tax' => 0,
            'order_discount' => 0,
            'shipping_cost' => 0,
            'grand_total' => $quote->grand_total,
            'booking_status' => 5,
            'payment_status' => 1,
            'paid_amount' => 0,
            'booking_note' => $note,
            'staff_note' => 'Created from WhatsApp. Draft only — does not hold inventory.',
        ]);
        $booking = Booking::create($payload);
        if ($product && $range && Schema::hasTable('booking_products')) {
            $line = $this->onlyColumns('booking_products', [
                'booking_id' => $booking->id,
                'product_id' => $product->id,
                'qty' => isset($slots['qty']) ? (int) $slots['qty'] : ($quote->total_qty ?: 1),
                'net_unit_price' => $product->rent_price_per_day,
                'discount' => 0,
                'tax_rate' => 0,
                'tax' => 0,
                'total' => $quote->grand_total,
                'start' => $range['start'].' 08:00:00',
                'end' => $range['end'].' 08:00:00',
            ]);
            BookingProduct::create($line);
        }

        return [
            'success' => true,
            'booking_requested' => true,
            'booking_id' => $booking->id,
            'reference' => $booking->reference_no,
            'quotation_reference' => $quote->reference_no,
            'status' => 'Draft',
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

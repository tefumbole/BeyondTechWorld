<?php

namespace Tests\Feature;

use App\Booking;
use App\BookingProduct;
use App\Contracts\Ai\AiProviderInterface;
use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\Product;
use App\Quotation;
use App\Services\Assistant\Providers\NullAiProvider;
use App\Services\Rental\RentalAvailabilityService;
use App\User;
use App\WhatsApp\WhatsAppMessage;
use App\WhatsApp\WhatsAppSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\WhatsAppHubTestCase;

class WhatsAppPhase4Test extends WhatsAppHubTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(WhatsAppProviderInterface::class, $this->fakeProvider());
        $this->app->instance(AiProviderInterface::class, new NullAiProvider());
        $this->rentalTables();
        config(['assistant.enabled' => true, 'assistant.provider' => 'null', 'assistant.rental_auto_quote_max' => 500000]);
        WhatsAppSetting::putValue('assistant_enabled', '1');
        WhatsAppSetting::putValue('default_conversation_mode', 'AI');
        User::create([
            'name' => 'Quote Admin',
            'email' => 'quote-admin@example.test',
            'password' => Hash::make('secret'),
            'phone' => '675000001',
            'role_id' => 1,
            'is_active' => true,
            'is_deleted' => false,
        ]);
    }

    public function test_dated_request_uses_stock_and_does_not_confirm_booking()
    {
        $product = $this->speaker(4);
        $this->postWebhook($this->incomingText('+237675400001', 'Do you have 2 JBL speakers available Saturday?', 'P4A1'))->assertStatus(200);
        $out = WhatsAppMessage::where('sender_type', 'ASSISTANT')->first();
        $this->assertNotNull($out);
        $this->assertStringContainsString('4 available', strtolower($out->body));
        $this->assertStringContainsString('not a confirmed booking', strtolower($out->body));
        $this->assertStringContainsString('25000', str_replace(',', '', $out->body));
        $range = app(RentalAvailabilityService::class)->resolveRange(['event_date' => 'saturday']);
        $this->assertStringContainsString($range['start'], $out->body);
        $this->assertSame(4, (int) $product->fresh()->qty);
    }

    public function test_pending_booking_reduces_availability()
    {
        $product = $this->speaker(2);
        $range = app(RentalAvailabilityService::class)->resolveRange(['event_date' => 'saturday']);
        $booking = Booking::create([
            'reference_no' => 'BK-HOLD',
            'booking_status' => 2,
            'payment_status' => 1,
        ]);
        BookingProduct::create([
            'booking_id' => $booking->id,
            'product_id' => $product->id,
            'qty' => 2,
            'start' => $range['start'].' 08:00:00',
            'end' => $range['end'].' 08:00:00',
        ]);
        $this->postWebhook($this->incomingText('+237675400002', 'Do you have 2 JBL speakers available Saturday?', 'P4A2'))->assertStatus(200);
        $out = WhatsAppMessage::where('sender_type', 'ASSISTANT')->first();
        $this->assertStringContainsString('not available', strtolower($out->body));
        $this->assertStringContainsString('not a confirmed booking', strtolower($out->body));
    }

    public function test_catalogue_question_without_date_does_not_claim_availability()
    {
        $this->speaker(3);
        $this->postWebhook($this->incomingText('+237675400003', 'Do you have JBL speakers?', 'P4A3'))->assertStatus(200);
        $out = WhatsAppMessage::where('sender_type', 'ASSISTANT')->first();
        $this->assertStringContainsString('not a confirmed booking', strtolower($out->body));
        $this->assertStringContainsString('date check', strtolower($out->body));
    }

    public function test_draft_quotation_then_staff_review_booking()
    {
        $this->speaker(4);
        $this->postWebhook($this->incomingText('+237675400004', 'Do you have 2 JBL speakers available Saturday?', 'P4Q1'))->assertStatus(200);
        $this->postWebhook($this->incomingText('+237675400004', 'Send me a quotation', 'P4Q2'))->assertStatus(200);
        $quote = Quotation::orderByDesc('id')->first();
        $this->assertNotNull($quote);
        $this->assertSame(Quotation::STATUS_PENDING, (int) $quote->quotation_status);
        $this->assertEquals(50000, (float) $quote->grand_total);
        $reply = WhatsAppMessage::where('sender_type', 'ASSISTANT')->orderByDesc('id')->first();
        $this->assertStringContainsString($quote->reference_no, $reply->body);
        $this->assertStringContainsString('not a confirmed booking', strtolower($reply->body));

        $this->assertSame(0, WhatsAppMessage::where('type', 'DOCUMENT')->count());
        $this->assertStringContainsString('staff must approve', strtolower($reply->body));
        $this->postWebhook($this->incomingText('+237675400004', 'I accept the quotation', 'P4Q3'))->assertStatus(200);
        $this->assertSame(0, Booking::count());
        $accept = WhatsAppMessage::where('sender_type', 'ASSISTANT')->orderByDesc('id')->first();
        $this->assertStringContainsString('not created a booking', strtolower($accept->body));
        $still = app(RentalAvailabilityService::class)->assess(
            Product::first(),
            2,
            app(RentalAvailabilityService::class)->resolveRange(['event_date' => 'saturday'])['start'],
            app(RentalAvailabilityService::class)->resolveRange(['event_date' => 'saturday'])['end']
        );
        $this->assertTrue($still['available']);
        $this->assertEquals(4, (float) $still['available_qty']);
    }

    public function test_large_quote_is_not_auto_sent()
    {
        config(['assistant.rental_auto_quote_max' => 1000]);
        $this->speaker(2);
        $this->postWebhook($this->incomingText('+237675400005', 'Do you have 1 JBL speaker available Saturday?', 'P4L1'))->assertStatus(200);
        $this->postWebhook($this->incomingText('+237675400005', 'Send me a quotation', 'P4L2'))->assertStatus(200);
        $quote = Quotation::orderByDesc('id')->first();
        $this->assertSame(Quotation::STATUS_PENDING, (int) $quote->quotation_status);
        $reply = WhatsAppMessage::where('sender_type', 'ASSISTANT')->orderByDesc('id')->first();
        $this->assertStringContainsString('staff must approve', strtolower($reply->body));
        $this->assertSame(0, WhatsAppMessage::where('type', 'DOCUMENT')->count());
    }

    public function test_ambiguous_date_is_not_guessed()
    {
        $this->speaker(2);
        $this->postWebhook($this->incomingText('+237675400006', 'I need sound this weekend', 'P4D1'))->assertStatus(200);
        $out = WhatsAppMessage::where('sender_type', 'ASSISTANT')->first();
        $this->assertStringContainsString('exact date', strtolower($out->body));
        $this->assertSame(0, Quotation::count());
    }

    public function test_discount_does_not_change_erp_price()
    {
        $this->speaker(2);
        $this->postWebhook($this->incomingText('+237675400007', 'Do you have 1 JBL speaker available Saturday?', 'P4D2'))->assertStatus(200);
        $this->postWebhook($this->incomingText('+237675400007', 'Send me a quotation', 'P4D3'))->assertStatus(200);
        $before = (float) Quotation::first()->grand_total;
        $this->postWebhook($this->incomingText('+237675400007', 'Give me 20% discount', 'P4D4'))->assertStatus(200);
        $this->assertEquals($before, (float) Quotation::first()->grand_total);
        $out = WhatsAppMessage::where('sender_type', 'ASSISTANT')->orderByDesc('id')->first();
        $this->assertStringContainsString('cannot change erp prices', strtolower($out->body));
    }

    public function test_revision_creates_a_new_quotation()
    {
        $this->speaker(10);
        $this->postWebhook($this->incomingText('+237675400009', 'Do you have 2 JBL speakers available Saturday?', 'P4V1'))->assertStatus(200);
        $this->postWebhook($this->incomingText('+237675400009', 'Send me a quotation', 'P4V2'))->assertStatus(200);
        $original = Quotation::first();
        $this->postWebhook($this->incomingText('+237675400009', 'I need 8 speakers instead of 2', 'P4V3'))->assertStatus(200);
        $this->assertSame(2, Quotation::count());
        $this->assertEquals(50000, (float) $original->fresh()->grand_total);
        $this->assertEquals(200000, (float) Quotation::orderByDesc('id')->first()->grand_total);
    }

    public function test_same_phone_does_not_create_a_second_customer()
    {
        $this->speaker(3);
        $this->postWebhook($this->incomingText('+237675400008', 'Do you have 1 JBL speaker available Saturday?', 'P4C1'))->assertStatus(200);
        $this->postWebhook($this->incomingText('+237675400008', 'Send me a quotation', 'P4C2'))->assertStatus(200);
        $this->postWebhook($this->incomingText('+237675400008', 'Send me a quotation', 'P4C3'))->assertStatus(200);
        $this->assertSame(1, \App\Customer::count());
    }

    public function test_ordinal_date_creates_a_rental_request_and_draft_quotation()
    {
        $this->speaker(4);
        $this->postWebhook($this->incomingText('+237675400010', 'I need sound for a wedding', 'P4O1'))->assertStatus(200);
        $this->postWebhook($this->incomingText('+237675400010', '20th November', 'P4O2'))->assertStatus(200);
        $dateReply = WhatsAppMessage::where('sender_type', 'ASSISTANT')->orderByDesc('id')->first();
        $this->assertStringNotContainsString('team member will continue', strtolower($dateReply->body));
        $this->postWebhook($this->incomingText('+237675400010', 'Send me a quotation', 'P4O3'))->assertStatus(200);
        $quote = Quotation::orderByDesc('id')->first();
        $this->assertNotNull($quote);
        $this->assertSame(Quotation::STATUS_PENDING, (int) $quote->quotation_status);
        $this->assertSame(0, Booking::count());
        $this->assertSame(0, WhatsAppMessage::where('type', 'DOCUMENT')->count());
        $request = \App\WhatsApp\RentalRequest::first();
        $this->assertNotNull($request);
        $this->assertSame('2026-11-20', $request->event_date->toDateString());
        $this->postWebhook($this->incomingText('+237675400011', 'I need sound for a wedding', 'P4O4'))->assertStatus(200);
        $this->postWebhook($this->incomingText('+237675400011', 'October 24th in Douala, around 500 people', 'P4O5'))->assertStatus(200);
        $follow = WhatsAppMessage::where('sender_type', 'ASSISTANT')->orderByDesc('id')->first();
        $this->assertStringNotContainsString('team member will continue', strtolower($follow->body));
        $second = \App\WhatsApp\RentalRequest::orderByDesc('id')->first();
        $this->assertSame('2026-10-24', $second->event_date->toDateString());
        $this->assertSame('Douala', $second->location);
        $this->assertSame(500, (int) $second->attendance);
    }

    public function test_quotation_uses_sale_price_when_there_is_no_daily_rate()
    {
        Product::create([
            'name' => 'JBL Charge 5 Bluetooth Speaker',
            'code' => 'JBLC5',
            'is_active' => true,
            'qty' => 5,
            'price' => 85000,
            'rent_price_per_day' => 0,
        ]);
        $this->postWebhook($this->incomingText('+237675400012', 'Do you have a JBL Charge 5 available Saturday?', 'P4S1'))->assertStatus(200);
        $this->postWebhook($this->incomingText('+237675400012', 'Send me a quotation', 'P4S2'))->assertStatus(200);
        $quote = Quotation::orderByDesc('id')->first();
        $this->assertNotNull($quote);
        $this->assertSame(Quotation::STATUS_PENDING, (int) $quote->quotation_status);
        $this->assertEquals(85000, (float) $quote->grand_total);
        $reply = WhatsAppMessage::where('sender_type', 'ASSISTANT')->orderByDesc('id')->first();
        $this->assertStringNotContainsString('daily rental', strtolower($reply->body));
        $this->assertStringNotContainsString('further help', strtolower($reply->body));
        $this->assertSame(0, Booking::count());
        $this->postWebhook($this->incomingText('+237675400012', 'send the quotation', 'P4S3'))->assertStatus(200);
        $again = WhatsAppMessage::where('sender_type', 'ASSISTANT')->orderByDesc('id')->first();
        $this->assertStringNotContainsString('further help', strtolower($again->body));
        $this->assertStringContainsString('quotation', strtolower($again->body));
    }

    protected function speaker($qty)
    {
        return Product::create([
            'name' => 'JBL Speaker',
            'code' => 'JBL1',
            'is_active' => true,
            'qty' => $qty,
            'price' => 25000,
            'rent_price_per_day' => 25000,
        ]);
    }

    protected function rentalTables()
    {
        if (! Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name')->nullable();
                $table->string('code')->nullable();
                $table->boolean('is_active')->default(true);
                $table->decimal('qty', 12, 2)->default(0);
                $table->decimal('price', 12, 2)->nullable();
                $table->decimal('rent_price_per_day', 12, 2)->nullable();
                $table->decimal('rent_price_per_hour', 12, 2)->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('bookings')) {
            Schema::create('bookings', function (Blueprint $table) {
                $table->increments('id');
                $table->string('reference_no')->nullable();
                $table->unsignedInteger('user_id')->nullable();
                $table->unsignedInteger('customer_id')->nullable();
                $table->integer('item')->nullable();
                $table->decimal('total_qty', 12, 2)->nullable();
                $table->decimal('total_price', 12, 2)->nullable();
                $table->decimal('grand_total', 12, 2)->nullable();
                $table->integer('booking_status')->nullable();
                $table->integer('payment_status')->nullable();
                $table->text('booking_note')->nullable();
                $table->text('staff_note')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('booking_products')) {
            Schema::create('booking_products', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('booking_id');
                $table->unsignedInteger('product_id');
                $table->decimal('qty', 12, 2)->default(1);
                $table->decimal('net_unit_price', 12, 2)->nullable();
                $table->decimal('total', 12, 2)->nullable();
                $table->dateTime('start')->nullable();
                $table->dateTime('end')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('quotations')) {
            Schema::create('quotations', function (Blueprint $table) {
                $table->increments('id');
                $table->string('reference_no')->nullable();
                $table->unsignedInteger('user_id')->nullable();
                $table->unsignedInteger('customer_id')->nullable();
                $table->integer('item')->nullable();
                $table->decimal('total_qty', 12, 2)->nullable();
                $table->decimal('total_discount', 12, 2)->nullable();
                $table->decimal('total_tax', 12, 2)->nullable();
                $table->decimal('total_price', 12, 2)->nullable();
                $table->decimal('order_tax_rate', 12, 2)->nullable();
                $table->decimal('order_tax', 12, 2)->nullable();
                $table->decimal('order_discount', 12, 2)->nullable();
                $table->decimal('shipping_cost', 12, 2)->nullable();
                $table->decimal('grand_total', 12, 2)->nullable();
                $table->integer('quotation_status')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('product_quotation')) {
            Schema::create('product_quotation', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('quotation_id');
                $table->unsignedInteger('product_id');
                $table->unsignedInteger('sale_unit_id')->nullable();
                $table->unsignedInteger('variant_id')->nullable();
                $table->decimal('qty', 12, 2)->default(1);
                $table->decimal('net_unit_price', 12, 2)->nullable();
                $table->decimal('discount', 12, 2)->nullable();
                $table->decimal('tax_rate', 12, 2)->nullable();
                $table->decimal('tax', 12, 2)->nullable();
                $table->decimal('total', 12, 2)->nullable();
                $table->timestamps();
            });
        }
    }

    protected function incomingText($phone, $body, $id)
    {
        $digits = preg_replace('/\D/', '', $phone);

        return [
            'event' => 'messages.received',
            'timestamp' => time(),
            'data' => [
                'messages' => [
                    'key' => [
                        'id' => $id,
                        'fromMe' => false,
                        'remoteJid' => $digits.'@s.whatsapp.net',
                        'cleanedSenderPn' => $digits,
                    ],
                    'messageBody' => $body,
                    'message' => ['conversation' => $body],
                ],
            ],
        ];
    }

    protected function fakeProvider()
    {
        return new class implements WhatsAppProviderInterface {
            public function sendText($phone, $message)
            {
                return ['success' => true, 'msg_id' => 'P4T'];
            }

            public function sendDocument($phone, $localPath, $fileName = null, $caption = null)
            {
                return ['success' => true, 'msg_id' => 'P4D'];
            }

            public function sendImage($phone, $localPath, $caption = null)
            {
                return ['success' => true];
            }

            public function sessionStatus()
            {
                return ['connected' => true, 'status' => 'CONNECTED', 'session_name' => 'test', 'configured' => true];
            }

            public function isConfigured()
            {
                return true;
            }

            public function listGroups()
            {
                return ['success' => true, 'groups' => []];
            }

            public function sendGroupText($groupJid, $message)
            {
                return ['success' => true, 'msg_id' => 'G'.uniqid()];
            }
        };
    }
}

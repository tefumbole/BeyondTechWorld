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

        $this->postWebhook($this->incomingText('+237675400004', 'I confirm the quotation', 'P4Q3'))->assertStatus(200);
        $booking = Booking::orderByDesc('id')->first();
        $this->assertNotNull($booking);
        $this->assertSame(5, (int) $booking->booking_status);
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
        $this->assertStringContainsString('automatic send limit', strtolower($reply->body));
        $this->assertSame(0, WhatsAppMessage::where('type', 'DOCUMENT')->count());
    }

    protected function speaker($qty)
    {
        return Product::create([
            'name' => 'JBL Speaker',
            'code' => 'JBL1',
            'is_active' => true,
            'qty' => $qty,
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
        };
    }
}

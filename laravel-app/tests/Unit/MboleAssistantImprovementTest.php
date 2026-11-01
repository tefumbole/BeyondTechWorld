<?php

namespace Tests\Unit;

use App\PaymentAttempt;
use App\Services\Assistant\AssistantHandoverService;
use App\Services\Assistant\AssistantLanguage;
use App\Services\Sales\CheckoutConflictException;
use App\Services\Sales\OrderSubmissionService;
use App\Services\Sales\PaymentAttemptService;
use App\Services\WhatsApp\WhatsAppAudioTranscriber;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MboleAssistantImprovementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('payment_attempts');
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->default(1);
            $table->string('checkout_key', 80);
            $table->unsignedInteger('sale_id')->nullable();
            $table->string('provider', 32)->default('campay');
            $table->string('provider_reference', 191)->nullable();
            $table->string('external_reference', 64);
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('status', 20)->default('pending');
            $table->char('payload_hash', 64)->nullable();
            $table->text('request_payload')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['cloud_tenant_id', 'checkout_key']);
            $table->unique('external_reference');
        });
    }

    public function test_language_follows_the_latest_substantive_message()
    {
        $language = new AssistantLanguage();

        $english = $language->follow('What is the price of a speaker?', 'French');
        $french = $language->follow('Quel est le prix de la sonorisation ?', $english['language']);
        $arabic = $language->follow('كم سعر الجهاز؟', $french['language']);
        $neutral = $language->follow('OK', $arabic['language']);
        $number = $language->follow('2', $arabic['language']);

        $this->assertSame('English', $english['language']);
        $this->assertSame('French', $french['language']);
        $this->assertSame('Arabic', $arabic['language']);
        $this->assertSame('Arabic', $neutral['language']);
        $this->assertSame('Arabic', $number['language']);
    }

    public function test_voice_note_secrets_are_removed_and_size_is_capped()
    {
        $transcriber = new WhatsAppAudioTranscriber();
        $this->assertTrue($transcriber->acceptedMime('audio/ogg; codecs=opus'));
        $this->assertFalse($transcriber->acceptedMime('application/pdf'));
        $this->assertFalse($transcriber->withinSize(9000000));
        $safe = $transcriber->forAssistant([
            'url' => 'https://mmg.whatsapp.net/encrypted-audio',
            'media_key' => 'secret-media-key',
            'file_sha256' => 'sha',
            'mimetype' => 'audio/ogg',
            'ptt' => true,
        ]);
        $this->assertArrayNotHasKey('media_key', $safe);
        $this->assertArrayNotHasKey('url', $safe);
        $this->assertArrayNotHasKey('file_sha256', $safe);
        $this->assertTrue($safe['ptt']);
    }

    public function test_handover_redacts_secrets()
    {
        $handover = app(AssistantHandoverService::class);
        $clean = $handover->redact('My OTP: 123456 and card 4111 1111 1111 1111');

        $this->assertStringNotContainsString('4111', $clean);
        $this->assertStringContainsString('[redacted]', $clean);
    }

    public function test_a_pending_payment_attempt_is_reused()
    {
        $attempts = app(PaymentAttemptService::class);
        $hash = hash('sha256', 'same-cart');
        $first = $attempts->claim('pos-checkout-key-1', $hash, 1500, ['phone' => '237600000000']);
        $second = $attempts->claim('pos-checkout-key-1', $hash, 1500, ['phone' => '237600000000']);

        $this->assertFalse($first['reuse']);
        $this->assertTrue($second['reuse']);
        $this->assertSame(1, PaymentAttempt::where('checkout_key', 'pos-checkout-key-1')->count());
        $this->assertSame($first['attempt']->id, $second['attempt']->id);
    }

    public function test_a_different_cart_on_the_same_checkout_is_rejected()
    {
        $attempts = app(PaymentAttemptService::class);
        $attempts->claim('pos-checkout-key-2', hash('sha256', 'cart-a'), 1500, ['phone' => '237600000000']);

        $this->expectException(CheckoutConflictException::class);
        $attempts->claim('pos-checkout-key-2', hash('sha256', 'cart-b'), 2000, ['phone' => '237600000000']);
    }

    public function test_event_pay_includes_food_money_per_day()
    {
        $pay = new \App\Services\EventWorkforceService();

        $this->assertSame(15000, $pay->calculateExpectedTotal('daily', 5000, 3, []));
        $this->assertSame(18000, $pay->calculateExpectedTotal('daily', 5000, 3, [], 1000));
        $this->assertSame(14000, $pay->calculateExpectedTotal('daily', 4000, 2, [], 3000));
    }

    public function test_checkout_keys_are_validated()
    {
        $orders = app(OrderSubmissionService::class);

        $this->assertTrue($orders->validKey('pos-abc12345'));
        $this->assertFalse($orders->validKey('short'));
        $this->assertFalse($orders->validKey('has space!!'));
    }
}

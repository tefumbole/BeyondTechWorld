<?php

namespace Tests\Feature;

use App\Assistant\AssistantActivity;
use App\Assistant\AssistantMemory;
use App\Contracts\Ai\AiProviderInterface;
use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\Customer;
use App\Employee;
use App\InternshipEnrolment;
use App\Services\Assistant\AssistantIntentRouter;
use App\Services\Assistant\AssistantToolExecutor;
use App\Services\Assistant\Providers\NullAiProvider;
use App\Services\WhatsApp\DocumentAuthorizationService;
use App\Services\WhatsApp\WhatsAppDocumentFileService;
use App\Services\WhatsApp\WhatsAppDocumentRegistry;
use App\Services\WhatsApp\WhatsAppDocumentService;
use App\Services\WhatsApp\WhatsAppHubQuery;
use App\Services\WhatsApp\WhatsAppVerificationService;
use App\User;
use App\WhatsApp\WhatsAppDocumentRequest;
use App\WhatsApp\WhatsAppMessage;
use App\WhatsApp\WhatsAppSetting;
use App\WhatsApp\WhatsAppVerificationChallenge;
use App\WhatsApp\WhatsAppVerificationSession;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\WhatsAppHubTestCase;

class WhatsAppPhase7Test extends WhatsAppHubTestCase
{
    protected $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = $this->fakeProvider();
        $this->app->instance(WhatsAppProviderInterface::class, $this->provider);
        $this->app->instance(AiProviderInterface::class, new NullAiProvider());
        config([
            'services.whatsapp.document_use_fixtures' => true,
            'services.whatsapp.document_fail_generation' => false,
            'services.whatsapp.otp_ttl_minutes' => 5,
            'services.whatsapp.otp_max_attempts' => 3,
            'services.whatsapp.otp_resend_cooldown_seconds' => 60,
            'services.whatsapp.otp_hourly_limit' => 5,
            'services.whatsapp.otp_daily_limit' => 10,
            'services.whatsapp.verification_session_minutes' => 20,
        ]);
        $this->extraTables();
        Carbon::setTestNow(Carbon::parse('2026-09-23 11:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_registry_lists_only_real_generators()
    {
        $registry = app(WhatsAppDocumentRegistry::class);
        $this->assertTrue($registry->get('CUSTOMER_QUOTATION')['available']);
        $this->assertTrue($registry->get('CUSTOMER_INVOICE')['available']);
        foreach (['CUSTOMER_RECEIPT', 'CUSTOMER_CONTRACT', 'EMPLOYEE_PAYSLIP', 'EMPLOYEE_CONTRACT', 'EMPLOYEE_TIMESHEET', 'EMPLOYEE_MISSION_ORDER', 'INTERNSHIP_LETTER', 'INTERNSHIP_ASSESSMENT', 'INTERNSHIP_CERTIFICATE'] as $key) {
            $this->assertFalse($registry->get($key)['available'], $key);
        }
        $labels = app(WhatsAppDocumentService::class)->listFor(['roles' => ['customer']]);
        $this->assertEquals(['Quotation', 'Invoice', 'Bill receipt'], array_column($labels, 'label'));
        $this->assertSame([], app(WhatsAppDocumentService::class)->listFor(['roles' => ['employee']]));
    }

    public function test_customer_quotation_requires_otp_then_sends_existing_file()
    {
        $this->enableAssistant();
        $customer = $this->customer('675700001');
        $this->quotation($customer->id, 'QT-1');
        $this->postWebhook($this->incomingText('+237675700001', 'Send my quotation.', 'P7A'))->assertStatus(200);
        $this->assertSame(1, WhatsAppVerificationChallenge::count());
        $code = $this->capturedCode();
        $this->assertNotSame($code, WhatsAppVerificationChallenge::first()->otp_hash);
        $this->assertFalse(strpos(WhatsAppMessage::where('sender_type', 'ASSISTANT')->orderByDesc('id')->first()->body, $code) !== false);
        $preview = AssistantActivity::orderByDesc('id')->first()->response_preview;
        $this->assertFalse(strpos((string) $preview, $code) !== false);
        $this->postWebhook($this->incomingText('+237675700001', $code, 'P7A2'))->assertStatus(200);
        $this->assertSame(WhatsAppDocumentRequest::SENT, WhatsAppDocumentRequest::where('document_type', 'CUSTOMER_QUOTATION')->orderByDesc('id')->first()->status);
        $this->assertSame(1, count($this->provider->documents));
        $this->assertSame(1, WhatsAppVerificationSession::count());
    }

    public function test_wrong_customer_quotation_is_denied_without_detail()
    {
        $owner = $this->customer('675700002');
        $other = $this->customer('675700003');
        $quote = $this->quotation($other->id, 'QT-OTHER');
        $result = $this->docs()->handle($this->context($owner), 'Send quotation '.$quote, [], 'm-wrong');
        $this->assertSame("I couldn't provide that document for this account.", $result['message']);
        $this->assertFalse(strpos($result['message'], 'another') !== false);
        $this->assertSame('wrong_owner', WhatsAppDocumentRequest::first()->failure_code);
        $this->assertSame([], $this->provider->documents);
        $this->assertSame(0, WhatsAppVerificationChallenge::count());
    }

    public function test_invoice_ownership_and_session_reuse_then_expiry()
    {
        $customer = $this->customer('675700004');
        $this->quotation($customer->id, 'QT-4');
        $this->sale($customer->id, 'INV-4');
        $context = $this->context($customer);
        $first = $this->docs()->handle($context, 'Send my quotation.', [], 'm-inv-1');
        $this->docs()->handle($context, $first['otp_code'], ['verification_pending' => 1], 'm-inv-2');
        $before = WhatsAppVerificationChallenge::count();
        $second = $this->docs()->handle($context, 'Send my invoice.', [], 'm-inv-3');
        $this->assertSame($before, WhatsAppVerificationChallenge::count());
        $this->assertSame(WhatsAppDocumentRequest::SENT, WhatsAppDocumentRequest::where('document_type', 'CUSTOMER_INVOICE')->first()->status);
        $this->assertArrayNotHasKey('otp_code', $second);
        $session = WhatsAppVerificationSession::first();
        $session->expires_at = Carbon::now()->subMinute();
        $session->save();
        $again = $this->docs()->handle($context, 'Send my latest quotation.', [], 'm-inv-4');
        $this->assertArrayHasKey('otp_code', $again);
        $this->assertSame($before + 1, WhatsAppVerificationChallenge::count());
    }

    public function test_payment_claim_does_not_create_a_receipt()
    {
        $customer = $this->customer('675700005');
        $result = $this->docs()->handle($this->context($customer), 'I paid 500000. Send my receipt.', [], 'm-pay');
        $this->assertFalse(strpos($result['message'], '500000') !== false);
        $this->assertSame(0, DB::table('payments')->count());
        $this->assertSame([], $this->provider->documents);
        $this->sale($customer->id, 'INV-PAID');
        DB::table('payments')->insert(['sale_id' => 1, 'amount' => 100, 'created_at' => now(), 'updated_at' => now()]);
        $recorded = $this->docs()->handle($this->context($customer), 'Send my receipt.', [], 'm-pay-2');
        $this->assertSame('A payment is recorded, but there is no receipt file to send.', $recorded['message']);
        $this->assertSame(1, DB::table('payments')->count());
    }

    public function test_payslip_contract_and_certificate_are_not_fabricated()
    {
        $person = $this->employeePerson('675700006');
        InternshipEnrolment::create(['student_user_id' => $person['user']->id, 'status' => 'active']);
        $context = [
            'roles' => ['employee', 'intern'],
            'employee_id' => $person['employee']->id,
            'intern_user_id' => $person['user']->id,
            'contact_id' => 9,
            'conversation_id' => 9,
        ];
        $payslip = $this->docs()->handle($context, 'Send my latest payslip.', [], 'm-pay-slip');
        $this->assertSame('Payslips are not available on WhatsApp yet.', $payslip['message']);
        $this->assertFalse(strpos($payslip['message'], '650') !== false);
        $contract = $this->docs()->handle($context, 'Send my employment contract.', [], 'm-contract');
        $this->assertSame('That document is not available on WhatsApp.', $contract['message']);
        $certificate = $this->docs()->handle($context, 'Send my certificate.', [], 'm-cert');
        $this->assertSame('That certificate is not currently available.', $certificate['message']);
        $this->assertSame([], $this->provider->documents);
        $this->assertSame(0, WhatsAppVerificationChallenge::count());
    }

    public function test_unknown_number_gets_no_otp()
    {
        $this->enableAssistant();
        $this->postWebhook($this->incomingText('+237675700099', 'Send my payslip.', 'P7UNK'))->assertStatus(200);
        $this->assertSame(0, WhatsAppVerificationChallenge::count());
        $this->assertSame(0, WhatsAppDocumentRequest::count());
        $this->assertSame([], $this->provider->documents);
    }

    public function test_otp_hash_expiry_attempts_replay_and_cooldown()
    {
        $otp = app(WhatsAppVerificationService::class);
        $issued = $otp->issue(4, 4, 'customer', 4, 'CUSTOMER_DOCUMENTS', 'otp-1');
        $this->assertSame(64, strlen($otp->hash($issued['code'])));
        $this->assertNotSame($issued['code'], WhatsAppVerificationChallenge::first()->otp_hash);
        $this->assertNotRegExp('/^[0-9]{6}$/', WhatsAppVerificationChallenge::first()->otp_hash);
        $wrong = $otp->verify(4, '000000' === $issued['code'] ? '111111' : '000000');
        $this->assertSame('mismatch', $wrong['error']);
        $this->assertSame(1, (int) WhatsAppVerificationChallenge::first()->attempts);
        $row = WhatsAppVerificationChallenge::first();
        $row->expires_at = Carbon::now()->subMinute();
        $row->save();
        $this->assertSame('expired', $otp->verify(4, $issued['code'])['error']);
        WhatsAppVerificationChallenge::query()->delete();
        $fresh = $otp->issue(4, 4, 'customer', 4, 'CUSTOMER_DOCUMENTS', 'otp-2');
        $otp->verify(4, '111111' === $fresh['code'] ? '222222' : '111111');
        $otp->verify(4, '333333' === $fresh['code'] ? '444444' : '333333');
        $locked = $otp->verify(4, '555555' === $fresh['code'] ? '666666' : '555555');
        $this->assertSame('locked', $locked['error']);
        $this->assertNotNull(WhatsAppVerificationChallenge::orderByDesc('id')->first()->invalidated_at);
        WhatsAppVerificationChallenge::query()->delete();
        WhatsAppVerificationSession::query()->delete();
        $ok = $otp->issue(5, 5, 'customer', 5, 'CUSTOMER_DOCUMENTS', 'otp-3');
        $again = $otp->issue(5, 5, 'customer', 5, 'CUSTOMER_DOCUMENTS', 'otp-4');
        $this->assertSame('cooldown', $again['error']);
        $this->assertTrue($otp->verify(5, $ok['code'])['ok']);
        $this->assertSame('replay', $otp->verify(5, $ok['code'])['error']);
        $this->assertSame(1, WhatsAppVerificationSession::count());
        Carbon::setTestNow(Carbon::parse('2026-09-23 11:02:00'));
        $rotated = $otp->issue(5, 5, 'customer', 5, 'CUSTOMER_DOCUMENTS', 'otp-5');
        $this->assertTrue($rotated['ok']);
        $this->assertNotSame($ok['code'], $rotated['code']);
        $this->assertSame('mismatch', $otp->verify(5, $ok['code'])['error']);
        $this->assertTrue($otp->verify(5, $rotated['code'])['ok']);
        $this->assertSame(2, WhatsAppVerificationSession::count());
    }

    public function test_otp_rate_limit_and_path_jail()
    {
        config(['services.whatsapp.otp_hourly_limit' => 2, 'services.whatsapp.otp_resend_cooldown_seconds' => 10]);
        $otp = app(WhatsAppVerificationService::class);
        $otp->issue(8, 8, 'customer', 8, 'CUSTOMER_DOCUMENTS');
        Carbon::setTestNow(Carbon::parse('2026-09-23 11:01:00'));
        $otp->issue(8, 8, 'customer', 8, 'CUSTOMER_DOCUMENTS');
        Carbon::setTestNow(Carbon::parse('2026-09-23 11:02:00'));
        $this->assertSame('rate_limited', $otp->issue(8, 8, 'customer', 8, 'CUSTOMER_DOCUMENTS')['error']);
        $files = app(WhatsAppDocumentFileService::class);
        $this->assertFalse($files->assertSafe('../../etc/passwd'));
        $this->assertFalse($files->assertSafe('/etc/hosts'));
        $this->assertFalse($files->assertSafe('http://example.test/file.pdf'));
        $safe = $files->quotation(3);
        $this->assertNotFalse($files->assertSafe($safe));
        $denied = $this->docs()->handle($this->context($this->customer('675700010')), 'Send invoice ../../etc/passwd', [], 'm-path');
        $this->assertSame('path_rejected', WhatsAppDocumentRequest::first()->failure_code);
        $this->assertSame([], $this->provider->documents);
        $this->assertSame("I couldn't provide that document for this account.", $denied['message']);
    }

    public function test_generation_and_send_failure_do_not_claim_sent()
    {
        $customer = $this->customer('675700011');
        $this->quotation($customer->id, 'QT-FAIL');
        config(['services.whatsapp.document_fail_generation' => true]);
        $issued = $this->docs()->handle($this->context($customer), 'Send my quotation.', [], 'm-gen');
        $failed = $this->docs()->handle($this->context($customer), $issued['otp_code'], ['verification_pending' => 1], 'm-gen-2');
        $this->assertSame('generation_failed', WhatsAppDocumentRequest::orderByDesc('id')->first()->failure_code);
        $this->assertFalse(strpos($failed['message'], 'I sent') !== false);
        config(['services.whatsapp.document_fail_generation' => false]);
        WhatsAppVerificationSession::query()->delete();
        $this->provider->failSend = true;
        $issued = $this->docs()->handle($this->context($customer), 'Send my last quotation.', [], 'm-send');
        $sendFailed = $this->docs()->handle($this->context($customer), $issued['otp_code'], ['verification_pending' => 1], 'm-send-2');
        $request = WhatsAppDocumentRequest::orderByDesc('id')->first();
        $this->assertSame(WhatsAppDocumentRequest::FAILED, $request->status);
        $this->assertSame('send_failed', $request->failure_code);
        $this->assertFalse(strpos($sendFailed['message'], 'I sent') !== false);
        $this->provider->failSend = false;
        $this->assertTrue($this->docs()->retrySend($request->fresh())['success']);
        $this->assertSame(WhatsAppDocumentRequest::SENT, $request->fresh()->status);
    }

    public function test_duplicate_webhook_does_not_duplicate_otp()
    {
        $this->enableAssistant();
        $customer = $this->customer('675700012');
        $this->quotation($customer->id, 'QT-DUP');
        $payload = $this->incomingText('+237675700012', 'Send my quotation.', 'P7DUP');
        $payload['timestamp'] = 1790007001;
        $this->postWebhook($payload)->assertStatus(200);
        $this->postWebhook($payload)->assertStatus(200)->assertJson(['duplicate' => true]);
        $this->assertSame(1, WhatsAppVerificationChallenge::count());
        $this->assertSame(1, WhatsAppDocumentRequest::count());
    }

    public function test_checkout_and_human_win_over_pending_otp()
    {
        $router = app(AssistantIntentRouter::class);
        $memory = ['verification_pending' => 1, 'document_context' => 'employee'];
        $this->assertSame('ATTENDANCE_OUT', $router->deterministic('CHECK OUT', $memory)['intent']);
        $this->assertSame('HUMAN_REQUEST', $router->deterministic('I need a human', $memory)['intent']);
        $this->assertSame('VERIFY_OTP', $router->deterministic('482913', $memory)['intent']);
        $this->assertSame('VERIFY_OTP', $router->deterministic('My code is 482913', $memory)['intent']);
        $plain = $router->deterministic('482913', []);
        $this->assertTrue($plain === null || (isset($plain['intent']) && $plain['intent'] !== 'VERIFY_OTP'));
        $this->assertSame('RENTAL_QUOTE', $router->deterministic('Send a quotation for 1 JBL Charge 5')['intent']);
        $this->assertSame('DOCUMENT_REQUEST', $router->deterministic('Send my quotation.')['intent']);

        $this->enableAssistant();
        $person = $this->employeePerson('675700013');
        $this->postWebhook($this->incomingText('+237675700013', 'Send my payslip.', 'P7CO1'))->assertStatus(200);
        $memoryRow = AssistantMemory::first();
        $params = $memoryRow->parameters();
        $params['verification_pending'] = 1;
        $params['document_context'] = 'employee';
        $memoryRow->setParameters($params);
        $memoryRow->save();
        $this->postWebhook($this->incomingText('+237675700013', 'CHECK OUT', 'P7CO2'))->assertStatus(200);
        $reply = WhatsAppMessage::where('sender_type', 'ASSISTANT')->orderByDesc('id')->first()->body;
        $this->assertFalse(strpos($reply, 'code is not valid') !== false);
        $this->assertSame('employee', AssistantMemory::first()->parameters()['document_context']);
    }

    public function test_multi_role_choice_survives_otp()
    {
        $this->enableAssistant();
        $person = $this->employeePerson('675700014');
        InternshipEnrolment::create(['student_user_id' => $person['user']->id, 'status' => 'active']);
        $this->postWebhook($this->incomingText('+237675700014', 'Send my document.', 'P7ROLE1'))->assertStatus(200);
        $this->assertStringContainsString('employment document', $this->latestAssistant());
        $this->postWebhook($this->incomingText('+237675700014', 'employee', 'P7ROLE2'))->assertStatus(200);
        $this->assertSame('employee', AssistantMemory::first()->parameters()['document_context']);
        $this->assertStringContainsString('employment document', $this->latestAssistant());
        $issued = app(WhatsAppVerificationService::class)->issue(
            \App\WhatsApp\WhatsAppContact::orderByDesc('id')->first()->id,
            \App\WhatsApp\WhatsAppConversation::orderByDesc('id')->first()->id,
            'employee',
            $person['employee']->id,
            'EMPLOYEE_DOCUMENTS',
            'P7ROLE-OTP'
        );
        $params = AssistantMemory::first()->parameters();
        $params['verification_pending'] = 1;
        $params['document_context'] = 'employee';
        $memory = AssistantMemory::first();
        $memory->setParameters($params);
        $memory->save();
        $this->postWebhook($this->incomingText('+237675700014', $issued['code'], 'P7ROLE3'))->assertStatus(200);
        $kept = AssistantMemory::first()->parameters();
        $this->assertSame('employee', $kept['document_context']);
        $this->assertStringNotContainsString('Do you mean', $this->latestAssistant());
        $session = WhatsAppVerificationSession::orderByDesc('id')->first();
        $this->assertSame('employee', $session->identity_type);
        $this->assertEquals($person['employee']->id, $session->identity_id);
    }

    public function test_verified_session_does_not_authorize_another_owner()
    {
        $a = $this->customer('675700015');
        $b = $this->customer('675700016');
        $this->quotation($a->id, 'QT-A');
        $foreign = $this->quotation($b->id, 'QT-B');
        $context = $this->context($a);
        $issued = $this->docs()->handle($context, 'Send my quotation.', [], 'm-own-1');
        $this->docs()->handle($context, $issued['otp_code'], ['verification_pending' => 1], 'm-own-2');
        $denied = $this->docs()->handle($context, 'Send quotation '.$foreign, [], 'm-own-3');
        $this->assertSame("I couldn't provide that document for this account.", $denied['message']);
        $this->assertSame(1, count($this->provider->documents));
        $auth = app(DocumentAuthorizationService::class);
        $definition = app(WhatsAppDocumentRegistry::class)->get('CUSTOMER_QUOTATION');
        $session = WhatsAppVerificationSession::first();
        $this->assertFalse($auth->decide('customer', $a->id, 'customer', $b->id, $definition, $session)['allowed']);
        $this->assertSame('privileged', $auth->decide('customer', $a->id, 'customer', $a->id, ['sensitivity' => 'PRIVILEGED', 'otp' => false, 'scope' => 'CUSTOMER_DOCUMENTS', 'owner' => 'customer'], $session)['code']);
    }

    public function test_permissions_diagnostics_and_privileged_tool()
    {
        $blocked = app(AssistantToolExecutor::class)->execute('bypass_ownership', [], ['roles' => ['employee']]);
        $this->assertSame('privileged', $blocked['error']);
        $diag = app(WhatsAppHubQuery::class)->diagnostics();
        $this->assertArrayHasKey('documents', $diag);
        $this->assertArrayNotHasKey('otp_hash', $diag['documents']);
        $stats = app(WhatsAppHubQuery::class)->commandCenter([
            'from' => Carbon::now()->startOfDay(),
            'to' => Carbon::now()->endOfDay(),
        ]);
        $this->assertArrayHasKey('document_requests_today', $stats);
        $this->assertNotNull(app('router')->getRoutes()->getByName('whatsapp.documents'));
        $limited = Role::find(3);
        $dummy = Permission::firstOrCreate(['name' => 'customers-index', 'guard_name' => 'web']);
        try {
            $limited->givePermissionTo($dummy);
        } catch (\Exception $e) {
        }
        $this->actingAs($this->makeUser(3, '675700017'));
        $this->get('/admin/whatsapp/documents')->assertRedirect();
        $this->assertTrue(session()->has('not_permitted'));
    }

    protected function docs()
    {
        return app(WhatsAppDocumentService::class);
    }

    protected function context(Customer $customer)
    {
        $conversation = $this->conversationFor($customer);

        return [
            'roles' => ['customer'],
            'customer_id' => $customer->id,
            'contact_id' => $conversation->contact_id,
            'conversation_id' => $conversation->id,
            'conversation' => $conversation,
        ];
    }

    protected function conversationFor(Customer $customer)
    {
        $contact = \App\WhatsApp\WhatsAppContact::firstOrCreate(
            ['normalized_phone' => '237'.$customer->phone_number],
            ['display_phone' => $customer->phone_number, 'wa_name' => 'Customer']
        );

        return \App\WhatsApp\WhatsAppConversation::firstOrCreate(
            ['contact_id' => $contact->id],
            ['mode' => 'AI', 'status' => 'OPEN']
        );
    }

    protected function customer($phone)
    {
        return Customer::create(['name' => 'Cust '.$phone, 'phone_number' => $phone, 'is_active' => true]);
    }

    protected function employeePerson($phone)
    {
        $user = User::create([
            'name' => 'Emp '.$phone, 'email' => $phone.'@example.test', 'password' => bcrypt('x'),
            'phone' => $phone, 'role_id' => 1, 'is_active' => true, 'is_deleted' => false,
        ]);
        $employee = Employee::create([
            'name' => 'Emp '.$phone, 'phone_number' => $phone, 'user_id' => $user->id, 'is_active' => true,
        ]);

        return ['user' => $user, 'employee' => $employee];
    }

    protected function quotation($customerId, $reference)
    {
        DB::table('quotations')->insert([
            'customer_id' => $customerId,
            'reference_no' => $reference,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('quotations')->where('reference_no', $reference)->value('id');
    }

    protected function sale($customerId, $reference)
    {
        DB::table('sales')->insert([
            'customer_id' => $customerId,
            'reference_no' => $reference,
            'grand_total' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('sales')->where('reference_no', $reference)->value('id');
    }

    protected function capturedCode()
    {
        $this->assertNotEmpty($this->provider->texts);
        $body = $this->provider->texts[count($this->provider->texts) - 1];
        $this->assertSame(1, preg_match('/\b(\d{6})\b/', $body, $matched));

        return $matched[1];
    }

    protected function enableAssistant()
    {
        config(['assistant.enabled' => true, 'assistant.provider' => 'null']);
        WhatsAppSetting::putValue('assistant_enabled', '1');
        WhatsAppSetting::putValue('default_conversation_mode', 'AI');
    }

    protected function latestAssistant()
    {
        $message = WhatsAppMessage::where('sender_type', 'ASSISTANT')->orderByDesc('id')->first();
        $this->assertNotNull($message);

        return (string) $message->body;
    }

    protected function incomingText($phone, $body, $id)
    {
        $digits = preg_replace('/\D/', '', $phone);

        return [
            'event' => 'messages.received',
            'timestamp' => 1790007100,
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
            public $texts = [];
            public $documents = [];
            public $failSend = false;

            public function sendText($phone, $message)
            {
                $this->texts[] = $message;

                return ['success' => true, 'msg_id' => 'T'.count($this->texts)];
            }

            public function sendDocument($phone, $localPath, $fileName = null, $caption = null)
            {
                $this->documents[] = $localPath;
                if ($this->failSend) {
                    return ['success' => false, 'error' => 'down'];
                }

                return ['success' => true, 'msg_id' => 'D'.count($this->documents)];
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

    protected function extraTables()
    {
        if (! Schema::hasTable('quotations')) {
            Schema::create('quotations', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('customer_id')->nullable();
                $table->string('reference_no')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('sales')) {
            Schema::create('sales', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('customer_id')->nullable();
                $table->string('reference_no')->nullable();
                $table->decimal('grand_total', 12, 2)->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('sale_id')->nullable();
                $table->decimal('amount', 12, 2)->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('internship_enrolments')) {
            Schema::create('internship_enrolments', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('student_user_id')->nullable();
                $table->string('status')->nullable();
                $table->timestamps();
            });
        }
    }
}

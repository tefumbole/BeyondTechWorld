<?php

namespace Tests\Feature;

use App\Attendance;
use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\Customer;
use App\Employee;
use App\Property\BillPaymentRequest;
use App\Property\MaintenanceRequest;
use App\Property\PropertyActivity;
use App\Property\RentObligation;
use App\Property\RentPayment;
use App\Property\Tenancy;
use App\Services\Assistant\AssistantIntentRouter;
use App\Services\Assistant\AssistantPolicyService;
use App\Services\Assistant\AssistantToolExecutor;
use App\Services\Attendance\AttendanceWhatsAppService;
use App\Services\Property\BillPaymentService;
use App\Services\Property\MaintenanceService;
use App\Services\Property\PropertyMetrics;
use App\Services\Property\PropertyWhatsAppService;
use App\Services\Property\RentBillingService;
use App\Services\Property\RentReminderService;
use App\Services\Property\TenancyService;
use App\Services\WhatsApp\WhatsAppDocumentService;
use App\Services\WhatsApp\WhatsAppHubQuery;
use App\Services\WhatsApp\WhatsAppIdentityService;
use App\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\WhatsAppHubTestCase;

class WhatsAppPhase8Test extends WhatsAppHubTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(WhatsAppProviderInterface::class, $this->fakeProvider());
        config([
            'services.whatsapp.document_use_fixtures' => true,
            'services.property.bill_webhook_secret' => 'bill-secret',
            'services.property.service_fee' => 0,
            'services.property.emergency_contact' => '',
            'services.property.reminders_enabled' => false,
        ]);
    }

    public function test_property_unit_and_exclusive_occupancy()
    {
        $first = $this->tenancy('670008001');
        $second = app(TenancyService::class)->open([
            'customer_id' => $this->customer('670008002')->id,
            'unit_id' => $first->unit_id,
            'start_date' => '2026-09-01',
            'rent_amount' => 80000,
            'status' => 'ACTIVE',
        ]);
        $this->assertFalse($second['ok']);
        $this->assertSame('unit_occupied', $second['error']);
        $this->assertSame(1, Tenancy::where('unit_id', $first->unit_id)->where('status', 'ACTIVE')->count());
    }

    public function test_rent_generation_is_idempotent_and_balance_is_authoritative()
    {
        $tenancy = $this->tenancy('670008011', ['due_day' => 23, 'rent_amount' => 150000]);
        $billing = app(RentBillingService::class);
        $this->assertSame(1, $billing->generate('2026-09-23'));
        $this->assertSame(0, $billing->generate('2026-09-23'));
        $this->assertSame(1, RentObligation::where('tenancy_id', $tenancy->id)->count());
        $balance = $billing->balance($tenancy);
        $this->assertSame(150000.0, $balance['amount']);
        $reply = app(PropertyWhatsAppService::class)->handle('get_rent_balance', [], $this->context($tenancy));
        $this->assertStringContainsString('150,000', $reply['message']);
        $due = app(PropertyWhatsAppService::class)->handle('get_rent_due_date', [], $this->context($tenancy));
        $this->assertStringContainsString('2026-09-23', $due['message']);
    }

    public function test_false_payment_claim_does_not_change_balance_or_create_a_receipt()
    {
        $tenancy = $this->tenancy('670008021', ['due_day' => 23, 'rent_amount' => 150000]);
        app(RentBillingService::class)->generate('2026-09-23');
        $before = RentPayment::count();
        $reply = app(PropertyWhatsAppService::class)->handle('record_payment_claim', ['text' => 'I paid my rent'], $this->context($tenancy));
        $this->assertStringContainsString('not proof', $reply['message']);
        $this->assertSame($before, RentPayment::count());
        $this->assertSame(150000.0, app(RentBillingService::class)->balance($tenancy->fresh())['amount']);
        $doc = app(WhatsAppDocumentService::class)->handle($this->context($tenancy), 'send my rent receipt');
        $this->assertStringContainsString('could not find a rent receipt', $doc['message']);
    }

    public function test_payment_history_and_receipt_use_stage7_for_the_owner_only()
    {
        $tenancy = $this->tenancy('670008031', ['due_day' => 23, 'rent_amount' => 150000]);
        $other = $this->tenancy('670008032', ['due_day' => 23, 'rent_amount' => 90000]);
        app(RentBillingService::class)->generate('2026-09-23');
        $paid = app(RentBillingService::class)->recordPayment($tenancy, 150000, 'ERP-1');
        $this->assertTrue($paid['ok']);
        $history = app(PropertyWhatsAppService::class)->handle('get_rent_payment_history', [], $this->context($tenancy));
        $this->assertStringContainsString('150,000', $history['message']);
        $otherHistory = app(PropertyWhatsAppService::class)->handle('get_rent_payment_history', [], $this->context($other));
        $this->assertStringNotContainsString('150,000', $otherHistory['message']);
        $denied = app(WhatsAppDocumentService::class)->handle(
            $this->context($other),
            'send my rent receipt '.$paid['payment']->id
        );
        $this->assertStringContainsString("couldn't provide that document", $denied['message']);
        $this->assertStringNotContainsString('another', strtolower($denied['message']));
        $sent = $this->verifiedDocument($tenancy, 'send my rent receipt');
        $this->assertStringContainsString('rent receipt', $sent['message']);
    }

    public function test_tenancy_agreement_is_not_fabricated()
    {
        $tenancy = $this->tenancy('670008041');
        $missing = app(WhatsAppDocumentService::class)->handle($this->context($tenancy), 'send my tenancy agreement');
        $this->assertStringContainsString('not on file', $missing['message']);
        $dir = storage_path('app/property-agreements');
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $path = $dir.'/agreement-'.$tenancy->id.'.pdf';
        file_put_contents($path, "%PDF-1.4\n");
        $tenancy->agreement_path = $path;
        $tenancy->save();
        $sent = $this->verifiedDocument($tenancy, 'send my rental agreement');
        $this->assertStringContainsString('tenancy agreement', $sent['message']);
    }

    public function test_maintenance_media_ownership_and_field_attendance()
    {
        $tenancy = $this->tenancy('670008051');
        $other = $this->tenancy('670008052');
        $opened = app(PropertyWhatsAppService::class)->handle('create_maintenance_request', [
            'text' => 'Water is leaking in my bathroom',
            'provider_message_id' => 'mm-1',
        ], $this->context($tenancy));
        $this->assertStringContainsString('OPEN', $opened['message']);
        $this->assertStringContainsString('Plumbing', $opened['message']);
        $this->assertStringContainsString('NORMAL', $opened['message']);
        $again = app(PropertyWhatsAppService::class)->handle('create_maintenance_request', [
            'text' => 'Water is leaking in my bathroom',
            'provider_message_id' => 'mm-1',
        ], $this->context($tenancy));
        $this->assertTrue($again['duplicate']);
        $this->assertSame(1, MaintenanceRequest::where('tenancy_id', $tenancy->id)->count());
        $request = MaintenanceRequest::where('tenancy_id', $tenancy->id)->first();
        $photo = app(PropertyWhatsAppService::class)->handle('add_maintenance_attachment', [
            'maintenance_request_id' => $request->id,
            'bytes' => 'fake-image',
            'filename' => 'leak.jpg',
            'mime' => 'image/jpeg',
        ], $this->context($tenancy));
        $this->assertStringContainsString('attached', $photo['message']);
        $voice = app(MaintenanceService::class)->attach($request, 'voice-bytes', 'note.ogg', 'audio/ogg', true);
        $this->assertTrue($voice['ok']);
        $bad = app(MaintenanceService::class)->attach($request, '<?php', 'x.php', 'application/x-php', false);
        $this->assertFalse($bad['ok']);
        $status = app(PropertyWhatsAppService::class)->handle('get_maintenance_status', [
            'text' => 'What is happening with my repair?',
        ], $this->context($tenancy));
        $this->assertStringContainsString('OPEN', $status['message']);
        $denied = app(PropertyWhatsAppService::class)->handle('get_maintenance_status', [
            'maintenance_request_id' => $request->id,
        ], $this->context($other));
        $this->assertStringContainsString("couldn't provide that", $denied['message']);
        $this->assertStringNotContainsString('another', strtolower($denied['message']));
        $employee = Employee::create(['name' => 'Tech', 'phone_number' => '670008059', 'is_active' => true]);
        $assigned = app(MaintenanceService::class)->assign($request, $employee->id);
        $this->assertSame('ASSIGNED', $assigned['request']->status);
        $this->attendanceTable();
        $user = User::create([
            'name' => 'Tech user', 'email' => 'tech@example.test', 'password' => bcrypt('x'),
            'phone' => '670008059', 'role_id' => 1, 'is_active' => true, 'is_deleted' => false,
        ]);
        $attendance = Attendance::create([
            'date' => Carbon::today()->toDateString(),
            'employee_id' => $employee->id,
            'user_id' => $user->id,
            'checkin' => '08:00:00',
            'checkout' => null,
            'status' => 1,
        ]);
        app(MaintenanceService::class)->noteFieldAttendance($request, $attendance->id);
        $checked = app(AttendanceWhatsAppService::class)->checkOut([
            'employee_id' => $employee->id,
            'roles' => ['employee'],
        ], ['text' => 'CHECK OUT']);
        $this->assertTrue($checked['success']);
        $request->refresh();
        $this->assertSame('ASSIGNED', $request->status);
        $this->assertNotNull($request->attendance_id);
    }

    public function test_emergency_uses_configured_instruction_only()
    {
        $tenancy = $this->tenancy('670008061');
        $reply = app(PropertyWhatsAppService::class)->handle('create_maintenance_request', [
            'text' => 'There is a fire in the kitchen',
        ], $this->context($tenancy));
        $this->assertStringContainsString('URGENT', $reply['message']);
        $this->assertStringContainsString('cannot dispatch', $reply['message']);
        $this->assertStringNotContainsString('+237', $reply['message']);
    }

    public function test_bill_request_confirmation_and_image_do_not_debit()
    {
        $tenancy = $this->tenancy('670008071');
        $context = $this->context($tenancy);
        $created = app(PropertyWhatsAppService::class)->handle('create_bill_payment_request', [
            'text' => 'I need help paying my electricity bill',
            'provider_message_id' => 'bill-1',
        ], $context);
        $this->assertStringContainsString('not paid', strtolower($created['message']));
        $request = BillPaymentRequest::find($created['bill_request_id']);
        $this->assertNotSame('PAID', $request->status);
        $again = app(PropertyWhatsAppService::class)->handle('create_bill_payment_request', [
            'text' => 'I need help paying my electricity bill',
            'provider_message_id' => 'bill-1',
        ], $context);
        $this->assertTrue($again['duplicate']);
        $this->assertSame(1, BillPaymentRequest::where('provider_message_id', 'bill-1')->count());
        $image = app(PropertyWhatsAppService::class)->handle('attach_bill_image', [
            'bill_request_id' => $request->id,
            'bytes' => 'image-bytes',
            'filename' => 'bill.png',
            'mime' => 'image/png',
        ], $context);
        $this->assertTrue($image['provisional']);
        $this->assertSame(1, DB::table('bill_payment_attachments')->where('bill_payment_request_id', $request->id)->count());
        app(PropertyWhatsAppService::class)->handle('update_bill_payment_details', [
            'bill_request_id' => $request->id,
            'provider' => 'City Power',
            'account_reference' => 'ACC-100200',
            'amount' => 12000,
        ], $context);
        $confirmed = app(PropertyWhatsAppService::class)->handle('confirm_bill_payment_request', [
            'bill_request_id' => $request->id,
        ], $context);
        $request->refresh();
        $this->assertSame('UNDER_REVIEW', $request->status);
        $this->assertStringContainsString('No payment has been taken', $confirmed['message']);
        $this->assertStringContainsString('Service fee: 0', $confirmed['message']);
        $this->assertFalse($confirmed['debited']);
        $this->assertSame(0, RentPayment::where('tenancy_id', $tenancy->id)->count());
    }

    public function test_provider_success_failure_timeout_and_duplicate_webhook()
    {
        $tenancy = $this->tenancy('670008081');
        $context = $this->context($tenancy);
        $created = app(BillPaymentService::class)->create([
            'customer_id' => $tenancy->customer_id,
            'tenancy_id' => $tenancy->id,
            'text' => 'electricity 5000',
            'provider_message_id' => 'bill-pay-1',
        ]);
        $request = $created['request'];
        app(BillPaymentService::class)->updateDetails($request, [
            'provider' => 'City Power',
            'account_reference' => 'ACC-9',
            'amount' => 5000,
        ]);
        app(BillPaymentService::class)->confirm($request->fresh());
        $failed = app(BillPaymentService::class)->applyProviderResult($request->fresh(), 'FAILED', 'ref-fail', 'evt-fail');
        $this->assertSame('FAILED', $failed['request']->status);
        $receipt = app(WhatsAppDocumentService::class)->handle($context, 'send my bill receipt '.$request->id);
        $this->assertStringNotContainsString('I sent your bill receipt', $receipt['message']);

        $second = app(BillPaymentService::class)->create([
            'customer_id' => $tenancy->customer_id,
            'text' => 'water 4000',
            'provider_message_id' => 'bill-pay-2',
        ]);
        $pending = $second['request'];
        app(BillPaymentService::class)->applyProviderResult($pending, 'TIMEOUT', 'ref-timeout', 'evt-timeout');
        $this->assertSame('PENDING_CONFIRMATION', $pending->fresh()->status);
        $reconciled = app(BillPaymentService::class)->reconcile($pending->fresh());
        $this->assertNotSame('PAID', $reconciled['request']->status);
        $this->assertFalse($reconciled['debited']);
        $this->assertSame(1, BillPaymentRequest::where('provider_message_id', 'bill-pay-2')->count());

        $third = app(BillPaymentService::class)->create([
            'customer_id' => $tenancy->customer_id,
            'text' => 'internet 7000',
            'provider_message_id' => 'bill-pay-3',
        ]);
        $paid = $third['request'];
        $body = json_encode([
            'request_id' => $paid->id,
            'status' => 'SUCCESS',
            'reference' => 'sandbox-1',
            'event_id' => 'evt-ok',
        ]);
        $this->call('POST', '/api/webhooks/bill-payments', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_BILL_SIGNATURE' => hash_hmac('sha256', $body, 'bill-secret'),
        ], $body);
        $this->call('POST', '/api/webhooks/bill-payments', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_BILL_SIGNATURE' => hash_hmac('sha256', $body, 'bill-secret'),
        ], $body);
        $paid->refresh();
        $this->assertSame('PAID', $paid->status);
        $this->assertSame(1, DB::table('bill_payment_events')->where('provider_event_id', 'evt-ok')->count());
        $sent = $this->verifiedDocument($tenancy, 'send my bill receipt '.$paid->id, 'customer');
        $this->assertStringContainsString('bill receipt', $sent['message']);
    }

    public function test_routing_identity_reminders_permissions_and_audit()
    {
        $router = app(AssistantIntentRouter::class);
        $checkout = $router->deterministic('CHECK OUT', [
            'bill_pending' => 1,
            'maintenance_pending' => 1,
            'tenant_choice_pending' => 1,
            'roles' => ['employee', 'tenant'],
        ]);
        $this->assertSame('ATTENDANCE_OUT', $checkout['intent']);
        $clarify = $router->deterministic('what do I owe?', ['roles' => ['customer', 'employee', 'tenant']]);
        $this->assertSame('TENANT_CLARIFY', $clarify['intent']);
        $balance = $router->deterministic('What is my rent balance?', []);
        $this->assertSame('TENANT_BALANCE', $balance['intent']);
        $unknown = app(AssistantPolicyService::class)->decide(['intent' => 'TENANT_BALANCE', 'confidence' => 0.99], []);
        $this->assertSame('HANDOVER', $unknown['action']);
        $this->assertSame([], app(WhatsAppIdentityService::class)->resolve('237699000099'));
        $blocked = app(AssistantToolExecutor::class)->execute('mark_rent_paid', [], ['roles' => ['tenant']]);
        $this->assertSame('privileged', $blocked['error']);
        $debit = app(AssistantToolExecutor::class)->execute('initiate_debit', [], ['roles' => ['customer']]);
        $this->assertSame('privileged', $debit['error']);

        $tenancy = $this->tenancy('670008091', ['due_day' => 23, 'rent_amount' => 10000]);
        app(RentBillingService::class)->generate('2026-09-23');
        $reminders = app(RentReminderService::class);
        $this->assertSame(1, $reminders->run('2026-09-23'));
        $this->assertSame(0, $reminders->run('2026-09-23'));
        $this->assertSame(1, DB::table('rent_reminder_logs')->count());
        app(RentBillingService::class)->recordPayment($tenancy, 10000, 'ERP-PAID');
        $this->assertSame(0, $reminders->run('2026-09-24'));

        $matches = app(WhatsAppIdentityService::class)->resolve('237670008091');
        $roles = array_column($matches, 'role');
        $this->assertContains('customer', $roles);
        $this->assertContains('tenant', $roles);

        $limited = Role::find(3);
        $dummy = Permission::firstOrCreate(['name' => 'customers-index', 'guard_name' => 'web']);
        try {
            $limited->givePermissionTo($dummy);
        } catch (\Exception $e) {
        }
        $this->actingAs($this->makeUser(3, '675700088'));
        $this->get('/admin/properties')->assertRedirect();
        $this->assertTrue(session()->has('not_permitted'));
        $diag = app(WhatsAppHubQuery::class)->diagnostics();
        $this->assertArrayHasKey('property', $diag);
        $this->assertTrue(strpos(json_encode($diag['property']), 'bill-secret') === false);
        $stats = app(PropertyMetrics::class)->all();
        $this->assertArrayHasKey('active_tenancies', $stats);
        $this->assertGreaterThan(0, PropertyActivity::where('action', 'rent_balance')->count() + PropertyActivity::where('action', 'rent_generated')->count());
        $this->assertNotNull(app('router')->getRoutes()->getByName('whatsapp.tenants'));
        $this->assertNotNull(app('router')->getRoutes()->getByName('property.bills.webhook'));
    }

    protected function tenancy($phone, array $extra = [])
    {
        $customer = $this->customer($phone);
        $service = app(TenancyService::class);
        $property = $service->createProperty([
            'name' => 'House '.$phone,
            'code' => 'P'.$phone,
            'city' => 'Douala',
            'country' => 'Cameroon',
        ]);
        $unit = $service->createUnit([
            'property_id' => $property->id,
            'code' => 'U'.$phone,
            'name' => 'Unit '.$phone,
            'unit_type' => 'apartment',
            'rent_amount' => isset($extra['rent_amount']) ? $extra['rent_amount'] : 100000,
        ]);
        $opened = $service->open([
            'customer_id' => $customer->id,
            'unit_id' => $unit['unit']->id,
            'start_date' => '2026-09-01',
            'rent_amount' => isset($extra['rent_amount']) ? $extra['rent_amount'] : 100000,
            'due_day' => isset($extra['due_day']) ? $extra['due_day'] : 5,
            'billing_frequency' => 'monthly',
            'status' => 'ACTIVE',
        ]);
        $this->assertTrue($opened['ok']);

        return $opened['tenancy'];
    }

    protected function customer($phone)
    {
        return Customer::create(['name' => 'Tenant '.$phone, 'phone_number' => $phone, 'is_active' => true]);
    }

    protected function context(Tenancy $tenancy, $role = 'tenant')
    {
        $customer = Customer::find($tenancy->customer_id);
        $contact = \App\WhatsApp\WhatsAppContact::firstOrCreate(
            ['normalized_phone' => '237'.$customer->phone_number],
            ['display_phone' => $customer->phone_number, 'wa_name' => 'Tenant']
        );
        $conversation = \App\WhatsApp\WhatsAppConversation::firstOrCreate(
            ['contact_id' => $contact->id],
            ['mode' => 'AI', 'status' => 'OPEN']
        );
        $roles = $role === 'customer' ? ['customer'] : ['customer', 'tenant'];

        return [
            'roles' => $roles,
            'customer_id' => $customer->id,
            'tenancy_id' => $role === 'customer' ? null : $tenancy->id,
            'tenancy_ids' => $role === 'customer' ? [] : [$tenancy->id],
            'contact_id' => $contact->id,
            'conversation_id' => $conversation->id,
            'conversation' => $conversation,
        ];
    }

    protected function verifiedDocument(Tenancy $tenancy, $text, $role = 'tenant')
    {
        $docs = app(WhatsAppDocumentService::class);
        $first = $docs->handle($this->context($tenancy, $role), $text);
        $this->assertArrayHasKey('otp_code', $first);
        $context = $this->context($tenancy, $role);

        return $docs->handle($context, $first['otp_code'], ['verification_pending' => 1]);
    }

    protected function fakeProvider()
    {
        return new class implements WhatsAppProviderInterface {
            public function sendText($phone, $message)
            {
                return ['success' => true, 'msg_id' => 'T1'];
            }

            public function sendDocument($phone, $localPath, $fileName = null, $caption = null)
            {
                return ['success' => true, 'msg_id' => 'D1'];
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

    protected function attendanceTable()
    {
        if (Schema::hasTable('attendances')) {
            return;
        }
        Schema::create('attendances', function (Blueprint $table) {
            $table->increments('id');
            $table->date('date');
            $table->integer('employee_id')->nullable();
            $table->integer('user_id');
            $table->string('checkin');
            $table->string('checkout')->nullable();
            $table->integer('status');
            $table->timestamps();
        });
    }
}

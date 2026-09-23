<?php

namespace Tests\Feature;

use App\Attendance;
use App\AttendanceCorrection;
use App\Contracts\Ai\AiProviderInterface;
use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\Employee;
use App\Event;
use App\EventAssignment;
use App\EventWorkerProfile;
use App\InternshipEnrolment;
use App\InternshipTaskAssignment;
use App\Services\Assistant\AssistantIntentRouter;
use App\Services\Assistant\AssistantToolExecutor;
use App\Services\Assistant\Providers\NullAiProvider;
use App\Services\Attendance\AttendanceLocationService;
use App\Services\Attendance\AttendanceWhatsAppService;
use App\TimesheetEntry;
use App\User;
use App\WorkingWeek;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppMessage;
use App\WhatsApp\WhatsAppSetting;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\WhatsAppHubTestCase;

class WhatsAppPhase6Test extends WhatsAppHubTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(WhatsAppProviderInterface::class, $this->fakeProvider());
        $this->app->instance(AiProviderInterface::class, new NullAiProvider());
        $this->extraTables();
        Carbon::setTestNow(Carbon::parse('2026-09-23 09:15:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_commands_are_deterministic()
    {
        $router = app(AssistantIntentRouter::class);
        $this->assertSame('ATTENDANCE_IN', $router->deterministic('CHECK IN')['intent']);
        $this->assertSame('ATTENDANCE_OUT', $router->deterministic('CHECK OUT')['intent']);
        $this->assertSame('ATTENDANCE_STATUS', $router->deterministic('STATUS')['intent']);
        $this->assertSame('ATTENDANCE_HOURS', $router->deterministic('How many hours have I worked today?')['intent']);
        $this->assertSame('ATTENDANCE_IN', $router->deterministic("I'm at work.")['intent']);
        $this->assertSame('ATTENDANCE_OUT', $router->deterministic("I'm leaving.")['intent']);
        $this->assertSame('ATTENDANCE_CORRECTION', $router->deterministic('I forgot to check out yesterday.')['intent']);
        $this->assertSame('ATTENDANCE_IN', $router->deterministic('CHECK IN JOB 247')['intent']);
        $this->assertSame('ATTENDANCE_IN', $router->deterministic('', [
            'attendance_pending' => 'ATTENDANCE_IN',
            'latitude' => 4.05,
        ])['intent']);
        $this->assertSame('ATTENDANCE_OUT', $router->deterministic('CHECK OUT', [
            'attendance_pending' => 'ATTENDANCE_IN',
            'latitude' => 4.05,
        ])['intent']);
        $this->assertGreaterThanOrEqual(0.98, $router->deterministic('CHECK IN')['confidence']);
    }

    public function test_chosen_role_is_kept_for_a_later_location()
    {
        $person = $this->employee('675610099');
        InternshipEnrolment::create(['student_user_id' => $person['user']->id, 'status' => 'active']);
        $context = [
            'employee_id' => $person['employee']->id,
            'intern_user_id' => $person['user']->id,
            'roles' => ['employee', 'intern'],
            'conversation_id' => 1,
        ];
        $service = app(AttendanceWhatsAppService::class);
        $ask = $service->checkIn($context, ['text' => 'CHECK IN']);
        $this->assertTrue(! empty($ask['needs_choice']));
        $kept = $service->checkIn($context, ['text' => '', 'context' => 'employee', 'provider_message_id' => 'P6CTX']);
        $this->assertTrue(empty($kept['needs_choice']));
        $this->assertTrue(! empty($kept['checked_in']));
        $this->assertEquals($person['employee']->id, Attendance::first()->employee_id);
        $this->assertNull(Attendance::first()->intern_user_id);
    }

    public function test_employee_check_in_uses_server_time_and_blocks_duplicates()
    {
        $person = $this->employee('675610001');
        $service = app(AttendanceWhatsAppService::class);
        $first = $service->checkIn($person['context'], ['text' => 'CHECK IN 8:00 AM', 'provider_message_id' => 'P6IN']);
        $this->assertTrue($first['success']);
        $this->assertSame('09:15', $first['checked_in']);
        $this->assertSame(1, Attendance::count());
        $this->assertSame('09:15:00', Attendance::first()->checkin);
        $this->assertNull(Attendance::first()->checkout);
        $again = $service->checkIn($person['context'], ['text' => 'CHECK IN', 'provider_message_id' => 'P6IN2']);
        $this->assertTrue(! empty($again['duplicate']));
        $this->assertSame('09:15', $again['started']);
        $this->assertSame(1, Attendance::count());
        $retry = $service->checkIn($person['context'], ['text' => 'CHECK IN', 'provider_message_id' => 'P6IN']);
        $this->assertTrue(! empty($retry['duplicate']));
        $this->assertSame(1, Attendance::count());
    }

    public function test_status_checkout_and_timesheet_use_erp_duration()
    {
        $person = $this->employee('675610002');
        $service = app(AttendanceWhatsAppService::class);
        $service->checkIn($person['context'], ['text' => 'CHECK IN']);
        $status = $service->status($person['context'], []);
        $this->assertSame('checked_in', $status['state']);
        $this->assertSame('09:15', $status['started']);
        Carbon::setTestNow(Carbon::parse('2026-09-23 17:12:00'));
        $out = $service->checkOut($person['context'], ['text' => 'CHECK OUT']);
        $this->assertSame('17:12', $out['checked_out']);
        $this->assertSame('7h 57m', $out['duration']);
        $this->assertSame(1, Attendance::count());
        $this->assertNotNull(Attendance::first()->checkout);
        $this->assertSame(1, TimesheetEntry::count());
        $this->assertGreaterThan(0, (float) TimesheetEntry::first()->hours);
        $none = $service->checkOut($person['context'], ['text' => 'CHECK OUT']);
        $this->assertSame('day_closed', $none['error']);
        $this->assertSame(1, Attendance::count());
    }

    public function test_checkout_without_check_in_creates_nothing()
    {
        $person = $this->employee('675610003');
        $result = app(AttendanceWhatsAppService::class)->checkOut($person['context'], ['text' => 'CHECK OUT']);
        $this->assertSame('no_open', $result['error']);
        $this->assertSame(0, Attendance::count());
    }

    public function test_unknown_number_and_missing_user_do_not_write()
    {
        $result = app(AttendanceWhatsAppService::class)->checkIn(['roles' => []], ['text' => 'CHECK IN']);
        $this->assertSame('not_authorized', $result['error']);
        $this->assertSame(0, Attendance::count());
        $employee = Employee::create([
            'name' => 'No User', 'phone_number' => '675610004', 'user_id' => null, 'is_active' => true,
        ]);
        $failed = app(AttendanceWhatsAppService::class)->checkIn([
            'employee_id' => $employee->id, 'roles' => ['employee'],
        ], ['text' => 'CHECK IN']);
        $this->assertSame('no_user', $failed['error']);
        $this->assertSame(0, Attendance::count());
    }

    public function test_multi_role_asks_and_intern_check_in_does_not_finish_a_task()
    {
        $person = $this->employee('675610005');
        InternshipEnrolment::create(['student_user_id' => $person['user']->id, 'status' => 'active']);
        $context = $person['context'];
        $context['intern_user_id'] = $person['user']->id;
        $context['roles'] = ['employee', 'intern'];
        $choice = app(AttendanceWhatsAppService::class)->checkIn($context, ['text' => 'CHECK IN']);
        $this->assertTrue(! empty($choice['needs_choice']));
        $this->assertSame(0, Attendance::count());

        $intern = $this->intern('675610006');
        $in = app(AttendanceWhatsAppService::class)->checkIn($intern['context'], ['text' => 'CHECK IN']);
        $this->assertTrue($in['success']);
        $this->assertSame('whatsapp', Attendance::first()->source);
        $this->assertSame($intern['user']->id, (int) Attendance::first()->intern_user_id);
        $this->assertNull(Attendance::first()->employee_id);
        $this->assertSame('available', $intern['task']->fresh()->status);
        $this->assertSame(0, (int) Attendance::first()->status);
    }

    public function test_field_job_location_geofence_and_unauthorized()
    {
        $person = $this->employee('675610007');
        $job = $this->job($person['user'], '247', 4.05, 9.70, 200);
        $service = app(AttendanceWhatsAppService::class);
        $need = $service->checkIn($person['context'], ['text' => 'CHECK IN JOB 247']);
        $this->assertTrue(! empty($need['location_required']));
        $this->assertSame(0, Attendance::count());

        $stale = $service->checkIn($person['context'], [
            'text' => 'CHECK IN JOB 247',
            'latitude' => 4.05,
            'longitude' => 9.70,
            'location_at' => '2026-09-23 08:00:00',
        ]);
        $this->assertSame('stale_location', $stale['error']);
        $this->assertSame(0, Attendance::count());

        $bad = $service->checkIn($person['context'], [
            'text' => 'CHECK IN JOB 247', 'latitude' => 999, 'longitude' => 9.7,
        ]);
        $this->assertSame('invalid_location', $bad['error']);

        $inside = $service->checkIn($person['context'], [
            'text' => 'CHECK IN JOB 247', 'latitude' => 4.0501, 'longitude' => 9.7001,
        ]);
        $this->assertSame('LOCATION_VERIFIED', $inside['location_status']);
        $this->assertSame(1, Attendance::count());
        $this->assertSame('checked_in', $job['assignment']->fresh()->attendance_status);

        $other = $this->employee('675610008');
        $denied = $service->checkIn($other['context'], [
            'text' => 'CHECK IN JOB 247', 'latitude' => 4.05, 'longitude' => 9.70,
        ]);
        $this->assertSame('not_assigned', $denied['error']);
        $this->assertSame(1, Attendance::count());
    }

    public function test_outside_geofence_is_review_not_verified()
    {
        $person = $this->employee('675610009');
        $this->job($person['user'], '248', 4.05, 9.70, 50);
        $result = app(AttendanceWhatsAppService::class)->checkIn($person['context'], [
            'text' => 'CHECK IN JOB 248', 'latitude' => 4.20, 'longitude' => 9.70,
        ]);
        $this->assertSame('LOCATION_REVIEW_REQUIRED', $result['location_status']);
        $this->assertNotSame('LOCATION_VERIFIED', $result['location_status']);
        $this->assertSame(1, Attendance::count());
        $distance = app(AttendanceLocationService::class)->distanceMeters(4.05, 9.70, 4.20, 9.70);
        $this->assertGreaterThan(50, $distance);
    }

    public function test_site_without_coordinates_is_unverified()
    {
        $person = $this->employee('675610010');
        $this->job($person['user'], '249', null, null, null);
        $result = app(AttendanceWhatsAppService::class)->checkIn($person['context'], [
            'text' => 'CHECK IN JOB 249', 'latitude' => 4.05, 'longitude' => 9.70,
        ]);
        $this->assertSame('UNVERIFIED', $result['location_status']);
    }

    public function test_hours_assignment_and_privacy()
    {
        $person = $this->employee('675610011');
        $other = $this->employee('675610012');
        $service = app(AttendanceWhatsAppService::class);
        $service->checkIn($person['context'], ['text' => 'CHECK IN']);
        Carbon::setTestNow(Carbon::parse('2026-09-23 09:36:00'));
        $hours = $service->hours($person['context'], ['employee_id' => $other['employee']->id]);
        $this->assertSame('0h 21m', $hours['today']);
        $mine = $service->hours($other['context'], ['employee_id' => $person['employee']->id]);
        $this->assertSame('0h 0m', $mine['today']);
        $jobs = $service->assignment($person['context'], []);
        $this->assertSame([], $jobs['assignments']);
    }

    public function test_forgotten_checkout_and_approval()
    {
        $person = $this->employee('675610013');
        $row = Attendance::create([
            'date' => '2026-09-22',
            'employee_id' => $person['employee']->id,
            'user_id' => $person['user']->id,
            'checkin' => '08:00:00',
            'checkout' => null,
            'status' => 1,
            'source' => 'whatsapp',
        ]);
        $service = app(AttendanceWhatsAppService::class);
        $blocked = $service->checkIn($person['context'], ['text' => 'CHECK IN']);
        $this->assertSame('checkout_required', $blocked['error']);
        $this->assertNull($row->fresh()->checkout);
        $request = $service->requestCorrection($person['context'], ['text' => 'I forgot to check out yesterday at 5']);
        $this->assertSame('PENDING', $request['status']);
        $this->assertNull($row->fresh()->checkout);
        $stranger = $this->employee('675610014');
        $stranger['user']->role_id = 3;
        $stranger['user']->save();
        $denied = $service->approveCorrection($request['correction_id'], $stranger['user'], '17:00');
        $this->assertSame('forbidden', $denied['error']);
        $this->assertNull($row->fresh()->checkout);
        Role::find(1);
        $approved = $service->approveCorrection($request['correction_id'], $person['user'], '17:00');
        $this->assertTrue($approved['success']);
        $this->assertSame('17:00:00', $row->fresh()->checkout);
        $this->assertSame(AttendanceCorrection::APPROVED, AttendanceCorrection::first()->status);
        $this->assertSame(1, TimesheetEntry::where('user_id', $person['user']->id)->count());
    }

    public function test_webhook_unknown_and_known_employee()
    {
        $this->enableAssistant();
        WhatsAppSetting::putValue('default_conversation_mode', 'AI');
        $this->postWebhook($this->incomingText('+237675619999', 'CHECK IN', 'P6UNK'))->assertStatus(200);
        $this->assertSame(0, Attendance::count());

        $this->employee('675610015');
        $payload = $this->incomingText('+237675610015', 'CHECK IN', 'P6WEB');
        $this->postWebhook($payload)->assertStatus(200);
        $this->assertSame(1, Attendance::count());
        $this->assertStringContainsString('09:15', $this->latestAssistant());
        $replies = WhatsAppMessage::where('sender_type', 'ASSISTANT')->count();
        $this->postWebhook($payload)->assertStatus(200);
        $this->assertSame(1, Attendance::count());
        $this->assertSame($replies, WhatsAppMessage::where('sender_type', 'ASSISTANT')->count());
    }

    public function test_privileged_tools_and_ops_diagnostics()
    {
        $blocked = app(AssistantToolExecutor::class)->execute('approve_attendance_correction', [], ['roles' => ['employee']]);
        $this->assertSame('privileged', $blocked['error']);
        $customer = app(AssistantToolExecutor::class)->execute('check_in', [], ['roles' => ['customer']]);
        $this->assertSame('identity_required', $customer['error']);

        $this->assertNotNull(app('router')->getRoutes()->getByName('whatsapp.attendance'));
        $diag = app(\App\Services\WhatsApp\WhatsAppHubQuery::class)->diagnostics();
        $this->assertArrayHasKey('attendance', $diag);
        $stats = app(\App\Services\WhatsApp\WhatsAppHubQuery::class)->commandCenter([
            'from' => Carbon::now()->startOfDay(),
            'to' => Carbon::now()->endOfDay(),
        ]);
        $this->assertArrayHasKey('checked_in_now', $stats);

        $limitedRole = Role::find(3);
        $dummy = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'customers-index', 'guard_name' => 'web']);
        try {
            $limitedRole->givePermissionTo($dummy);
        } catch (\Exception $e) {
        }
        $this->actingAs($this->makeUser(3, '675610099'));
        $this->get('/admin/whatsapp/attendance')->assertRedirect();
        $this->assertTrue(session()->has('not_permitted'));
    }

    protected function employee($phone)
    {
        $user = User::create([
            'name' => 'Emp '.$phone, 'email' => $phone.'@example.test', 'password' => bcrypt('x'),
            'phone' => $phone, 'role_id' => 1, 'is_active' => true, 'is_deleted' => false,
        ]);
        $employee = Employee::create([
            'name' => 'Emp '.$phone, 'phone_number' => $phone, 'user_id' => $user->id, 'is_active' => true,
        ]);

        return [
            'user' => $user,
            'employee' => $employee,
            'context' => ['employee_id' => $employee->id, 'roles' => ['employee'], 'conversation_id' => 1],
        ];
    }

    protected function intern($phone)
    {
        $user = User::create([
            'name' => 'Intern '.$phone, 'email' => $phone.'@example.test', 'password' => bcrypt('x'),
            'phone' => $phone, 'role_id' => 1, 'is_active' => true, 'is_deleted' => false,
        ]);
        InternshipEnrolment::create(['student_user_id' => $user->id, 'status' => 'active']);
        WorkingWeek::create([
            'user_id' => $user->id,
            'monday' => false, 'tuesday' => false, 'wednesday' => true, 'thursday' => false,
            'friday' => false, 'saturday' => false, 'sunday' => false,
            'wednesday_start' => '09:00', 'wednesday_end' => '17:00',
            'lunch_break_minutes' => 60, 'expected_hours_per_day' => 8,
        ]);
        $task = InternshipTaskAssignment::create([
            'enrolment_id' => InternshipEnrolment::first()->id,
            'status' => 'available',
            'released_at' => now(),
        ]);

        return [
            'user' => $user,
            'task' => $task,
            'context' => ['intern_user_id' => $user->id, 'roles' => ['intern']],
        ];
    }

    protected function job(User $user, $number, $lat, $lng, $radius)
    {
        $event = new Event();
        $event->reference_no = 'JOB-'.$number;
        $event->name = 'Setup '.$number;
        $event->venue = 'Hall';
        $event->latitude = $lat;
        $event->longitude = $lng;
        $event->geofence_radius_meters = $radius;
        $event->setup_start_at = Carbon::parse('2026-09-23 07:00:00');
        $event->event_start_at = Carbon::parse('2026-09-23 18:00:00');
        $event->save();
        $profile = EventWorkerProfile::create([
            'user_id' => $user->id,
            'telephone' => $user->phone,
            'is_active' => true,
        ]);
        $assignment = EventAssignment::create([
            'event_id' => $event->id,
            'worker_profile_id' => $profile->id,
            'assignment_role' => 'Technician',
            'work_start_date' => '2026-09-23',
            'work_end_date' => '2026-09-23',
            'attendance_status' => 'pending',
        ]);

        return compact('event', 'profile', 'assignment');
    }

    protected function extraTables()
    {
        if (! Schema::hasTable('attendances')) {
            Schema::create('attendances', function (Blueprint $table) {
                $table->increments('id');
                $table->date('date');
                $table->integer('employee_id')->nullable();
                $table->integer('user_id');
                $table->unsignedInteger('intern_user_id')->nullable();
                $table->string('checkin');
                $table->string('checkout')->nullable();
                $table->integer('status');
                $table->text('note')->nullable();
                $table->string('source')->nullable();
                $table->unsignedInteger('whatsapp_conversation_id')->nullable();
                $table->string('whatsapp_message_id')->nullable()->unique();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->unsignedInteger('location_accuracy')->nullable();
                $table->timestamp('location_at')->nullable();
                $table->string('location_status')->nullable();
                $table->unsignedInteger('distance_meters')->nullable();
                $table->unsignedInteger('allowed_radius_meters')->nullable();
                $table->string('event_assignment_id')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('be_timesheet_entries')) {
            Schema::create('be_timesheet_entries', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->unsignedInteger('user_id')->nullable();
                $table->string('employee_name')->nullable();
                $table->date('entry_date')->nullable();
                $table->decimal('hours', 8, 2)->default(0);
                $table->decimal('overtime_hours', 8, 2)->default(0);
                $table->boolean('requires_ot_approval')->default(false);
                $table->text('notes')->nullable();
                $table->string('status')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('be_working_week')) {
            Schema::create('be_working_week', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->unsignedInteger('user_id')->nullable();
                $table->boolean('monday')->default(false);
                $table->boolean('tuesday')->default(false);
                $table->boolean('wednesday')->default(false);
                $table->boolean('thursday')->default(false);
                $table->boolean('friday')->default(false);
                $table->boolean('saturday')->default(false);
                $table->boolean('sunday')->default(false);
                $table->string('wednesday_start', 5)->nullable();
                $table->string('wednesday_end', 5)->nullable();
                $table->unsignedSmallInteger('lunch_break_minutes')->default(0);
                $table->decimal('expected_hours_per_day', 5, 2)->default(8);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('internship_task_assignments')) {
            Schema::create('internship_task_assignments', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('enrolment_id')->nullable();
                $table->string('status')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('btw_events')) {
            Schema::create('btw_events', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('reference_no')->nullable();
                $table->string('name')->nullable();
                $table->string('slug')->nullable();
                $table->string('venue')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->unsignedInteger('geofence_radius_meters')->nullable();
                $table->dateTime('setup_start_at')->nullable();
                $table->dateTime('event_start_at')->nullable();
                $table->dateTime('event_end_at')->nullable();
                $table->dateTime('dismantling_end_at')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('event_worker_profiles')) {
            Schema::create('event_worker_profiles', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->unsignedInteger('user_id')->nullable();
                $table->string('telephone')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('event_assignments')) {
            Schema::create('event_assignments', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('event_id')->nullable();
                $table->uuid('worker_profile_id')->nullable();
                $table->string('assignment_role')->nullable();
                $table->dateTime('reporting_time')->nullable();
                $table->date('work_start_date')->nullable();
                $table->date('work_end_date')->nullable();
                $table->string('attendance_status')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('event_timesheets')) {
            Schema::create('event_timesheets', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('event_id')->nullable();
                $table->uuid('assignment_id')->nullable();
                $table->uuid('worker_profile_id')->nullable();
                $table->string('status')->nullable();
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->unsignedSmallInteger('total_days')->default(0);
                $table->decimal('total_hours', 6, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('event_timesheet_entries')) {
            Schema::create('event_timesheet_entries', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('timesheet_id')->nullable();
                $table->date('work_date')->nullable();
                $table->decimal('hours', 5, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    protected function enableAssistant()
    {
        config(['assistant.enabled' => true, 'assistant.provider' => 'null']);
        WhatsAppSetting::putValue('assistant_enabled', '1');
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
                return ['success' => true, 'msg_id' => 'P6'];
            }

            public function sendDocument($phone, $localPath, $fileName = null, $caption = null)
            {
                return ['success' => true];
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

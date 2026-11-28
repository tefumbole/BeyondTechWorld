<?php

namespace Tests\Feature;

use App\Mai\MaiSession;
use App\Services\Mai\MaiProviderRouter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MaiAdminDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \App\Http\Middleware\LogActivity::class,
        ]);
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->string('company_name')->nullable();
            $table->integer('role_id')->default(5);
            $table->boolean('is_active')->default(1);
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->timestamps();
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->timestamps();
        });
        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedInteger('permission_id');
            $table->unsignedInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });
        \DB::table('roles')->insert([
            ['id' => 1, 'name' => 'Admin', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'name' => 'Customer', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
        ]);
        Schema::create('cloud_subscriptions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable();
            $table->unsignedInteger('cloud_plan_id')->nullable();
            $table->string('status');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamps();
        });
        Schema::create('internship_enrolments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('student_user_id')->nullable();
            $table->string('status')->nullable();
        });
        Schema::create('cloud_plans', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->timestamps();
        });
        \DB::table('cloud_plans')->insert(['id' => 1, 'name' => 'Whats MAI', 'created_at' => now(), 'updated_at' => now()]);
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_11_26_233000_create_mai_customer_sessions.php', '--force' => true]);
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_11_28_120000_mai_dashboard_tokens.php', '--force' => true]);
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_11_28_130000_create_mai_signins.php', '--force' => true]);
    }

    public function test_an_admin_can_open_the_dashboard_and_a_customer_cannot()
    {
        $idle = $this->person('idle@example.test', 5);
        \DB::table('mai_signins')->insert(['user_id' => $idle->id, 'signed_in_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $trial = $this->person('trial@example.test', 5);
        $paid = $this->person('paid@example.test', 5);
        $this->seedPlan($trial, 11, 'TRIALING', now()->addDay(), null, 'CONNECTED');
        $this->seedPlan($paid, 12, 'ACTIVE', null, now()->addMonth(), 'CONNECTED');

        $admin = $this->person('admin@example.test', 1);
        $this->actingAs($admin);
        $page = $this->get('/admin/whatsapp/mai');
        $page->assertOk();
        $page->assertSee('Operator dashboard');
        $page->assertSee('idle@example.test');
        $page->assertSee('trial@example.test');
        $page->assertSee('TRIALING');
        $page->assertSee('paid@example.test');
        $page->assertSee('ACTIVE');
        $page->assertDontSee('Revoke');
        $page->assertDontSee('mai_');

        $filtered = $this->get('/admin/whatsapp/mai?filter=no_session');
        $filtered->assertOk();
        $filtered->assertSee('idle@example.test');
        $filtered->assertDontSee('paid@example.test');

        $customer = $this->person('shop@example.test', 5);
        $this->actingAs($customer);
        $this->get('/admin/whatsapp/mai')
            ->assertRedirect('/admin')
            ->assertSessionHas('not_permitted');

        $this->assertNotNull(app('router')->getRoutes()->getByName('whatsapp.mai.link'));
        $this->assertNotNull(app('router')->getRoutes()->getByName('whatsapp.mai.link.qr'));
        $this->assertNotNull(app('router')->getRoutes()->getByName('whatsapp.mai.link.pair'));
        $this->assertNotNull(app('router')->getRoutes()->getByName('whatsapp.mai.inbox'));
        $this->assertStringContainsString(
            'WaSenderWebhookController@handle',
            app('router')->getRoutes()->match(
                \Illuminate\Http\Request::create('/api/webhooks/wasender', 'POST')
            )->getActionName()
        );
    }

    public function test_a_pdf_above_eight_megabytes_is_refused_before_the_worker()
    {
        $dir = sys_get_temp_dir().'/mai-pdf-'.bin2hex(random_bytes(4));
        mkdir($dir);
        $small = $dir.'/below.pdf';
        $big = $dir.'/above.pdf';
        file_put_contents($small, "%PDF-1.4\n".str_repeat('a', 2048));
        file_put_contents($big, "%PDF-1.4\n".str_repeat('b', 8 * 1024 * 1024));
        $seen = 0;
        config(['cloud.whatsapp.link_transport' => function () use (&$seen) {
            $seen++;

            return ['success' => true, 'id' => 'm1'];
        }]);
        $engine = app(\App\Services\Cloud\MaiWhatsAppEngine::class);
        $ok = $engine->sendFile('237670000111', '237671111222', $small, 'document', 'application/pdf', 'below.pdf', '');
        $blocked = $engine->sendFile('237670000111', '237671111222', $big, 'document', 'application/pdf', 'above.pdf', '');
        $this->assertTrue($ok['success']);
        $this->assertSame(1, $seen);
        $this->assertFalse($blocked['success']);
        $this->assertSame('Send a file up to 8 MB.', $blocked['error']);
    }

    protected function person($email, $roleId)
    {
        $user = new \App\User();
        $user->name = $email;
        $user->email = $email;
        $user->password = bcrypt('portal-secret');
        $user->phone = '237670000111';
        $user->company_name = $email;
        $user->role_id = $roleId;
        $user->is_active = 1;
        $user->save();

        return $user;
    }

    protected function seedPlan($user, $tenantId, $status, $trialEnd, $periodEnd, $sessionStatus)
    {
        \DB::table('cloud_subscriptions')->insert([
            'cloud_tenant_id' => $tenantId,
            'cloud_plan_id' => 1,
            'status' => $status,
            'trial_ends_at' => $trialEnd,
            'current_period_end' => $periodEnd,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $session = new MaiSession();
        $session->public_id = 'c'.bin2hex(random_bytes(16));
        $session->user_id = $user->id;
        $session->cloud_tenant_id = $tenantId;
        $session->provider = MaiProviderRouter::MAI;
        $session->worker_session_id = 's'.bin2hex(random_bytes(8));
        $session->expected_phone = '237670000111';
        $session->phone_number = $sessionStatus === 'CONNECTED' ? '237670000111' : null;
        $session->status = $sessionStatus;
        $session->save();
    }
}

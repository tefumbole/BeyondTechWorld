<?php

namespace Tests;

use App\Customer;
use App\Employee;
use App\InternshipEnrolment;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

abstract class WhatsAppHubTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.whatsapp.wasender_webhook_secret' => 'test-secret']);
        config(['services.whatsapp.default_conversation_mode' => 'HUMAN']);
        config(['services.whatsapp.wasender_api_key' => '']);
        config(['services.whatsapp.wasender_session_id' => '']);
        $this->createSupportTables();
        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_09_22_180000_create_whatsapp_hub_foundation.php',
            '--force' => true,
        ]);
        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_09_22_191000_create_whatsapp_hub_phase2.php',
            '--force' => true,
        ]);
        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_09_22_201000_create_whatsapp_hub_phase3.php',
            '--force' => true,
        ]);
        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_09_23_100000_create_whatsapp_rental_requests.php',
            '--force' => true,
        ]);
        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_09_23_140000_create_whatsapp_internship_intakes.php',
            '--force' => true,
        ]);
        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_09_23_160000_extend_attendance_for_whatsapp.php',
            '--force' => true,
        ]);
        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_09_23_180000_create_whatsapp_document_verification.php',
            '--force' => true,
        ]);
        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_09_23_200000_create_property_tenant_foundation.php',
            '--force' => true,
        ]);
        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_09_25_120000_create_whatsapp_call_requests.php',
            '--force' => true,
        ]);
    }

    protected function createSupportTables()
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name')->nullable();
                $table->string('email')->nullable();
                $table->string('password')->nullable();
                $table->string('phone')->nullable();
                $table->string('additional_phone')->nullable();
                $table->unsignedInteger('role_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_deleted')->default(false);
                $table->boolean('must_set_password')->default(false);
                $table->boolean('otp_verify')->default(true);
                $table->rememberToken();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->string('guard_name')->default('web');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->string('guard_name')->default('web');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('role_has_permissions')) {
            Schema::create('role_has_permissions', function (Blueprint $table) {
                $table->unsignedInteger('permission_id');
                $table->unsignedInteger('role_id');
                $table->primary(['permission_id', 'role_id']);
            });
        }
        if (! Schema::hasTable('model_has_roles')) {
            Schema::create('model_has_roles', function (Blueprint $table) {
                $table->unsignedInteger('role_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
            });
        }
        if (! Schema::hasTable('model_has_permissions')) {
            Schema::create('model_has_permissions', function (Blueprint $table) {
                $table->unsignedInteger('permission_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
            });
        }
        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name')->nullable();
                $table->string('phone_number')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('employees')) {
            Schema::create('employees', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name')->nullable();
                $table->string('phone_number')->nullable();
                $table->unsignedInteger('user_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('suppliers')) {
            Schema::create('suppliers', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name')->nullable();
                $table->string('phone_number')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('applications')) {
            Schema::create('applications', function (Blueprint $table) {
                $table->increments('id');
                $table->string('full_name')->nullable();
                $table->string('phone')->nullable();
                $table->string('whatsapp_number')->nullable();
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
        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->text('connection')->nullable();
                $table->text('queue')->nullable();
                $table->longText('payload')->nullable();
                $table->longText('exception')->nullable();
                $table->timestamp('failed_at')->nullable();
            });
        }
        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('queue');
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (! Role::find(1)) {
            DB::table('roles')->insert([
                'id' => 1,
                'name' => 'Admin',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        if (! Role::find(3)) {
            DB::table('roles')->insert([
                'id' => 3,
                'name' => 'StaffLimited',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function makeUser($roleId = 1, $phone = '675111111')
    {
        return User::create([
            'name' => 'Staff '.$roleId,
            'email' => 'staff'.$roleId.uniqid().'@example.test',
            'password' => Hash::make('secret'),
            'phone' => $phone,
            'role_id' => $roleId,
            'is_active' => true,
            'is_deleted' => false,
        ]);
    }

    protected function grantWhatsApp(Role $role)
    {
        foreach ([
            'whatsapp_module', 'whatsapp.view', 'whatsapp.manage',
            'whatsapp.conversations', 'whatsapp.reply', 'whatsapp.calls', 'whatsapp.settings',
            'whatsapp.leads', 'whatsapp.leads.manage', 'whatsapp.assign',
            'whatsapp.takeover', 'whatsapp.notes', 'whatsapp.documents',
            'whatsapp.ai', 'whatsapp.ai.manage', 'whatsapp.ai.knowledge',
            'whatsapp.ai.tools', 'whatsapp.ai.activity', 'whatsapp.ai.suggest',
            'whatsapp.rentals', 'whatsapp.rentals.view', 'whatsapp.rentals.manage',
            'whatsapp.quotation.prepare', 'whatsapp.quotation.approve', 'whatsapp.quotation.send',
            'whatsapp.internship', 'whatsapp.internship.view', 'whatsapp.internship.submit',
            'whatsapp.internship.manage', 'whatsapp.internship.media',
            'whatsapp.attendance', 'whatsapp.attendance.view', 'whatsapp.attendance.manage',
            'whatsapp.attendance.location', 'whatsapp.attendance.corrections',
            'whatsapp.documents.view', 'whatsapp.documents.manage', 'whatsapp.documents.retry',
            'whatsapp.verification', 'whatsapp.verification.invalidate',
            'properties.view', 'properties.manage', 'tenancies.view', 'tenancies.manage',
            'rent.view', 'rent.manage', 'maintenance.view', 'maintenance.manage',
            'billpayments.view', 'billpayments.manage',
            'whatsapp.tenants.view', 'whatsapp.tenants.manage',
            'whatsapp.bills.view', 'whatsapp.bills.manage',
        ] as $name) {
            $perm = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            try {
                $role->givePermissionTo($perm);
            } catch (\Exception $e) {
            }
        }
    }

    protected function signedHeaders($body)
    {
        return [
            'X-Webhook-Signature' => 'test-secret',
            'Content-Type' => 'application/json',
        ];
    }

    protected function postWebhook(array $payload)
    {
        return $this->withHeaders($this->signedHeaders(json_encode($payload)))
            ->json('POST', '/api/webhooks/wasender', $payload);
    }
}

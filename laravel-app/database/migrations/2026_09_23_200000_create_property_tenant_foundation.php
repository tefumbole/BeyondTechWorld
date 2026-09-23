<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreatePropertyTenantFoundation extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('properties')) {
            Schema::create('properties', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->string('code', 40)->unique();
                $table->string('address')->nullable();
                $table->string('city')->nullable();
                $table->string('country')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('manager_employee_id')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('property_units')) {
            Schema::create('property_units', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('property_id')->index();
                $table->string('code', 40);
                $table->string('name');
                $table->string('unit_type', 40)->default('unit');
                $table->text('description')->nullable();
                $table->decimal('rent_amount', 14, 2)->default(0);
                $table->string('billing_frequency', 20)->default('monthly');
                $table->string('status', 20)->default('AVAILABLE');
                $table->timestamps();
                $table->unique(['property_id', 'code']);
            });
        }
        if (! Schema::hasTable('tenancies')) {
            Schema::create('tenancies', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('customer_id')->index();
                $table->unsignedInteger('property_id')->index();
                $table->unsignedInteger('unit_id')->index();
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->decimal('rent_amount', 14, 2);
                $table->string('billing_frequency', 20)->default('monthly');
                $table->decimal('security_deposit', 14, 2)->nullable();
                $table->unsignedTinyInteger('due_day')->default(1);
                $table->string('status', 20)->default('DRAFT');
                $table->string('agreement_path', 500)->nullable();
                $table->unsignedInteger('active_unit_lock')->nullable()->unique();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('rent_obligations')) {
            Schema::create('rent_obligations', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenancy_id')->index();
                $table->string('period_key', 20);
                $table->date('period_start');
                $table->date('period_end');
                $table->date('due_date');
                $table->decimal('amount_due', 14, 2);
                $table->decimal('amount_paid', 14, 2)->default(0);
                $table->string('status', 20)->default('UPCOMING');
                $table->timestamps();
                $table->unique(['tenancy_id', 'period_key']);
            });
        }
        if (! Schema::hasTable('property_rent_payments')) {
            Schema::create('property_rent_payments', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenancy_id')->index();
                $table->unsignedInteger('rent_obligation_id')->nullable()->index();
                $table->decimal('amount', 14, 2);
                $table->string('currency', 8)->default('XAF');
                $table->date('paid_on');
                $table->string('source', 20)->default('erp');
                $table->string('reference', 80)->nullable();
                $table->unsignedInteger('recorded_by')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('property_maintenance_requests')) {
            Schema::create('property_maintenance_requests', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('property_id')->index();
                $table->unsignedInteger('unit_id')->index();
                $table->unsignedInteger('tenancy_id')->index();
                $table->unsignedInteger('customer_id')->index();
                $table->string('category', 40);
                $table->text('description');
                $table->string('priority', 20)->default('NORMAL');
                $table->boolean('emergency')->default(false);
                $table->string('status', 20)->default('OPEN');
                $table->unsignedInteger('assigned_employee_id')->nullable();
                $table->unsignedInteger('attendance_id')->nullable();
                $table->unsignedInteger('conversation_id')->nullable()->index();
                $table->string('source', 20)->default('erp');
                $table->string('provider_message_id')->nullable()->unique();
                $table->timestamp('opened_at')->nullable();
                $table->timestamp('acknowledged_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('property_maintenance_attachments')) {
            Schema::create('property_maintenance_attachments', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('maintenance_request_id')->index();
                $table->unsignedInteger('tenancy_id')->index();
                $table->string('original_name', 180);
                $table->string('stored_name', 180);
                $table->string('mime', 80);
                $table->unsignedInteger('size_bytes');
                $table->string('kind', 20)->default('image');
                $table->string('path', 500);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('property_utility_accounts')) {
            Schema::create('property_utility_accounts', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('property_id')->index();
                $table->unsignedInteger('unit_id')->index();
                $table->string('utility_type', 40);
                $table->string('provider', 80);
                $table->string('account_reference', 80);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('bill_payment_requests')) {
            Schema::create('bill_payment_requests', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('customer_id')->nullable()->index();
                $table->unsignedInteger('tenancy_id')->nullable()->index();
                $table->unsignedInteger('conversation_id')->nullable()->index();
                $table->string('bill_category', 40);
                $table->string('provider', 80)->nullable();
                $table->string('account_reference', 80)->nullable();
                $table->decimal('amount', 14, 2)->nullable();
                $table->decimal('service_fee', 14, 2)->default(0);
                $table->string('currency', 8)->default('XAF');
                $table->boolean('amount_provisional')->default(false);
                $table->string('source', 20)->default('whatsapp');
                $table->string('status', 32)->default('DRAFT');
                $table->unsignedInteger('assigned_employee_id')->nullable();
                $table->string('provider_reference', 80)->nullable();
                $table->string('provider_message_id')->nullable()->unique();
                $table->string('failure_reason', 180)->nullable();
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('bill_payment_attachments')) {
            Schema::create('bill_payment_attachments', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('bill_payment_request_id')->index();
                $table->string('original_name', 180);
                $table->string('mime', 80);
                $table->unsignedInteger('size_bytes');
                $table->string('path', 500);
                $table->text('extracted_note')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('bill_payment_events')) {
            Schema::create('bill_payment_events', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('bill_payment_request_id')->index();
                $table->string('provider_event_id', 80)->unique();
                $table->string('provider_status', 40);
                $table->string('provider_reference', 80)->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('rent_reminder_logs')) {
            Schema::create('rent_reminder_logs', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenancy_id')->index();
                $table->unsignedInteger('rent_obligation_id');
                $table->string('reminder_type', 20);
                $table->string('period_key', 20);
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
                $table->unique(['rent_obligation_id', 'reminder_type']);
            });
        }
        if (! Schema::hasTable('property_activities')) {
            Schema::create('property_activities', function (Blueprint $table) {
                $table->increments('id');
                $table->string('action', 60);
                $table->string('subject_type', 40)->nullable();
                $table->unsignedInteger('subject_id')->nullable();
                $table->unsignedInteger('conversation_id')->nullable();
                $table->unsignedInteger('actor_user_id')->nullable();
                $table->text('meta')->nullable();
                $table->timestamps();
            });
        }
        $this->seedPermissions();
    }

    public function down()
    {
        Schema::dropIfExists('property_activities');
        Schema::dropIfExists('rent_reminder_logs');
        Schema::dropIfExists('bill_payment_events');
        Schema::dropIfExists('bill_payment_attachments');
        Schema::dropIfExists('bill_payment_requests');
        Schema::dropIfExists('property_utility_accounts');
        Schema::dropIfExists('property_maintenance_attachments');
        Schema::dropIfExists('property_maintenance_requests');
        Schema::dropIfExists('property_rent_payments');
        Schema::dropIfExists('rent_obligations');
        Schema::dropIfExists('tenancies');
        Schema::dropIfExists('property_units');
        Schema::dropIfExists('properties');
    }

    private function seedPermissions()
    {
        $names = [
            'properties.view', 'properties.manage',
            'tenancies.view', 'tenancies.manage',
            'rent.view', 'rent.manage',
            'maintenance.view', 'maintenance.manage',
            'billpayments.view', 'billpayments.manage',
            'whatsapp.tenants.view', 'whatsapp.tenants.manage',
            'whatsapp.bills.view', 'whatsapp.bills.manage',
        ];
        foreach ($names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        foreach (Role::whereIn('id', [1, 2])->get() as $role) {
            foreach ($names as $name) {
                try {
                    $role->givePermissionTo($name);
                } catch (\Exception $e) {
                }
            }
        }
    }
}

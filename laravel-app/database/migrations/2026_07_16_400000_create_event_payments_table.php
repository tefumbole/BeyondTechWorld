<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateEventPaymentsTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('event_worker_payments')) {
            Schema::create('event_worker_payments', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('event_id');
                $table->uuid('assignment_id');
                $table->uuid('worker_profile_id');
                $table->string('reference_no', 32)->unique();
                $table->unsignedInteger('amount');
                $table->string('payment_method', 32)->default('mobile_money');
                $table->string('mobile_money_number', 64)->nullable();
                $table->string('status', 32)->default('pending');
                $table->dateTime('paid_at')->nullable();
                $table->string('receipt_path')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->unsignedInteger('approved_by')->nullable();
                $table->timestamps();

                $table->index(['event_id', 'status']);
                $table->index('assignment_id');
            });
        }

        foreach (['event_payments.view', 'event_payments.create', 'event_payments.approve'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        foreach (Role::whereIn('id', [1, 2])->get() as $role) {
            foreach (['event_payments.view', 'event_payments.create', 'event_payments.approve'] as $perm) {
                if (! $role->hasPermissionTo($perm)) {
                    $role->givePermissionTo($perm);
                }
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('event_worker_payments');
    }
}

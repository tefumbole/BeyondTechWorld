<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateWhatsappRentalRequests extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('whatsapp_rental_requests')) {
            Schema::create('whatsapp_rental_requests', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('conversation_id')->index();
                $table->unsignedBigInteger('contact_id')->nullable()->index();
                $table->unsignedBigInteger('lead_id')->nullable()->index();
                $table->unsignedInteger('customer_id')->nullable()->index();
                $table->string('event_type', 48)->nullable();
                $table->date('event_date')->nullable();
                $table->string('event_date_text', 80)->nullable();
                $table->dateTime('setup_at')->nullable();
                $table->dateTime('event_start_at')->nullable();
                $table->dateTime('event_end_at')->nullable();
                $table->dateTime('return_at')->nullable();
                $table->string('location', 191)->nullable();
                $table->string('venue', 191)->nullable();
                $table->string('indoor_outdoor', 16)->nullable();
                $table->unsignedInteger('attendance')->nullable();
                $table->text('categories_json')->nullable();
                $table->text('lines_json')->nullable();
                $table->text('requirements')->nullable();
                $table->decimal('budget', 14, 2)->nullable();
                $table->boolean('delivery_required')->default(false);
                $table->boolean('setup_required')->default(false);
                $table->boolean('technicians_required')->default(false);
                $table->string('status', 48)->default('COLLECTING_REQUIREMENTS')->index();
                $table->unsignedInteger('quotation_id')->nullable()->index();
                $table->dateTime('availability_checked_at')->nullable();
                $table->string('availability_note', 191)->nullable();
                $table->decimal('proposal_total', 14, 2)->nullable();
                $table->string('created_by', 24)->default('assistant');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('whatsapp_rental_activities')) {
            Schema::create('whatsapp_rental_activities', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('rental_request_id')->index();
                $table->string('type', 48);
                $table->text('body')->nullable();
                $table->text('meta')->nullable();
                $table->unsignedInteger('actor_user_id')->nullable();
                $table->timestamps();
            });
        }
        if (Schema::hasTable('quotations')) {
            Schema::table('quotations', function (Blueprint $table) {
                if (! Schema::hasColumn('quotations', 'whatsapp_conversation_id')) {
                    $table->unsignedBigInteger('whatsapp_conversation_id')->nullable()->index();
                }
                if (! Schema::hasColumn('quotations', 'whatsapp_lead_id')) {
                    $table->unsignedBigInteger('whatsapp_lead_id')->nullable()->index();
                }
                if (! Schema::hasColumn('quotations', 'quotation_source')) {
                    $table->string('quotation_source', 32)->nullable();
                }
                if (! Schema::hasColumn('quotations', 'revised_from_id')) {
                    $table->unsignedInteger('revised_from_id')->nullable()->index();
                }
            });
        }
        $this->seedPermissions();
    }

    private function seedPermissions()
    {
        $prepare = [
            'whatsapp.rentals',
            'whatsapp.rentals.view',
            'whatsapp.rentals.manage',
            'whatsapp.quotation.prepare',
        ];
        $approve = [
            'whatsapp.quotation.approve',
            'whatsapp.quotation.send',
        ];
        foreach (array_merge($prepare, $approve) as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        foreach (Role::whereIn('id', [1, 2])->get() as $role) {
            foreach ($prepare as $name) {
                try {
                    $role->givePermissionTo($name);
                } catch (\Exception $e) {
                }
            }
        }
        $admin = Role::find(1);
        if ($admin) {
            foreach ($approve as $name) {
                try {
                    $admin->givePermissionTo($name);
                } catch (\Exception $e) {
                }
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_rental_activities');
        Schema::dropIfExists('whatsapp_rental_requests');
    }
}

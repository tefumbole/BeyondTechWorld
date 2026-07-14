<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Operational events module (Phase 1). Uses btw_events to avoid colliding with
 * the legacy Node/React invitations events table.
 */
class CreateBtwEventsTables extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('btw_events')) {
            Schema::create('btw_events', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('reference_no', 32)->unique();
                $table->string('name');
                $table->string('slug', 191)->unique();
                $table->string('event_type', 64)->default('other');
                $table->string('flyer_path')->nullable();
                $table->unsignedInteger('customer_id')->nullable()->index();
                $table->string('client_contact_person')->nullable();
                $table->string('client_telephone', 64)->nullable();
                $table->string('client_email')->nullable();
                $table->string('venue')->nullable();
                $table->text('venue_address')->nullable();
                $table->string('city', 128)->nullable();
                $table->string('timezone', 64)->default('Africa/Kigali');
                $table->text('internal_description')->nullable();
                $table->text('internal_notes')->nullable();

                $table->dateTime('packing_at')->nullable();
                $table->dateTime('loading_at')->nullable();
                $table->dateTime('departure_at')->nullable();
                $table->dateTime('setup_start_at')->nullable();
                $table->dateTime('setup_end_at')->nullable();
                $table->dateTime('rehearsal_at')->nullable();
                $table->dateTime('event_start_at')->nullable();
                $table->dateTime('event_end_at')->nullable();
                $table->dateTime('dismantling_start_at')->nullable();
                $table->dateTime('dismantling_end_at')->nullable();
                $table->dateTime('return_at')->nullable();

                $table->unsignedSmallInteger('expected_workdays')->nullable();
                $table->dateTime('timesheet_deadline_at')->nullable();

                $table->unsignedInteger('booking_id')->nullable()->index();
                $table->string('rental_link_mode', 32)->default('none'); // none|link|create

                $table->string('internal_status', 64)->default('draft')->index();
                $table->string('labour_mode', 32)->default('individual'); // individual|budget

                $table->unsignedInteger('created_by')->nullable();
                $table->unsignedInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('event_publications')) {
            Schema::create('event_publications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('event_id')->unique();
                $table->boolean('publish_on_website')->default(false);
                $table->string('public_title')->nullable();
                $table->string('public_summary', 500)->nullable();
                $table->text('public_description')->nullable();
                $table->string('public_flyer_path')->nullable();
                $table->string('public_venue')->nullable();
                $table->string('public_location')->nullable();
                $table->string('public_contact_name')->nullable();
                $table->string('public_contact_phone', 64)->nullable();
                $table->string('public_contact_email')->nullable();
                $table->string('registration_url', 2048)->nullable();
                $table->string('ticket_url', 2048)->nullable();
                $table->string('external_url', 2048)->nullable();
                $table->dateTime('visibility_at')->nullable();
                $table->dateTime('unpublish_at')->nullable();
                $table->boolean('is_featured')->default(false);
                $table->unsignedInteger('display_order')->default(0);
                $table->boolean('show_event_time')->default(true);
                $table->boolean('show_setup_info')->default(false);
                $table->boolean('show_countdown')->default(false);
                $table->string('countdown_target_type', 64)->nullable();
                $table->dateTime('countdown_custom_at')->nullable();
                $table->dateTime('countdown_visible_from')->nullable();
                $table->string('countdown_completion_message')->nullable();
                $table->boolean('hide_countdown_after_completion')->default(true);
                $table->string('public_status_override', 64)->nullable();
                $table->text('public_announcement')->nullable();
                $table->string('publication_status', 32)->default('draft')->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('event_status_histories')) {
            Schema::create('event_status_histories', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->uuid('event_id')->index();
                $table->string('previous_status', 64)->nullable();
                $table->string('new_status', 64);
                $table->unsignedInteger('changed_by')->nullable();
                $table->text('note')->nullable();
                $table->timestamp('changed_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('event_worker_categories')) {
            Schema::create('event_worker_categories', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('code', 32)->unique();
                $table->text('description')->nullable();
                $table->unsignedInteger('default_daily_rate')->default(0);
                $table->unsignedInteger('default_hourly_rate')->nullable();
                $table->unsignedInteger('overtime_hourly_rate')->nullable();
                $table->decimal('minimum_payable_hours', 5, 2)->nullable();
                $table->unsignedSmallInteger('budget_weight')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $this->seedWorkerCategories();
        $this->seedPermissions();
    }

    private function seedWorkerCategories()
    {
        if (DB::table('event_worker_categories')->count() > 0) {
            return;
        }

        $rows = [
            ['Maneuver / Unskilled Labourer', 'maneuver', 2500],
            ['Technician', 'technician', 3500],
            ['Senior Technician', 'senior_technician', 4500],
            ['Engineer', 'engineer', 5000],
            ['Senior Engineer', 'senior_engineer', 6000],
        ];

        foreach ($rows as [$name, $code, $rate]) {
            DB::table('event_worker_categories')->insert([
                'name' => $name,
                'code' => $code,
                'default_daily_rate' => $rate,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedPermissions()
    {
        $names = [
            'events_module',
            'events.view',
            'events.create',
            'events.update',
            'events.delete',
            'events.approve',
            'events.manage_workforce',
            'events.manage_budget',
            'events.change_status',
            'events.manage_publication',
            'events.settings',
        ];

        foreach ($names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $adminRoles = Role::whereIn('id', [1, 2])->get();
        foreach ($adminRoles as $role) {
            foreach ($names as $name) {
                $perm = Permission::where('name', $name)->first();
                if ($perm && ! $role->hasPermissionTo($perm)) {
                    $role->givePermissionTo($perm);
                }
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('event_status_histories');
        Schema::dropIfExists('event_publications');
        Schema::dropIfExists('event_worker_categories');
        Schema::dropIfExists('btw_events');

        foreach ([
            'events_module', 'events.view', 'events.create', 'events.update', 'events.delete',
            'events.approve', 'events.manage_workforce', 'events.manage_budget', 'events.change_status',
            'events.manage_publication', 'events.settings',
        ] as $name) {
            Permission::where('name', $name)->delete();
        }
    }
}

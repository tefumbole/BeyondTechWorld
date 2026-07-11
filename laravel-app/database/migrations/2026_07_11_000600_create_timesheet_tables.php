<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

/**
 * Self-service staff timesheet. Entries are keyed to the Beyond portal user
 * (be_users.id) so any authenticated staff member can log daily hours without
 * requiring a linked HR staff profile.
 */
class CreateTimesheetTables extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('be_timesheet_activities')) {
            Schema::create('be_timesheet_activities', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('color', 20)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('be_timesheet_entries')) {
            Schema::create('be_timesheet_entries', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('be_user_id')->index();
                $table->uuid('activity_id')->nullable()->index();
                $table->string('activity_name')->nullable();
                $table->date('entry_date');
                $table->decimal('hours', 6, 2)->default(0);
                $table->text('notes')->nullable();
                $table->string('status', 20)->default('submitted');
                $table->timestamps();
                $table->index(['be_user_id', 'entry_date']);
            });
        }

        $this->seedActivities();
    }

    private function seedActivities()
    {
        if (DB::table('be_timesheet_activities')->count() > 0) {
            return;
        }

        foreach ([
            ['Field Installation', '#003D82'],
            ['Network Support', '#0066CC'],
            ['Software Development', '#D4AF37'],
            ['Client Meeting', '#16a34a'],
            ['Training / Learning', '#7c3aed'],
            ['Administration', '#64748b'],
        ] as [$name, $color]) {
            DB::table('be_timesheet_activities')->insert([
                'id' => (string) Str::uuid(),
                'name' => $name,
                'color' => $color,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('be_timesheet_entries');
        Schema::dropIfExists('be_timesheet_activities');
    }
}

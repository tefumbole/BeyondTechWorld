<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

class CreateTaskTables extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('task_categories')) {
            Schema::create('task_categories', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('color', 20)->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tasks')) {
            Schema::create('tasks', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('priority', 50)->default('Medium');
                $table->date('start_date')->nullable();
                $table->date('deadline')->nullable();
                $table->time('deadline_time')->nullable();
                $table->string('status', 50)->default('Pending');
                $table->uuid('created_by')->nullable();
                $table->uuid('category_id')->nullable()->index();
                $table->longText('notification_template')->nullable();
                $table->timestamps();
                $table->index('status');
                $table->index('deadline');
            });
        }

        if (! Schema::hasTable('task_assignments')) {
            Schema::create('task_assignments', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('task_id');
                $table->uuid('user_id');
                $table->string('status', 50)->default('Pending');
                $table->unsignedInteger('progress')->default(0);
                $table->longText('acceptance_signature')->nullable();
                $table->dateTime('signature_at')->nullable();
                $table->dateTime('accepted_at')->nullable();
                $table->dateTime('declined_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->dateTime('last_update_at')->nullable();
                $table->uuid('invite_token')->nullable()->index();
                $table->timestamps();
                $table->unique(['task_id', 'user_id']);
                $table->index('user_id');
                $table->index('task_id');
            });
        }

        if (! Schema::hasTable('task_updates')) {
            Schema::create('task_updates', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('assignment_id')->index();
                $table->unsignedInteger('progress')->default(0);
                $table->string('status', 50)->nullable();
                $table->text('comment')->nullable();
                $table->timestamps();
            });
        }

        $this->seedDemo();
    }

    private function seedDemo()
    {
        if (DB::table('task_categories')->count() === 0) {
            foreach ([
                ['General', '#003D82'],
                ['Field Work', '#D4AF37'],
                ['Documentation', '#0066CC'],
            ] as [$name, $color]) {
                DB::table('task_categories')->insert([
                    'id' => (string) Str::uuid(),
                    'name' => $name,
                    'color' => $color,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $portal = DB::table('be_users')->where('email', 'portal@beyondtechworld.com')->first();
        if ($portal && DB::table('tasks')->count() === 0) {
            $categoryId = DB::table('task_categories')->value('id');
            $taskId = (string) Str::uuid();
            DB::table('tasks')->insert([
                'id' => $taskId,
                'title' => 'Site Survey — Kigali Heights Network Rollout',
                'description' => 'Conduct an on-site survey for the network infrastructure rollout. Document cable runs, rack locations, and power availability.',
                'priority' => 'High',
                'start_date' => now()->toDateString(),
                'deadline' => now()->addDays(7)->toDateString(),
                'deadline_time' => '17:00:00',
                'status' => 'Pending',
                'created_by' => $portal->id,
                'category_id' => $categoryId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('task_assignments')->insert([
                'id' => (string) Str::uuid(),
                'task_id' => $taskId,
                'user_id' => $portal->id,
                'status' => 'Pending',
                'progress' => 0,
                'invite_token' => (string) Str::uuid(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('task_updates');
        Schema::dropIfExists('task_assignments');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('task_categories');
    }
}

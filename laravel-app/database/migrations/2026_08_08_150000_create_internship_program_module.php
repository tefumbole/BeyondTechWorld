<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateInternshipProgramModule extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('internship_programs')) {
            Schema::create('internship_programs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('code', 64)->index();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('version', 32)->default('1.0');
                $table->string('status', 32)->default('draft')->index(); // draft|published|archived
                $table->unsignedSmallInteger('duration_tasks')->default(180);
                $table->string('discipline', 128)->nullable();
                $table->text('prerequisites')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['code', 'version']);
            });
        }

        if (! Schema::hasTable('internship_program_tasks')) {
            Schema::create('internship_program_tasks', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('program_id')->index();
                $table->unsignedSmallInteger('day_number');
                $table->string('title');
                $table->text('objective')->nullable();
                $table->longText('instructions_json')->nullable();
                $table->longText('resources_json')->nullable();
                $table->decimal('estimated_hours', 5, 2)->default(8);
                $table->string('tools', 500)->nullable();
                $table->string('difficulty', 64)->nullable();
                $table->text('submission_requirements')->nullable();
                $table->longText('rubric_json')->nullable();
                $table->unsignedTinyInteger('pass_mark')->default(60);
                $table->boolean('requires_supervisor_approval')->default(true);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['program_id', 'day_number']);
            });
        }

        if (! Schema::hasTable('internship_enrolments')) {
            Schema::create('internship_enrolments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('student_user_id')->index();
                $table->string('application_id', 64)->nullable()->index();
                $table->unsignedBigInteger('program_id')->index();
                $table->unsignedInteger('supervisor_id')->nullable()->index();
                $table->date('start_date')->nullable();
                $table->string('status', 32)->default('pending')->index(); // pending|active|paused|completed|withdrawn
                $table->unsignedSmallInteger('current_task_order')->default(0);
                $table->unsignedSmallInteger('completed_count')->default(0);
                $table->date('last_release_date')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('internship_task_assignments')) {
            Schema::create('internship_task_assignments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('enrolment_id')->index();
                $table->unsignedBigInteger('program_task_id')->index();
                $table->unsignedSmallInteger('progression_day');
                $table->date('scheduled_work_date')->nullable()->index();
                $table->timestamp('released_at')->nullable();
                $table->string('status', 40)->default('locked')->index();
                // locked|available|in_progress|submitted|revision_required|passed|skipped
                $table->unsignedSmallInteger('attempt_count')->default(0);
                $table->timestamp('whatsapp_sent_at')->nullable();
                $table->string('whatsapp_message_id', 128)->nullable();
                $table->timestamps();
                $table->unique(['enrolment_id', 'program_task_id']);
            });
        }

        if (! Schema::hasTable('internship_submissions')) {
            Schema::create('internship_submissions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('assignment_id')->index();
                $table->unsignedInteger('student_user_id')->index();
                $table->unsignedSmallInteger('attempt_no')->default(1);
                $table->longText('description')->nullable();
                $table->string('pdf_path')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->string('status', 40)->default('submitted')->index();
                // submitted|under_review|revision_required|passed
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('internship_submission_files')) {
            Schema::create('internship_submission_files', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('submission_id')->index();
                $table->string('disk', 32)->default('local');
                $table->string('path');
                $table->string('original_name');
                $table->string('mime', 128)->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->string('checksum', 64)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('internship_grades')) {
            Schema::create('internship_grades', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('submission_id')->index();
                $table->unsignedInteger('grader_id')->index();
                $table->unsignedTinyInteger('score')->default(0);
                $table->longText('rubric_scores_json')->nullable();
                $table->longText('feedback')->nullable();
                $table->string('decision', 32)->default('pass'); // pass|revision_required
                $table->timestamp('graded_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('internship_activity_logs')) {
            Schema::create('internship_activity_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('actor_id')->nullable()->index();
                $table->unsignedBigInteger('enrolment_id')->nullable()->index();
                $table->string('event', 64)->index();
                $table->longText('old_values')->nullable();
                $table->longText('new_values')->nullable();
                $table->string('ip', 64)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('internship_notification_logs')) {
            Schema::create('internship_notification_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('idempotency_key', 191)->unique();
                $table->string('event', 64)->index();
                $table->unsignedInteger('user_id')->nullable()->index();
                $table->string('channel', 32)->default('whatsapp');
                $table->string('phone', 64)->nullable();
                $table->string('provider_message_id', 128)->nullable();
                $table->string('status', 32)->default('pending')->index();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->text('error')->nullable();
                $table->timestamps();
            });
        }

        $this->seedPermissionsAndRoles();
    }

    protected function seedPermissionsAndRoles()
    {
        $all = [
            'internship_module',
            'internship.dashboard.view',
            'internship.programs.view',
            'internship.programs.create',
            'internship.programs.update',
            'internship.programs.publish',
            'internship.programs.archive',
            'internship.programs.import',
            'internship.enrolments.view',
            'internship.enrolments.create',
            'internship.enrolments.update',
            'internship.enrolments.assign_supervisor',
            'internship.enrolments.pause',
            'internship.enrolments.resume',
            'internship.tasks.view',
            'internship.tasks.create',
            'internship.tasks.update',
            'internship.tasks.reorder',
            'internship.submissions.view',
            'internship.submissions.grade',
            'internship.submissions.request_revision',
            'internship.reports.view',
            'internship.reports.export',
            'internship.notifications.retry',
            'internship.settings.manage',
            'internship.student',
            'internship.supervise',
        ];

        foreach ($all as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (Role::whereIn('id', [1, 2])->get() as $role) {
            foreach ($all as $name) {
                try {
                    $role->givePermissionTo($name);
                } catch (\Exception $e) {
                }
            }
        }

        // Internship Administrator — curriculum/enrolment/grade oversight (no finance)
        $adminPerms = array_values(array_filter($all, function ($p) {
            return $p !== 'internship.student';
        }));
        $internshipAdmin = $this->ensureRole('Internship Administrator');
        foreach ($adminPerms as $name) {
            try {
                $internshipAdmin->givePermissionTo($name);
            } catch (\Exception $e) {
            }
        }

        // Intern — limited ERP + student internship
        $internPerms = [
            'products-index',
            'sales-index',
            'sales-add',
            'booking_module',
            'booking_create',
            'timesheets_module',
            'timesheets.employee',
            'internship_module',
            'internship.dashboard.view',
            'internship.student',
        ];
        foreach ($internPerms as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $intern = $this->ensureRole('Intern');
        foreach ($internPerms as $name) {
            try {
                $intern->givePermissionTo($name);
            } catch (\Exception $e) {
            }
        }

        // Supervisor helper role
        $supervisorPerms = [
            'internship_module',
            'internship.dashboard.view',
            'internship.supervise',
            'internship.submissions.view',
            'internship.submissions.grade',
            'internship.submissions.request_revision',
            'internship.enrolments.view',
            'internship.reports.view',
            'timesheets_module',
            'timesheets.employee',
        ];
        $supervisor = $this->ensureRole('Internship Supervisor');
        foreach ($supervisorPerms as $name) {
            try {
                $supervisor->givePermissionTo($name);
            } catch (\Exception $e) {
            }
        }
    }

    protected function ensureRole($name)
    {
        $role = Role::where('name', $name)->where('guard_name', 'web')->first();
        if ($role) {
            return $role;
        }

        // SalePro roles table may require is_active
        try {
            return Role::create(['name' => $name, 'guard_name' => 'web', 'is_active' => 1]);
        } catch (\Exception $e) {
            try {
                DB::table('roles')->insert([
                    'name' => $name,
                    'guard_name' => 'web',
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Exception $e2) {
                DB::table('roles')->insert([
                    'name' => $name,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return Role::where('name', $name)->where('guard_name', 'web')->first();
        }
    }

    public function down()
    {
        Schema::dropIfExists('internship_notification_logs');
        Schema::dropIfExists('internship_activity_logs');
        Schema::dropIfExists('internship_grades');
        Schema::dropIfExists('internship_submission_files');
        Schema::dropIfExists('internship_submissions');
        Schema::dropIfExists('internship_task_assignments');
        Schema::dropIfExists('internship_enrolments');
        Schema::dropIfExists('internship_program_tasks');
        Schema::dropIfExists('internship_programs');
    }
}

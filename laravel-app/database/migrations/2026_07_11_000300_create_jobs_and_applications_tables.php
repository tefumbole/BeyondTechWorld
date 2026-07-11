<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

class CreateJobsAndApplicationsTables extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('job_postings')) {
            Schema::create('job_postings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('location')->nullable();
                $table->string('type', 100)->nullable();
                $table->string('employment_type', 100)->nullable();
                $table->string('department')->nullable();
                $table->string('salary')->nullable();
                $table->text('min_requirements')->nullable();
                $table->text('requirements')->nullable();
                $table->text('qualifications')->nullable();
                $table->text('responsibilities')->nullable();
                $table->dateTime('deadline')->nullable();
                $table->unsignedInteger('max_positions')->default(1);
                $table->unsignedInteger('max_applicants')->nullable();
                $table->unsignedInteger('expected_applicants')->default(50);
                $table->boolean('enable_countdown')->default(true);
                $table->unsignedInteger('current_applicants')->default(0);
                $table->string('status', 50)->default('active');
                $table->dateTime('posted_at')->nullable();
                $table->dateTime('expires_at')->nullable();
                $table->timestamps();
                $table->index('status');
            });
        }

        if (! Schema::hasTable('applications')) {
            Schema::create('applications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('job_id')->nullable()->index();
                $table->uuid('user_id')->nullable()->index();
                $table->string('full_name');
                $table->string('email');
                $table->string('phone', 50)->nullable();
                $table->string('country', 100)->nullable();
                $table->text('cover_letter')->nullable();
                $table->string('expected_salary')->nullable();
                $table->string('availability', 50)->nullable();
                $table->unsignedInteger('availability_days')->nullable();
                $table->text('cv_url')->nullable();
                $table->text('cv_path')->nullable();
                $table->string('status', 50)->default('new');
                $table->string('reference_number', 100)->nullable()->unique();
                $table->text('rejection_reason')->nullable();
                $table->dateTime('interview_date')->nullable();
                $table->dateTime('submitted_at')->nullable();
                $table->timestamps();
                $table->index('email');
            });
        }

        $this->seedJobs();
    }

    private function seedJobs()
    {
        if (DB::table('job_postings')->count() > 0) {
            return;
        }

        $now = now();
        $samples = [
            [
                'title' => 'Network Infrastructure Engineer',
                'description' => 'Design, deploy, and maintain enterprise network infrastructure for our clients across Kigali. Work hands-on with routers, switches, firewalls, and structured cabling.',
                'location' => 'Kigali, Rwanda',
                'department' => 'Engineering',
                'employment_type' => 'Full-Time',
                'type' => 'Full-Time',
                'salary' => '600000-900000',
                'requirements' => "CCNA or equivalent certification\n3+ years hands-on networking experience\nStrong knowledge of TCP/IP, VLANs, routing protocols",
                'responsibilities' => "Install and configure network hardware\nMonitor network performance and troubleshoot issues\nDocument network topology and maintain records",
                'max_positions' => 2,
                'expected_applicants' => 40,
            ],
            [
                'title' => 'CCTV & Security Systems Technician',
                'description' => 'Install and service CCTV, access control, and alarm systems for commercial and residential clients. Provide on-site support and client training.',
                'location' => 'Kigali, Rwanda',
                'department' => 'Field Services',
                'employment_type' => 'Full-Time',
                'type' => 'Full-Time',
                'salary' => '400000-650000',
                'requirements' => "Experience with IP camera systems\nComfortable working at heights and on-site\nValid driving license is a plus",
                'responsibilities' => "Install cameras, DVR/NVR, and cabling\nConfigure remote monitoring\nPerform preventive maintenance",
                'max_positions' => 3,
                'expected_applicants' => 60,
            ],
            [
                'title' => 'IT Support & Training Assistant',
                'description' => 'Support our training programs and provide first-line IT support to students and staff. Great entry-level role for a motivated tech enthusiast.',
                'location' => 'Kigali, Rwanda (Hybrid)',
                'department' => 'Training',
                'employment_type' => 'Part-Time',
                'type' => 'Part-Time',
                'salary' => '250000-400000',
                'requirements' => "Diploma in IT or related field\nGood communication skills\nPatience and a passion for teaching",
                'responsibilities' => "Assist trainers during sessions\nSet up lab equipment\nRespond to student support requests",
                'max_positions' => 1,
                'expected_applicants' => 80,
            ],
        ];

        foreach ($samples as $job) {
            DB::table('job_postings')->insert(array_merge([
                'id' => (string) Str::uuid(),
                'enable_countdown' => true,
                'current_applicants' => 0,
                'status' => 'active',
                'deadline' => $now->copy()->addDays(30),
                'posted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ], $job));
        }
    }

    public function down()
    {
        Schema::dropIfExists('applications');
        Schema::dropIfExists('job_postings');
    }
}

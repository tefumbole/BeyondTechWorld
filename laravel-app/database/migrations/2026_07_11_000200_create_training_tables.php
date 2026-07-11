<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTrainingTables extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('courses')) {
            Schema::create('courses', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->unsignedInteger('legacy_id')->nullable()->index();
                $table->string('name');
                $table->string('slug')->nullable();
                $table->text('description')->nullable();
                $table->decimal('price', 12, 2)->default(0);
                $table->string('duration', 100)->nullable();
                $table->string('delivery_mode')->nullable();
                $table->string('category', 100)->nullable();
                $table->longText('curriculum_json')->nullable();
                $table->string('icon', 50)->nullable();
                $table->string('color', 20)->nullable();
                $table->integer('sort_order')->default(0);
                $table->string('status', 50)->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('registrations')) {
            Schema::create('registrations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('reference_number', 100)->nullable()->unique();
                $table->string('client_name');
                $table->string('client_email')->nullable();
                $table->string('client_phone', 50)->nullable();
                $table->string('company_name')->nullable();
                $table->longText('course_ids')->nullable();
                $table->text('course_names')->nullable();
                $table->decimal('total_price', 14, 2)->nullable();
                $table->string('status', 50)->default('pending');
                $table->string('payment_status', 50)->default('pending');
                $table->uuid('user_id')->nullable()->index();
                $table->timestamps();
                $table->index('client_email');
            });
        }

        if (! Schema::hasTable('student_progress')) {
            Schema::create('student_progress', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('registration_id')->index();
                $table->uuid('course_id')->nullable()->index();
                $table->string('course_name')->nullable();
                $table->decimal('progress_percentage', 5, 2)->default(0);
                $table->string('status', 50)->default('not_started');
                $table->dateTime('start_date')->nullable();
                $table->dateTime('completion_date')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('course_feedback')) {
            Schema::create('course_feedback', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('registration_id')->nullable()->index();
                $table->uuid('course_id')->nullable()->index();
                $table->string('student_name')->nullable();
                $table->unsignedTinyInteger('rating')->default(5);
                $table->text('feedback_text')->nullable();
                $table->string('status', 50)->default('pending');
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('course_feedback');
        Schema::dropIfExists('student_progress');
        Schema::dropIfExists('registrations');
        Schema::dropIfExists('courses');
    }
}

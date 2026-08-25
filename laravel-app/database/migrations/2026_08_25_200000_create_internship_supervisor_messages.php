<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInternshipSupervisorMessages extends Migration
{
    public function up()
    {
        if (Schema::hasTable('internship_supervisor_messages')) {
            return;
        }

        Schema::create('internship_supervisor_messages', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('enrolment_id')->index();
            $table->unsignedInteger('student_user_id')->index();
            $table->string('supervisor_name')->nullable();
            $table->string('supervisor_phone', 40)->nullable();
            $table->text('body');
            $table->string('reply_token', 64)->unique();
            $table->text('reply_body')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('internship_supervisor_messages');
    }
}

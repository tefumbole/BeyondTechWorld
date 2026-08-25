<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInternshipDraftFiles extends Migration
{
    public function up()
    {
        if (Schema::hasTable('internship_draft_files')) {
            return;
        }

        Schema::create('internship_draft_files', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('assignment_id')->index();
            $table->unsignedInteger('student_user_id')->index();
            $table->unsignedSmallInteger('slot_index')->default(0);
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 128)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->string('caption', 400)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('internship_draft_files');
    }
}

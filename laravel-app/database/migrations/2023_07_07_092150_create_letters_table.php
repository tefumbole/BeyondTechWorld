<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLettersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('letters', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('category_id')->nullable();
            $table->integer('template_id')->nullable();
            $table->string('reference');
            $table->string('name');
            $table->text('to');
            $table->text('cc')->nullable();
            $table->text('header')->nullable();
            $table->text('subject');
            $table->text('body')->nullable();
            $table->text('footer')->nullable();
            $table->text('attachment')->nullable();
            $table->boolean('is_approve')->nullable();
            $table->integer('approved_by')->nullable();
            $table->boolean('is_sign')->nullable();
            $table->integer('signed_by')->nullable();
            $table->boolean('is_sent')->default(0);
            $table->integer('sent_by')->nullable();
            $table->boolean('is_edit')->default(0);
            $table->integer('edit_by')->nullable();
            $table->boolean('is_rejected')->default(0);
            $table->integer('reject_by')->nullable();
            $table->boolean('is_active');
            $table->integer('created_by');
            $table->string('otp')->nullable();
            $table->dateTime('otp_time')->nullable();
            $table->enum('people_type',['user','customer'])->default('user');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('letters');
    }
}

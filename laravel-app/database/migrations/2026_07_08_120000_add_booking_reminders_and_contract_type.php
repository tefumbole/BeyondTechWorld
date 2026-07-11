<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBookingRemindersAndContractType extends Migration
{
    public function up()
    {
        Schema::table('booking_contracts', function (Blueprint $table) {
            $table->string('contract_type', 32)->default('equipment')->after('booking_id');
        });

        Schema::create('booking_reminders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('booking_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->timestamp('remind_at');
            $table->timestamp('sent_at')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('booking_reminders');

        Schema::table('booking_contracts', function (Blueprint $table) {
            $table->dropColumn('contract_type');
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContractRemindersTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('contract_reminders')) {
            Schema::create('contract_reminders', function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->string('contract_id', 36)->index();
                $table->timestamp('reminder_time')->index();
                $table->string('label')->nullable();
                $table->text('message')->nullable();
                $table->boolean('is_sent')->default(false)->index();
                $table->timestamp('sent_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('contract_reminders');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSharedMessageSerialsTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('shared_message_serials')) {
            Schema::create('shared_message_serials', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('reference', 80)->unique();
                $table->string('source', 40)->default('whatsapp')->index();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('shared_message_serials');
    }
}

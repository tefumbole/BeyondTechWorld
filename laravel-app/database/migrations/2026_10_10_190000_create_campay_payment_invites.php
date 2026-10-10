<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCampayPaymentInvites extends Migration
{
    public function up()
    {
        if (Schema::hasTable('campay_payment_invites')) {
            return;
        }

        Schema::create('campay_payment_invites', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->string('reason', 191)->nullable();
            $table->text('people');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('campay_payment_invites');
    }
}

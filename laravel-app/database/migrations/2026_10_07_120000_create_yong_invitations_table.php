<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateYongInvitationsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('yong_invitations')) {
            return;
        }

        Schema::create('yong_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('phone', 50);
            $table->string('name', 120);
            $table->string('position', 20)->default('guest');
            $table->unsignedInteger('pledge_amount')->nullable();
            $table->string('invitation_type', 20);
            $table->string('image_file', 191);
            $table->string('pdf_file', 191)->nullable();
            $table->string('payment_status', 20)->nullable();
            $table->string('campay_reference', 191)->nullable();
            $table->string('stripe_session_id', 191)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index('phone');
        });
    }

    public function down()
    {
        Schema::dropIfExists('yong_invitations');
    }
}

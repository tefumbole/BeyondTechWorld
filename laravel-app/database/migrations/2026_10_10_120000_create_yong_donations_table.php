<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateYongDonationsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('yong_donations')) {
            return;
        }

        Schema::create('yong_donations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('phone', 50);
            $table->string('name', 120);
            $table->string('name_source', 20)->nullable();
            $table->unsignedInteger('amount');
            $table->string('method', 20);
            $table->string('status', 20)->default('pending');
            $table->string('campay_reference', 191)->nullable();
            $table->string('stripe_session_id', 191)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index('phone');
        });
    }

    public function down()
    {
        Schema::dropIfExists('yong_donations');
    }
}

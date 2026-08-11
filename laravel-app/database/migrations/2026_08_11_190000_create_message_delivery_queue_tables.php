<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMessageDeliveryQueueTables extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('message_delivery_batches')) {
            Schema::create('message_delivery_batches', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->uuid('uuid')->unique();
                $table->string('type', 40)->default('letter');
                $table->unsignedBigInteger('letter_id')->nullable()->index();
                $table->string('title')->nullable();
                $table->string('status', 20)->default('queued')->index(); // queued|sending|completed|partial|failed
                $table->unsignedInteger('total')->default(0);
                $table->unsignedInteger('sent_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->unsignedBigInteger('queued_by')->nullable()->index();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('message_delivery_items')) {
            Schema::create('message_delivery_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('batch_id')->index();
                $table->string('recipient_ref', 120)->nullable()->index();
                $table->string('recipient_name')->nullable();
                $table->string('phone', 40)->nullable();
                $table->string('email')->nullable();
                $table->string('channel', 40)->default('whatsapp_pdf'); // whatsapp_pdf|whatsapp_text|email
                $table->string('role', 20)->default('to'); // to|cc
                $table->string('status', 20)->default('queued')->index(); // queued|sending|sent|failed|skipped
                $table->string('provider_message_id')->nullable();
                $table->text('error')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->foreign('batch_id')
                    ->references('id')
                    ->on('message_delivery_batches')
                    ->onDelete('cascade');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('message_delivery_items');
        Schema::dropIfExists('message_delivery_batches');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDocumentPaymentLinks extends Migration
{
    public function up()
    {
        if (Schema::hasTable('document_payment_links')) {
            return;
        }

        Schema::create('document_payment_links', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->string('kind', 20);
            $table->unsignedInteger('document_id');
            $table->string('token', 64)->unique();
            $table->string('reference_no', 64)->nullable();
            $table->string('person_name', 191)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('requested_by', 191)->nullable();
            $table->unsignedInteger('amount');
            $table->string('method', 20)->nullable();
            $table->string('status', 20)->default('waiting');
            $table->string('campay_reference', 64)->nullable();
            $table->text('payment_link')->nullable();
            $table->string('error', 250)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('document_payment_links');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSmsProviderReconciliation extends Migration
{
    public function up()
    {
        Schema::table('cloud_sms_messages', function (Blueprint $table) {
            $table->unsignedSmallInteger('provider_units')->nullable();
        });
        Schema::table('cloud_sms_connections', function (Blueprint $table) {
            $table->string('health_status', 32)->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamp('last_receipt_at')->nullable();
        });
    }

    public function down()
    {
        Schema::table('cloud_sms_messages', function (Blueprint $table) {
            $table->dropColumn('provider_units');
        });
        Schema::table('cloud_sms_connections', function (Blueprint $table) {
            $table->dropColumn(['health_status', 'last_success_at', 'last_failure_at', 'last_receipt_at']);
        });
    }
}

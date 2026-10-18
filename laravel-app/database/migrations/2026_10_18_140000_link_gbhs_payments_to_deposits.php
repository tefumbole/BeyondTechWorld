<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class LinkGbhsPaymentsToDeposits extends Migration
{
    public function up()
    {
        Schema::table('gbhs_payments', function (Blueprint $table) {
            $table->unsignedInteger('deposit_id')->nullable()->index()->after('provider_reference');
        });
    }

    public function down()
    {
        Schema::table('gbhs_payments', function (Blueprint $table) {
            $table->dropColumn('deposit_id');
        });
    }
}

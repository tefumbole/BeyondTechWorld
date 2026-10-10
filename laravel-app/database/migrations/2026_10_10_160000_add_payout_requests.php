<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPayoutRequests extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('campay_payout_links')) {
            Schema::create('campay_payout_links', function (Blueprint $table) {
                $table->increments('id');
                $table->string('token', 64)->unique();
                $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('campay_payout_requests')) {
            Schema::create('campay_payout_requests', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
                $table->string('requester_name', 80);
                $table->string('note', 191)->nullable();
                $table->string('status', 32)->default('pending');
                $table->timestamps();
            });
        }
        if (Schema::hasTable('campay_payouts') && ! Schema::hasColumn('campay_payouts', 'request_id')) {
            Schema::table('campay_payouts', function (Blueprint $table) {
                $table->unsignedInteger('request_id')->nullable()->index();
                $table->string('momo_name', 191)->nullable();
                $table->boolean('momo_checked')->default(false);
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('campay_payouts') && Schema::hasColumn('campay_payouts', 'request_id')) {
            Schema::table('campay_payouts', function (Blueprint $table) {
                $table->dropColumn(['request_id', 'momo_name', 'momo_checked']);
            });
        }
        Schema::dropIfExists('campay_payout_requests');
        Schema::dropIfExists('campay_payout_links');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFuneralEulogiesAndStripe extends Migration
{
    public function up()
    {
        if (Schema::hasTable('funeral_pledges') && ! Schema::hasColumn('funeral_pledges', 'stripe_session_id')) {
            Schema::table('funeral_pledges', function (Blueprint $table) {
                $table->string('stripe_session_id', 120)->nullable()->after('campay_reference');
            });
        }

        if (! Schema::hasTable('funeral_eulogies')) {
            Schema::create('funeral_eulogies', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('campaign_id');
                $table->unsignedInteger('customer_id')->nullable();
                $table->string('name');
                $table->string('phone', 32);
                $table->text('body');
                $table->string('signature_path')->nullable();
                $table->timestamps();
                $table->index('campaign_id');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('funeral_eulogies');
        if (Schema::hasTable('funeral_pledges') && Schema::hasColumn('funeral_pledges', 'stripe_session_id')) {
            Schema::table('funeral_pledges', function (Blueprint $table) {
                $table->dropColumn('stripe_session_id');
            });
        }
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddExpectedOnToTravelParcels extends Migration
{
    public function up()
    {
        Schema::table('travel_parcels', function (Blueprint $table) {
            $table->date('expected_on')->nullable()->after('destination_station_id');
        });
    }

    public function down()
    {
        Schema::table('travel_parcels', function (Blueprint $table) {
            $table->dropColumn('expected_on');
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HotspotRouterTunnelOptional extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('hotspot_routers')) {
            return;
        }
        DB::statement('ALTER TABLE hotspot_routers MODIFY api_host VARCHAR(255) NULL');
    }

    public function down()
    {
        if (! Schema::hasTable('hotspot_routers')) {
            return;
        }
        DB::table('hotspot_routers')->whereNull('api_host')->update(['api_host' => '']);
        DB::statement("ALTER TABLE hotspot_routers MODIFY api_host VARCHAR(255) NOT NULL DEFAULT ''");
    }
}

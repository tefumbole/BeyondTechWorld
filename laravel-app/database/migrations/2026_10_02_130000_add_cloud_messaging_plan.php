<?php

use App\Services\Cloud\CloudCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddCloudMessagingPlan extends Migration
{
    public function up()
    {
        CloudCatalog::install();
    }

    public function down()
    {
        $module = DB::table('cloud_modules')->where('code', 'MESSAGING')->first();
        if (! $module) {
            return;
        }
        DB::table('cloud_plans')->where('code', 'MESSAGING_MONTHLY')->delete();
        DB::table('cloud_modules')->where('id', $module->id)->delete();
    }
}

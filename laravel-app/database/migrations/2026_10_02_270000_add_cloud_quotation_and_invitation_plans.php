<?php

use App\Services\Cloud\CloudCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddCloudQuotationAndInvitationPlans extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('cloud_modules')) {
            return;
        }
        CloudCatalog::install();
    }

    public function down()
    {
        foreach (['QUOTATIONS_MONTHLY', 'DIGITAL_INVITATIONS_MONTHLY'] as $code) {
            DB::table('cloud_plans')->where('code', $code)->delete();
        }
        DB::table('cloud_modules')->whereIn('code', ['QUOTATIONS', 'DIGITAL_INVITATIONS'])->delete();
    }
}

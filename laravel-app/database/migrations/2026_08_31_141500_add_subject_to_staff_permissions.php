<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSubjectToStaffPermissions extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('staff_permissions')) {
            return;
        }
        if (! Schema::hasColumn('staff_permissions', 'subject')) {
            Schema::table('staff_permissions', function (Blueprint $table) {
                $table->string('subject', 255)->nullable()->after('company_role');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('staff_permissions') && Schema::hasColumn('staff_permissions', 'subject')) {
            Schema::table('staff_permissions', function (Blueprint $table) {
                $table->dropColumn('subject');
            });
        }
    }
}

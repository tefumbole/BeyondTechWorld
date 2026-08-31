<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLetterFieldsToStaffPermissions extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('staff_permissions')) {
            return;
        }

        Schema::table('staff_permissions', function (Blueprint $table) {
            if (! Schema::hasColumn('staff_permissions', 'letter_id')) {
                $table->unsignedInteger('letter_id')->nullable()->after('reference_number');
            }
            if (! Schema::hasColumn('staff_permissions', 'instructions')) {
                $table->text('instructions')->nullable()->after('admin_note');
            }
            if (! Schema::hasColumn('staff_permissions', 'letter_footer')) {
                $table->text('letter_footer')->nullable()->after('instructions');
            }
        });
    }

    public function down()
    {
        if (! Schema::hasTable('staff_permissions')) {
            return;
        }

        Schema::table('staff_permissions', function (Blueprint $table) {
            if (Schema::hasColumn('staff_permissions', 'letter_id')) {
                $table->dropColumn('letter_id');
            }
            if (Schema::hasColumn('staff_permissions', 'instructions')) {
                $table->dropColumn('instructions');
            }
            if (Schema::hasColumn('staff_permissions', 'letter_footer')) {
                $table->dropColumn('letter_footer');
            }
        });
    }
}

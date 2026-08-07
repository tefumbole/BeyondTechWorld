<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddDirectoryRecipientsToLetters extends Migration
{
    public function up()
    {
        Schema::table('letters', function (Blueprint $table) {
            if (! Schema::hasColumn('letters', 'recipients_json')) {
                $table->longText('recipients_json')->nullable()->after('cc');
            }
            if (! Schema::hasColumn('letters', 'cc_json')) {
                $table->longText('cc_json')->nullable()->after('recipients_json');
            }
        });

        try {
            DB::statement("ALTER TABLE letters MODIFY people_type ENUM('user','customer','csv','all','directory') NOT NULL DEFAULT 'user'");
        } catch (\Throwable $e) {
            // SQLite / already migrated — ignore.
        }
    }

    public function down()
    {
        Schema::table('letters', function (Blueprint $table) {
            if (Schema::hasColumn('letters', 'cc_json')) {
                $table->dropColumn('cc_json');
            }
            if (Schema::hasColumn('letters', 'recipients_json')) {
                $table->dropColumn('recipients_json');
            }
        });

        try {
            DB::statement("ALTER TABLE letters MODIFY people_type ENUM('user','customer','csv','all') NOT NULL DEFAULT 'user'");
        } catch (\Throwable $e) {
            // ignore
        }
    }
}

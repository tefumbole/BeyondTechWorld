<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSelfieToFuneralEulogies extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('funeral_eulogies') || Schema::hasColumn('funeral_eulogies', 'selfie_path')) {
            return;
        }

        Schema::table('funeral_eulogies', function (Blueprint $table) {
            $table->string('selfie_path')->nullable()->after('signature_path');
        });
    }

    public function down()
    {
        if (! Schema::hasTable('funeral_eulogies') || ! Schema::hasColumn('funeral_eulogies', 'selfie_path')) {
            return;
        }

        Schema::table('funeral_eulogies', function (Blueprint $table) {
            $table->dropColumn('selfie_path');
        });
    }
}

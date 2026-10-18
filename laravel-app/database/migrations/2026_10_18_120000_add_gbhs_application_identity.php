<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGbhsApplicationIdentity extends Migration
{
    public function up()
    {
        Schema::table('gbhs_applications', function (Blueprint $table) {
            $table->string('employment_status')->nullable()->after('profession');
            $table->string('id_type')->nullable()->after('address');
            $table->string('id_holder_name')->nullable()->after('id_type');
            $table->string('id_number')->nullable()->after('id_holder_name');
            $table->date('id_issued_on')->nullable()->after('id_number');
            $table->string('id_step')->nullable()->after('id_issued_on');
            $table->string('photo_step')->nullable()->after('photo');
        });
    }

    public function down()
    {
        Schema::table('gbhs_applications', function (Blueprint $table) {
            $table->dropColumn([
                'employment_status', 'id_type', 'id_holder_name', 'id_number',
                'id_issued_on', 'id_step', 'photo_step',
            ]);
        });
    }
}

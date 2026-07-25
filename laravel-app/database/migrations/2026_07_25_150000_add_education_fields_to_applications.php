<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEducationFieldsToApplications extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('applications')) {
            return;
        }

        Schema::table('applications', function (Blueprint $table) {
            if (! Schema::hasColumn('applications', 'school')) {
                $table->string('school', 255)->nullable()->after('country');
            }
            if (! Schema::hasColumn('applications', 'level_of_study')) {
                $table->string('level_of_study', 100)->nullable()->after('school');
            }
            if (! Schema::hasColumn('applications', 'education_status')) {
                // currently_studying | graduated
                $table->string('education_status', 40)->nullable()->after('level_of_study');
            }
            if (! Schema::hasColumn('applications', 'is_academic_required')) {
                // Academic-required internship vs voluntary
                $table->boolean('is_academic_required')->nullable()->after('education_status');
            }
        });
    }

    public function down()
    {
        if (! Schema::hasTable('applications')) {
            return;
        }

        Schema::table('applications', function (Blueprint $table) {
            foreach (['school', 'level_of_study', 'education_status', 'is_academic_required'] as $col) {
                if (Schema::hasColumn('applications', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EnablePaidInternship extends Migration
{
    public function up()
    {
        if (Schema::hasTable('applications')) {
            if (! Schema::hasColumn('applications', 'internship_fee_amount')) {
                Schema::table('applications', function (Blueprint $table) {
                    $table->unsignedInteger('internship_fee_amount')->nullable();
                });
            }
            if (! Schema::hasColumn('applications', 'internship_fee_status')) {
                Schema::table('applications', function (Blueprint $table) {
                    $table->string('internship_fee_status', 20)->nullable();
                });
            }
            if (! Schema::hasColumn('applications', 'internship_fee_reference')) {
                Schema::table('applications', function (Blueprint $table) {
                    $table->string('internship_fee_reference', 80)->nullable();
                });
            }
            if (! Schema::hasColumn('applications', 'internship_fee_external')) {
                Schema::table('applications', function (Blueprint $table) {
                    $table->string('internship_fee_external', 80)->nullable();
                });
            }
        }

        if (Schema::hasTable('job_postings') && Schema::hasColumn('job_postings', 'posting_type')) {
            DB::table('job_postings')
                ->where('posting_type', 'internship')
                ->update([
                    'status' => 'active',
                    'deadline' => '2026-11-30 23:59:59',
                    'expires_at' => '2026-11-30 23:59:59',
                    'enable_countdown' => 1,
                    'salary' => 'Paid internship',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        }
    }

    public function down()
    {
    }
}

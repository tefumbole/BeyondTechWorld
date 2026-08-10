<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddOfferPortalAndProgramCapacity extends Migration
{
    public function up()
    {
        if (Schema::hasTable('applications')) {
            Schema::table('applications', function (Blueprint $table) {
                if (! Schema::hasColumn('applications', 'working_week_json')) {
                    $table->longText('working_week_json')->nullable()->after('internship_duration_days');
                }
                if (! Schema::hasColumn('applications', 'offer_accepted_at')) {
                    $table->timestamp('offer_accepted_at')->nullable()->after('agreement_signed_at');
                }
                if (! Schema::hasColumn('applications', 'offer_flow_version')) {
                    $table->unsignedTinyInteger('offer_flow_version')->default(1)->after('offer_accepted_at');
                }
            });

            // Legacy applications: treat as offer already accepted (no portal).
            DB::table('applications')
                ->whereIn('status', ['selected', 'shortlisted', 'hired'])
                ->whereNull('offer_accepted_at')
                ->update([
                    'offer_flow_version' => 0,
                    'offer_accepted_at' => DB::raw('COALESCE(agreement_signed_at, submitted_at, NOW())'),
                ]);

            // Existing awaiting apps also stay on legacy (no WW on apply yet).
            DB::table('applications')
                ->whereIn('status', ['awaiting_approval', 'new', 'reviewed', 'interview', 'pending', 'rejected', 'withdrawn'])
                ->whereNull('offer_accepted_at')
                ->update(['offer_flow_version' => 0]);
        }

        if (Schema::hasTable('internship_programs') && ! Schema::hasColumn('internship_programs', 'max_students')) {
            Schema::table('internship_programs', function (Blueprint $table) {
                $table->unsignedInteger('max_students')->nullable()->after('duration_tasks');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('applications')) {
            Schema::table('applications', function (Blueprint $table) {
                foreach (['working_week_json', 'offer_accepted_at', 'offer_flow_version'] as $col) {
                    if (Schema::hasColumn('applications', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
        if (Schema::hasTable('internship_programs') && Schema::hasColumn('internship_programs', 'max_students')) {
            Schema::table('internship_programs', function (Blueprint $table) {
                $table->dropColumn('max_students');
            });
        }
    }
}

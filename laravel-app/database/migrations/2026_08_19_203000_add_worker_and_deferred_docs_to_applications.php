<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWorkerAndDeferredDocsToApplications extends Migration
{
    public function up()
    {
        Schema::table('applications', function (Blueprint $table) {
            if (! Schema::hasColumn('applications', 'applicant_type')) {
                $table->string('applicant_type', 20)->nullable()->after('education_status');
            }
            if (! Schema::hasColumn('applications', 'employment_letter_path')) {
                $table->string('employment_letter_path')->nullable()->after('internship_letter_path');
            }
            if (! Schema::hasColumn('applications', 'official_badge_path')) {
                $table->string('official_badge_path')->nullable()->after('employment_letter_path');
            }
            if (! Schema::hasColumn('applications', 'deferred_documents')) {
                $table->text('deferred_documents')->nullable()->after('official_badge_path');
            }
            if (! Schema::hasColumn('applications', 'documents_status')) {
                $table->string('documents_status', 32)->nullable()->after('deferred_documents');
            }
            if (! Schema::hasColumn('applications', 'documents_update_token')) {
                $table->string('documents_update_token', 64)->nullable()->unique()->after('documents_status');
            }
            if (! Schema::hasColumn('applications', 'documents_requested_at')) {
                $table->timestamp('documents_requested_at')->nullable()->after('documents_update_token');
            }
            if (! Schema::hasColumn('applications', 'documents_request_note')) {
                $table->text('documents_request_note')->nullable()->after('documents_requested_at');
            }
        });
    }

    public function down()
    {
        Schema::table('applications', function (Blueprint $table) {
            foreach ([
                'applicant_type',
                'employment_letter_path',
                'official_badge_path',
                'deferred_documents',
                'documents_status',
                'documents_update_token',
                'documents_requested_at',
                'documents_request_note',
            ] as $col) {
                if (Schema::hasColumn('applications', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}

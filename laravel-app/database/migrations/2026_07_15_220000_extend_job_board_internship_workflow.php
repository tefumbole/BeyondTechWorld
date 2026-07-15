<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExtendJobBoardInternshipWorkflow extends Migration
{
    public function up()
    {
        if (Schema::hasTable('job_postings') && ! Schema::hasColumn('job_postings', 'posting_type')) {
            Schema::table('job_postings', function (Blueprint $table) {
                $table->string('posting_type', 32)->default('job')->after('employment_type');
            });
        }

        if (Schema::hasTable('applications')) {
            Schema::table('applications', function (Blueprint $table) {
                if (! Schema::hasColumn('applications', 'whatsapp_number')) {
                    $table->string('whatsapp_number', 50)->nullable()->after('phone');
                }
                if (! Schema::hasColumn('applications', 'student_id_path')) {
                    $table->string('student_id_path')->nullable()->after('cv_path');
                }
                if (! Schema::hasColumn('applications', 'internship_letter_path')) {
                    $table->string('internship_letter_path')->nullable()->after('student_id_path');
                }
                if (! Schema::hasColumn('applications', 'selfie_path')) {
                    $table->string('selfie_path')->nullable()->after('internship_letter_path');
                }
                if (! Schema::hasColumn('applications', 'signature_image')) {
                    $table->longText('signature_image')->nullable()->after('selfie_path');
                }
                if (! Schema::hasColumn('applications', 'agreement_token')) {
                    $table->string('agreement_token', 64)->nullable()->unique()->after('signature_image');
                }
                if (! Schema::hasColumn('applications', 'agreement_sent_at')) {
                    $table->timestamp('agreement_sent_at')->nullable()->after('agreement_token');
                }
                if (! Schema::hasColumn('applications', 'agreement_signed_at')) {
                    $table->timestamp('agreement_signed_at')->nullable()->after('agreement_sent_at');
                }
                if (! Schema::hasColumn('applications', 'agreement_signature_image')) {
                    $table->longText('agreement_signature_image')->nullable()->after('agreement_signed_at');
                }
            });
        }

        // Classify obvious internships and normalize legacy application statuses.
        if (Schema::hasColumn('job_postings', 'posting_type')) {
            DB::table('job_postings')
                ->where(function ($q) {
                    $q->where('title', 'like', '%intern%')
                        ->orWhere('employment_type', 'like', '%intern%')
                        ->orWhere('type', 'like', '%intern%');
                })
                ->update(['posting_type' => 'internship']);
        }

        if (Schema::hasColumn('applications', 'status')) {
            DB::table('applications')->whereIn('status', ['new', 'reviewed', 'interview'])->update(['status' => 'awaiting_approval']);
            DB::table('applications')->whereIn('status', ['shortlisted'])->update(['status' => 'selected']);
            DB::table('applications')->where('status', 'withdrawn')->update(['status' => 'rejected']);
        }
    }

    public function down()
    {
        if (Schema::hasTable('applications')) {
            Schema::table('applications', function (Blueprint $table) {
                foreach ([
                    'whatsapp_number', 'student_id_path', 'internship_letter_path', 'selfie_path',
                    'signature_image', 'agreement_token', 'agreement_sent_at', 'agreement_signed_at',
                    'agreement_signature_image',
                ] as $col) {
                    if (Schema::hasColumn('applications', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('job_postings') && Schema::hasColumn('job_postings', 'posting_type')) {
            Schema::table('job_postings', function (Blueprint $table) {
                $table->dropColumn('posting_type');
            });
        }
    }
}

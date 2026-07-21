<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddClientApprovalToQuotations extends Migration
{
    public function up()
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('quotations', 'client_approval_token')) {
                $table->string('client_approval_token', 64)->nullable()->unique()->after('quotation_status');
            }
            if (! Schema::hasColumn('quotations', 'client_signature_path')) {
                $table->string('client_signature_path')->nullable()->after('client_approval_token');
            }
            if (! Schema::hasColumn('quotations', 'client_signed_at')) {
                $table->timestamp('client_signed_at')->nullable()->after('client_signature_path');
            }
            if (! Schema::hasColumn('quotations', 'client_comment')) {
                $table->text('client_comment')->nullable()->after('client_signed_at');
            }
            if (! Schema::hasColumn('quotations', 'client_responded_at')) {
                $table->timestamp('client_responded_at')->nullable()->after('client_comment');
            }
            if (! Schema::hasColumn('quotations', 'approval_sent_at')) {
                $table->timestamp('approval_sent_at')->nullable()->after('client_responded_at');
            }
        });
    }

    public function down()
    {
        Schema::table('quotations', function (Blueprint $table) {
            foreach ([
                'client_approval_token',
                'client_signature_path',
                'client_signed_at',
                'client_comment',
                'client_responded_at',
                'approval_sent_at',
            ] as $col) {
                if (Schema::hasColumn('quotations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}

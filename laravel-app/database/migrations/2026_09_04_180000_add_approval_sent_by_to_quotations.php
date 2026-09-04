<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddApprovalSentByToQuotations extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('quotations') || Schema::hasColumn('quotations', 'approval_sent_by')) {
            return;
        }

        Schema::table('quotations', function (Blueprint $table) {
            $table->unsignedInteger('approval_sent_by')->nullable()->after('approval_sent_at');
        });
    }

    public function down()
    {
        if (! Schema::hasTable('quotations') || ! Schema::hasColumn('quotations', 'approval_sent_by')) {
            return;
        }

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('approval_sent_by');
        });
    }
}

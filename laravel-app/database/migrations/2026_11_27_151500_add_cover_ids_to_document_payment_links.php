<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCoverIdsToDocumentPaymentLinks extends Migration
{
    public function up()
    {
        if (Schema::hasTable('document_payment_links') && ! Schema::hasColumn('document_payment_links', 'cover_ids')) {
            Schema::table('document_payment_links', function (Blueprint $table) {
                $table->text('cover_ids')->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('document_payment_links') && Schema::hasColumn('document_payment_links', 'cover_ids')) {
            Schema::table('document_payment_links', function (Blueprint $table) {
                $table->dropColumn('cover_ids');
            });
        }
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QuotationDocumentsText extends Migration
{
    public function up()
    {
        if (Schema::hasTable('quotations') && Schema::hasColumn('quotations', 'document')) {
            DB::statement('ALTER TABLE quotations MODIFY document TEXT NULL');
        }
    }

    public function down()
    {
    }
}

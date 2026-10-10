<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WidenVisaReferences extends Migration
{
    public function up()
    {
        if (Schema::hasTable('public_donations') && Schema::hasColumn('public_donations', 'campay_reference')) {
            DB::statement('ALTER TABLE public_donations MODIFY campay_reference VARCHAR(191) NULL');
        }
        if (Schema::hasTable('document_payment_links') && Schema::hasColumn('document_payment_links', 'campay_reference')) {
            DB::statement('ALTER TABLE document_payment_links MODIFY campay_reference VARCHAR(191) NULL');
        }
    }

    public function down()
    {
        if (Schema::hasTable('public_donations') && Schema::hasColumn('public_donations', 'campay_reference')) {
            DB::statement('ALTER TABLE public_donations MODIFY campay_reference VARCHAR(64) NULL');
        }
        if (Schema::hasTable('document_payment_links') && Schema::hasColumn('document_payment_links', 'campay_reference')) {
            DB::statement('ALTER TABLE document_payment_links MODIFY campay_reference VARCHAR(64) NULL');
        }
    }
}

<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateShareholdersTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('shareholders')) {
            Schema::create('shareholders', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('full_name')->nullable();
                $table->string('name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone_number', 50)->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('country_code', 10)->nullable();
                $table->string('full_phone_number', 50)->nullable();
                $table->string('company_name')->nullable();
                $table->text('address')->nullable();
                $table->string('nationality', 100)->nullable();
                $table->unsignedInteger('shares_assigned')->default(0);
                $table->decimal('investment_amount', 14, 2)->nullable();
                $table->longText('signature')->nullable();
                $table->text('signature_image_url')->nullable();
                $table->dateTime('agreement_signed_at')->nullable();
                $table->text('agreement_pdf_url')->nullable();
                $table->text('agreement_pdf_path')->nullable();
                $table->dateTime('pdf_generated_at')->nullable();
                $table->string('payment_status', 50)->default('pending');
                $table->string('reference_number', 100)->nullable()->unique();
                $table->string('status', 50)->default('pending_approval');
                $table->boolean('is_guest')->default(true);
                $table->uuid('user_id')->nullable();
                $table->dateTime('submitted_at')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->uuid('approved_by')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->text('admin_notes')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->timestamps();
                $table->index('email');
                $table->index('status');
            });
        }

        if (! Schema::hasTable('be_share_settings')) {
            Schema::create('be_share_settings', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->decimal('price_per_share', 14, 2)->default(1000);
                $table->unsignedInteger('total_shares_available')->default(10000);
                $table->unsignedInteger('total_sold_admin_override')->default(0);
                $table->string('currency', 10)->default('USD');
                $table->timestamps();
            });

            DB::table('be_share_settings')->insert([
                'price_per_share' => 1000,
                'total_shares_available' => 10000,
                'total_sold_admin_override' => 0,
                'currency' => 'USD',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('be_share_settings');
        Schema::dropIfExists('shareholders');
    }
}

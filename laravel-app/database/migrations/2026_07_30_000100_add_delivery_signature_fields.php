<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDeliverySignatureFields extends Migration
{
    public function up()
    {
        Schema::table('deliveries', function (Blueprint $table) {
            if (! Schema::hasColumn('deliveries', 'delivered_by_customer_id')) {
                $table->unsignedInteger('delivered_by_customer_id')->nullable()->after('delivered_by');
            }
            if (! Schema::hasColumn('deliveries', 'received_by_customer_id')) {
                $table->unsignedInteger('received_by_customer_id')->nullable()->after('recieved_by');
            }
            if (! Schema::hasColumn('deliveries', 'client_signature_token')) {
                $table->string('client_signature_token', 64)->nullable()->unique()->after('note');
            }
            if (! Schema::hasColumn('deliveries', 'client_signature_path')) {
                $table->string('client_signature_path')->nullable()->after('client_signature_token');
            }
            if (! Schema::hasColumn('deliveries', 'client_signed_at')) {
                $table->timestamp('client_signed_at')->nullable()->after('client_signature_path');
            }
            if (! Schema::hasColumn('deliveries', 'signature_sent_at')) {
                $table->timestamp('signature_sent_at')->nullable()->after('client_signed_at');
            }
            if (! Schema::hasColumn('deliveries', 'signer_name')) {
                $table->string('signer_name')->nullable()->after('signature_sent_at');
            }
        });
    }

    public function down()
    {
        Schema::table('deliveries', function (Blueprint $table) {
            foreach ([
                'delivered_by_customer_id',
                'received_by_customer_id',
                'client_signature_token',
                'client_signature_path',
                'client_signed_at',
                'signature_sent_at',
                'signer_name',
            ] as $col) {
                if (Schema::hasColumn('deliveries', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}

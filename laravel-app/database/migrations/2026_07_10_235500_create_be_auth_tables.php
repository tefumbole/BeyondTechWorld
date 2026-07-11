<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBeAuthTables extends Migration
{
    public function up()
    {
        Schema::create('be_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->string('username', 100)->nullable()->unique();
            $table->string('password_hash');
            $table->string('name')->nullable();
            $table->string('role', 50)->default('customer');
            $table->string('status', 50)->default('active');
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->boolean('must_change_credentials')->default(false);
            $table->timestamps();
            $table->index('role');
        });

        Schema::create('be_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email')->nullable();
            $table->string('full_name')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('role', 50)->nullable();
            $table->string('username', 100)->nullable();
            $table->text('address')->nullable();
            $table->boolean('must_change_credentials')->default(false);
            $table->string('status', 50)->default('active');
            $table->text('avatar_url')->nullable();
            $table->timestamps();
            $table->index('role');
        });

        Schema::create('be_otp_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('phone', 50);
            $table->string('otp', 10);
            $table->dateTime('expires_at');
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('resend_count')->default(0);
            $table->dateTime('verified_at')->nullable();
            $table->string('purpose', 50)->default('login');
            $table->timestamp('created_at')->useCurrent();
            $table->index('phone');
            $table->index('expires_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('be_otp_sessions');
        Schema::dropIfExists('be_profiles');
        Schema::dropIfExists('be_users');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNbcPraiseTeam extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('nbc_bylaws')) {
            Schema::create('nbc_bylaws', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('version')->default(1);
                $table->longText('body');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nbc_members')) {
            Schema::create('nbc_members', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 191);
                $table->string('email', 191)->unique();
                $table->string('phone', 40)->nullable();
                $table->string('password');
                $table->string('role', 20)->default('member');
                $table->string('part', 120)->nullable();
                $table->text('about')->nullable();
                $table->string('status', 20)->default('pending');
                $table->text('permissions')->nullable();
                $table->unsignedInteger('bylaws_version')->nullable();
                $table->dateTime('agreed_at')->nullable();
                $table->longText('signature')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nbc_events')) {
            Schema::create('nbc_events', function (Blueprint $table) {
                $table->increments('id');
                $table->string('title', 191);
                $table->string('kind', 20)->default('event');
                $table->dateTime('starts_at')->nullable();
                $table->dateTime('ends_at')->nullable();
                $table->string('place', 191)->nullable();
                $table->text('body')->nullable();
                $table->unsignedInteger('sort')->default(0);
                $table->boolean('published')->default(true);
                $table->unsignedInteger('author_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nbc_announcements')) {
            Schema::create('nbc_announcements', function (Blueprint $table) {
                $table->increments('id');
                $table->string('title', 191);
                $table->text('body');
                $table->dateTime('published_at')->nullable();
                $table->unsignedInteger('author_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nbc_tasks')) {
            Schema::create('nbc_tasks', function (Blueprint $table) {
                $table->increments('id');
                $table->string('title', 191);
                $table->text('body')->nullable();
                $table->unsignedInteger('assignee_id')->nullable();
                $table->date('due_on')->nullable();
                $table->string('status', 20)->default('open');
                $table->unsignedInteger('author_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nbc_letters')) {
            Schema::create('nbc_letters', function (Blueprint $table) {
                $table->increments('id');
                $table->string('title', 191);
                $table->string('recipient_name', 191)->nullable();
                $table->unsignedInteger('recipient_id')->nullable();
                $table->date('letter_date')->nullable();
                $table->text('body');
                $table->unsignedInteger('author_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nbc_quotations')) {
            Schema::create('nbc_quotations', function (Blueprint $table) {
                $table->increments('id');
                $table->string('number', 40);
                $table->string('client_name', 191);
                $table->decimal('amount', 14, 2)->default(0);
                $table->string('status', 20)->default('draft');
                $table->text('body')->nullable();
                $table->unsignedInteger('author_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nbc_messages')) {
            Schema::create('nbc_messages', function (Blueprint $table) {
                $table->increments('id');
                $table->string('audience', 20)->default('members');
                $table->unsignedInteger('member_id')->nullable();
                $table->text('body');
                $table->unsignedInteger('author_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nbc_attendance')) {
            Schema::create('nbc_attendance', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('member_id');
                $table->unsignedInteger('event_id')->nullable();
                $table->dateTime('clock_in');
                $table->dateTime('clock_out')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('nbc_attendance');
        Schema::dropIfExists('nbc_messages');
        Schema::dropIfExists('nbc_quotations');
        Schema::dropIfExists('nbc_letters');
        Schema::dropIfExists('nbc_tasks');
        Schema::dropIfExists('nbc_announcements');
        Schema::dropIfExists('nbc_events');
        Schema::dropIfExists('nbc_members');
        Schema::dropIfExists('nbc_bylaws');
    }
}

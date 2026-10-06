<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNbcPraiseTeam extends Migration
{
    public function up()
    {
        $schema = Schema::connection('nbc');
        if (! $schema->hasTable('nbc_bylaws')) {
            $schema->create('nbc_bylaws', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('version')->default(1);
                $table->longText('body');
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('nbc_members')) {
            $schema->create('nbc_members', function (Blueprint $table) {
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

        if (! $schema->hasTable('nbc_events')) {
            $schema->create('nbc_events', function (Blueprint $table) {
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

        if (! $schema->hasTable('nbc_announcements')) {
            $schema->create('nbc_announcements', function (Blueprint $table) {
                $table->increments('id');
                $table->string('title', 191);
                $table->text('body');
                $table->dateTime('published_at')->nullable();
                $table->unsignedInteger('author_id')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('nbc_tasks')) {
            $schema->create('nbc_tasks', function (Blueprint $table) {
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

        if (! $schema->hasTable('nbc_letters')) {
            $schema->create('nbc_letters', function (Blueprint $table) {
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

        if (! $schema->hasTable('nbc_quotations')) {
            $schema->create('nbc_quotations', function (Blueprint $table) {
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

        if (! $schema->hasTable('nbc_messages')) {
            $schema->create('nbc_messages', function (Blueprint $table) {
                $table->increments('id');
                $table->string('audience', 20)->default('members');
                $table->unsignedInteger('member_id')->nullable();
                $table->text('body');
                $table->unsignedInteger('author_id')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('nbc_attendance')) {
            $schema->create('nbc_attendance', function (Blueprint $table) {
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
        $schema = Schema::connection('nbc');
        $schema->dropIfExists('nbc_attendance');
        $schema->dropIfExists('nbc_messages');
        $schema->dropIfExists('nbc_quotations');
        $schema->dropIfExists('nbc_letters');
        $schema->dropIfExists('nbc_tasks');
        $schema->dropIfExists('nbc_announcements');
        $schema->dropIfExists('nbc_events');
        $schema->dropIfExists('nbc_members');
        $schema->dropIfExists('nbc_bylaws');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColorsToOnlineInvitationTemplates extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('online_invitation_templates')) {
            return;
        }

        Schema::table('online_invitation_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('online_invitation_templates', 'border_color')) {
                $table->string('border_color', 16)->nullable()->after('background');
            }
            if (! Schema::hasColumn('online_invitation_templates', 'font_color')) {
                $table->string('font_color', 16)->nullable()->after('border_color');
            }
            if (! Schema::hasColumn('online_invitation_templates', 'font_size')) {
                $table->unsignedTinyInteger('font_size')->nullable()->after('font_color');
            }
        });

        if (Schema::hasTable('online_invitations') && ! Schema::hasColumn('online_invitations', 'font_size')) {
            Schema::table('online_invitations', function (Blueprint $table) {
                $table->unsignedTinyInteger('font_size')->nullable()->after('font_color');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('online_invitations') && Schema::hasColumn('online_invitations', 'font_size')) {
            Schema::table('online_invitations', function (Blueprint $table) {
                $table->dropColumn('font_size');
            });
        }

        if (! Schema::hasTable('online_invitation_templates')) {
            return;
        }

        Schema::table('online_invitation_templates', function (Blueprint $table) {
            if (Schema::hasColumn('online_invitation_templates', 'font_size')) {
                $table->dropColumn('font_size');
            }
            if (Schema::hasColumn('online_invitation_templates', 'font_color')) {
                $table->dropColumn('font_color');
            }
            if (Schema::hasColumn('online_invitation_templates', 'border_color')) {
                $table->dropColumn('border_color');
            }
        });
    }
}

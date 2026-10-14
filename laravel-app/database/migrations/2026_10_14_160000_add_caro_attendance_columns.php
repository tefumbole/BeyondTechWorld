<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCaroAttendanceColumns extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('caro_invitations')) {
            return;
        }

        Schema::table('caro_invitations', function (Blueprint $table) {
            if (! Schema::hasColumn('caro_invitations', 'presented_at')) {
                $table->timestamp('presented_at')->nullable();
            }
            if (! Schema::hasColumn('caro_invitations', 'thanks_sent_at')) {
                $table->timestamp('thanks_sent_at')->nullable();
            }
            if (! Schema::hasColumn('caro_invitations', 'invitation_sent_at')) {
                $table->timestamp('invitation_sent_at')->nullable();
            }
        });

        try {
            $dupes = \Illuminate\Support\Facades\DB::table('caro_invitations')
                ->select('phone_digits')
                ->groupBy('phone_digits')
                ->havingRaw('COUNT(*) > 1')
                ->get();
            if (count($dupes) === 0) {
                Schema::table('caro_invitations', function (Blueprint $table) {
                    $table->unique('phone_digits');
                });
            }
        } catch (\Throwable $e) {
            // The phone is already unique, or this database cannot add the index yet.
        }

        try {
            $service = app(\App\Services\CaroInvitationService::class);
            foreach (\App\CaroInvitation::query()->get() as $row) {
                try {
                    $service->compose($row);
                } catch (\Throwable $e) {
                    // Keep the saved card if this one cannot be redrawn. Nothing is sent.
                }
            }
        } catch (\Throwable $e) {
            // Drawing the cards again can wait. Attendance still works from the seat record.
        }
    }

    public function down()
    {
        if (! Schema::hasTable('caro_invitations')) {
            return;
        }

        Schema::table('caro_invitations', function (Blueprint $table) {
            if (Schema::hasColumn('caro_invitations', 'presented_at')) {
                $table->dropColumn('presented_at');
            }
            if (Schema::hasColumn('caro_invitations', 'thanks_sent_at')) {
                $table->dropColumn('thanks_sent_at');
            }
            if (Schema::hasColumn('caro_invitations', 'invitation_sent_at')) {
                $table->dropColumn('invitation_sent_at');
            }
        });
    }
}

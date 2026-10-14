<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class MarkExistingCaroInvitationsSent extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('caro_invitations') || ! Schema::hasColumn('caro_invitations', 'invitation_sent_at')) {
            return;
        }

        foreach (\App\CaroInvitation::query()->whereNull('invitation_sent_at')->get() as $row) {
            if (! is_file($row->imagePath())) {
                continue;
            }
            $row->invitation_sent_at = $row->created_at ?: \Carbon\Carbon::now('Africa/Douala');
            $row->save();
        }
    }

    public function down()
    {
    }
}

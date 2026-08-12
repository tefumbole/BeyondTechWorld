<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class FixOnlineInvitationTemplatePublicUrls extends Migration
{
    public function up()
    {
        $rows = DB::table('online_invitation_templates')
            ->whereNotNull('background')
            ->where('background', 'like', '%/images/online_invitation/%')
            ->get(['id', 'background']);

        foreach ($rows as $row) {
            $bg = (string) $row->background;
            // Insert /public before /images when missing.
            $fixed = preg_replace(
                '#(https?://[^/\'\"]+)(/images/online_invitation/)#i',
                '$1/public$2',
                $bg
            );
            $fixed = preg_replace(
                "#url\\('(/images/online_invitation/)#i",
                "url('/public$1",
                $fixed
            );
            if ($fixed && $fixed !== $bg) {
                DB::table('online_invitation_templates')
                    ->where('id', $row->id)
                    ->update(['background' => $fixed]);
            }
        }
    }

    public function down()
    {
        // irreversible data fix
    }
}

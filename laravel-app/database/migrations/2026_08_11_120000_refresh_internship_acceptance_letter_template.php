<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RefreshInternshipAcceptanceLetterTemplate extends Migration
{
    const TEMPLATE_NAME = 'Internship Acceptance Letter';

    public function up()
    {
        if (! Schema::hasTable('letter_templates')) {
            return;
        }

        $body = <<<'HTML'
<p><em>[school]</em></p>
<p>Congratulations, and welcome to <strong>[system_name]</strong>!</p>
<p>We are delighted to confirm that you have been <strong>admitted</strong> into the <strong>[system_name] Internship Programme</strong> for <strong>[program]</strong>.</p>
<p>You were selected because we believe in your potential, passion for technology, and willingness to learn. During your internship, you will work alongside experienced professionals on real-world projects. We encourage you to be curious, professional, collaborative, and ready to contribute fresh ideas.</p>
<p><strong>Your supervisor(s)</strong> will be <strong>[supervisors]</strong>.</p>
<p><strong>Your internship period</strong> will run from <strong>[start_date]</strong> to <strong>[end_date]</strong>.</p>
<p><strong>Duration:</strong> [duration]. You are required to <strong>submit a task daily</strong>.</p>
<p><strong>Login credentials</strong><br>
Username: <strong>[phone_number]</strong> (or email <strong>[email]</strong>)<br>
Default password: <strong>[password]</strong><br>
After you log in, please change your password immediately. Then go to <strong>Timesheets</strong> and configure your working week.</p>
<p>This is more than an internship—it is an opportunity to learn, grow, solve meaningful problems, and build your future.</p>
<p>Welcome to the Beyond family. We look forward to achieving great things together!</p>
HTML;

        DB::table('letter_templates')
            ->where('name', self::TEMPLATE_NAME)
            ->update([
                'subject' => 'Internship Admission and Welcome to the [system_name] Internship Programme',
                'body' => $body,
                'footer' => '<p>Beyond Company Ltd · Internship Programme</p>',
                'updated_at' => now(),
            ]);
    }

    public function down()
    {
        // Non-destructive: leave the refreshed template in place.
    }
}

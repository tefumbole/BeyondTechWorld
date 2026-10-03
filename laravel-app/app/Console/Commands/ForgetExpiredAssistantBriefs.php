<?php

namespace App\Console\Commands;

use App\Assistant\AssistantBrief;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ForgetExpiredAssistantBriefs extends Command
{
    protected $signature = 'assistant:forget-expired-briefs';

    protected $description = 'Delete assistant briefs whose end time has passed';

    public function handle()
    {
        if (! Schema::hasTable('assistant_briefs') || ! Schema::hasColumn('assistant_briefs', 'ends_at')) {
            return 0;
        }
        AssistantBrief::whereNotNull('ends_at')->where('ends_at', '<', now())->delete();

        return 0;
    }
}

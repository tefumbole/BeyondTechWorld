<?php

namespace App\Console\Commands;

use App\Services\EventPublicationService;
use Illuminate\Console\Command;

class PublishScheduledEvents extends Command
{
    protected $signature = 'events:publish-scheduled';

    protected $description = 'Publish events whose visibility date has arrived';

    public function handle(EventPublicationService $pubService)
    {
        $count = $pubService->processScheduledPublications();
        $this->info("Published {$count} scheduled event(s).");

        return 0;
    }
}

<?php

namespace App\Jobs;

use App\Travel\TravelNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TravelFlushOutbox implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $outboxId;

    public function __construct($outboxId)
    {
        $this->outboxId = $outboxId;
    }

    public function handle()
    {
        app(TravelNotifier::class)->deliver($this->outboxId);
    }
}

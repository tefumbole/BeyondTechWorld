<?php

namespace App\Jobs;

use App\Services\Messaging\NotificationRouter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendHotspotWhatsApp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $timeout = 40;

    protected $phone;

    protected $body;

    public function __construct($phone, $body)
    {
        $this->phone = $phone;
        $this->body = $body;
        $this->onConnection('database')->onQueue('whatsapp');
    }

    public function handle(NotificationRouter $router)
    {
        if (! $this->phone || trim((string) $this->body) === '') {
            return;
        }
        $router->sendWasenderText($this->phone, $this->body);
    }
}

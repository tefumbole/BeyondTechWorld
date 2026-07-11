<?php

namespace App\Jobs;


use App\Announcement;
use App\Http\Controllers\AnnouncementController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAnnouncementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $announcement;
    protected $recipient;

    public function __construct(Announcement $announcement, $recipient)
    {
        $this->announcement = $announcement;
        $this->recipient = $recipient;
    }

    public function handle()
    {
        $controller = new AnnouncementController();
        $controller->sendAnnouncementMsg($this->announcement, $this->recipient);
    }
}

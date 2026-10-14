<?php

namespace App\Jobs;

use App\CaroInvitation;
use App\Services\BeyondWasenderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendCaroInvitationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;

    public $tries = 3;

    protected $invitationId;

    public function __construct($invitationId)
    {
        $this->invitationId = $invitationId;
    }

    public function handle(BeyondWasenderService $whatsapp)
    {
        $row = CaroInvitation::find($this->invitationId);
        if (! $row || ! is_file($row->imagePath())) {
            return;
        }

        $lock = storage_path('framework/caro-wasender.lock');
        $stamp = storage_path('framework/caro-wasender-last.txt');
        $handle = fopen($lock, 'c');
        if (! $handle) {
            $this->send($whatsapp, $row);

            return;
        }
        try {
            flock($handle, LOCK_EX);
            $last = is_file($stamp) ? (int) trim((string) file_get_contents($stamp)) : 0;
            $wait = 5 - (time() - $last);
            if ($last > 0 && $wait > 0) {
                sleep($wait);
            }
            $this->send($whatsapp, $row);
            file_put_contents($stamp, (string) time());
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    protected function send(BeyondWasenderService $whatsapp, CaroInvitation $row)
    {
        try {
            $result = $whatsapp->sendImage($row->phone, $row->imagePath(), null, true);
            if (empty($result['success'])) {
                Log::info('caro invitation send failed', ['error' => isset($result['error']) ? $result['error'] : 'unknown']);
            }
        } catch (\Throwable $e) {
            Log::warning('caro invitation send exception: '.$e->getMessage());
        }
    }
}

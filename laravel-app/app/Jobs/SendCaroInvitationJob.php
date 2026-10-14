<?php

namespace App\Jobs;

use App\CaroInvitation;
use App\Services\BeyondWasenderService;
use App\Support\CaroSendGate;
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
        if (! $row || ! is_file($row->imagePath()) || $row->invitation_sent_at) {
            return;
        }

        CaroSendGate::send(function () use ($whatsapp, $row) {
            $fresh = CaroInvitation::find($row->id);
            if (! $fresh || ! is_file($fresh->imagePath()) || $fresh->invitation_sent_at) {
                return;
            }
            $this->send($whatsapp, $fresh);
        });
    }

    protected function send(BeyondWasenderService $whatsapp, CaroInvitation $row)
    {
        try {
            $result = $whatsapp->sendImage($row->phone, $row->imagePath(), null, true);
            if (! empty($result['success'])) {
                $row->invitation_sent_at = \Carbon\Carbon::now('Africa/Douala');
                $row->save();
            } else {
                Log::info('caro invitation send failed', ['error' => isset($result['error']) ? $result['error'] : 'unknown']);
            }
        } catch (\Throwable $e) {
            Log::warning('caro invitation send exception: '.$e->getMessage());
        }
    }
}

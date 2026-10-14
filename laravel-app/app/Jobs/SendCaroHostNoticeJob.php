<?php

namespace App\Jobs;

use App\CaroInvitation;
use App\Services\BeyondWasenderService;
use App\Support\CaroSeats;
use App\Support\CaroSendGate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendCaroHostNoticeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 90;

    public $tries = 3;

    protected $invitationId;

    public function __construct($invitationId)
    {
        $this->invitationId = $invitationId;
    }

    public function handle(BeyondWasenderService $whatsapp)
    {
        $row = CaroInvitation::find($this->invitationId);
        if (! $row || $row->host_notified_at) {
            return;
        }

        $message = $row->name.' has booked seat '.CaroSeats::label($row->seat_code).' for MSATA. '
            .'Contact: '.$row->phone;

        CaroSendGate::send(function () use ($whatsapp, $row, $message) {
            $fresh = CaroInvitation::find($row->id);
            if (! $fresh || $fresh->host_notified_at) {
                return;
            }
            try {
                $result = $whatsapp->sendTextRaw(CaroSeats::hostPhone(), $message, false);
                if (empty($result['success'])) {
                    Log::info('caro host notice failed', ['error' => isset($result['error']) ? $result['error'] : 'unknown']);
                    throw new \RuntimeException('Host notice was not sent.');
                }
                $fresh->host_notified_at = \Carbon\Carbon::now('Africa/Douala');
                $fresh->save();
            } catch (\Throwable $e) {
                Log::warning('caro host notice exception: '.$e->getMessage());
                throw $e;
            }
        });
    }
}

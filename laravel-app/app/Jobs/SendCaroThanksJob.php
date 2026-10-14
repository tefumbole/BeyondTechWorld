<?php

namespace App\Jobs;

use App\CaroInvitation;
use App\Services\BeyondWasenderService;
use App\Support\CaroSeats;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendCaroThanksJob implements ShouldQueue
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
        if (! $row || $row->thanks_sent_at) {
            return;
        }

        $seat = CaroSeats::label($row->seat_code);
        $message = 'Thank you for attending MSATA 2026, '.$row->name.'. '
            .'We are glad you were with us at Presbyterian Hall Ntamulung. '
            .'Your seat was '.$seat.'.';

        try {
            $result = $whatsapp->sendTextRaw($row->phone, $message, false);
            if (empty($result['success'])) {
                Log::info('caro thanks send failed', ['error' => isset($result['error']) ? $result['error'] : 'unknown']);
                throw new \RuntimeException('Thanks message was not sent.');
            }
            $row->thanks_sent_at = \Carbon\Carbon::now('Africa/Douala');
            $row->save();
        } catch (\Throwable $e) {
            Log::warning('caro thanks send exception: '.$e->getMessage());
            throw $e;
        }
    }
}

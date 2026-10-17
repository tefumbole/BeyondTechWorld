<?php

namespace App\Gbhs;

use App\Letter;
use App\Services\BeyondWasenderService;
use Illuminate\Support\Facades\Storage;

class GbhsMessenger
{
    /**
     * Preview only. Nothing is sent until confirm() is called.
     */
    public static function plan(array $jids, $letterId)
    {
        $sent = GbhsDelivery::where('letter_id', $letterId)->where('status', 'sent')->pluck('target')->all();

        return GbhsRules::groupTargets($jids, $sent);
    }

    public static function confirm($letterId, array $jids)
    {
        $letter = Letter::find($letterId);
        if (! $letter) {
            return [];
        }
        $targets = self::plan($jids, $letterId);
        $path = self::pdf($letter);
        $results = [];
        $sender = app(BeyondWasenderService::class);
        foreach ($targets as $jid) {
            $delivery = GbhsDelivery::firstOrCreate(
                ['letter_id' => $letter->id, 'target' => $jid],
                ['kind' => 'group', 'status' => 'pending']
            );
            if ($delivery->status === 'sent') {
                $results[] = $delivery;
                continue;
            }
            try {
                if ($path && is_file($path)) {
                    $sent = $sender->sendGroupDocument($jid, $path, $letter->reference.'.pdf', $letter->subject);
                } else {
                    $sent = $sender->sendGroupText($jid, $letter->subject."\n".$letter->reference);
                }
                $ok = is_array($sent) ? ! empty($sent['success']) : (bool) $sent;
                if (is_array($sent) && ! array_key_exists('success', $sent) && empty($sent['error'])) {
                    $ok = true;
                }
                $delivery->status = $ok ? 'sent' : 'failed';
                $delivery->error = $ok ? null : (is_array($sent) && isset($sent['error']) ? substr((string) $sent['error'], 0, 180) : 'Not sent');
                $delivery->sent_at = $ok ? now() : null;
            } catch (\Throwable $e) {
                $delivery->status = 'failed';
                $delivery->error = substr($e->getMessage(), 0, 180);
            }
            $delivery->save();
            $results[] = $delivery;
        }

        return $results;
    }

    protected static function pdf(Letter $letter)
    {
        try {
            $data = [
                'to' => $letter->to,
                'data' => $letter,
                'user_to' => (object) ['name' => $letter->to, 'phone_number' => '', 'email' => ''],
                'letterhead_flow' => true,
            ];
            $pdf = \PDF::loadView('pdf.letter_pdf', $data)->setPaper('A4', 'portrait');
            $dir = 'gbhs/letters';
            Storage::disk('public')->put($dir.'/'.$letter->id.'.pdf', $pdf->output());
            $public = storage_path('app/public/'.$dir.'/'.$letter->id.'.pdf');
            if (is_file($public)) {
                return $public;
            }
        } catch (\Throwable $e) {
        }

        return null;
    }
}

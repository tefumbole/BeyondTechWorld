<?php

namespace App\Services;

use App\Services\Messaging\NotificationRouter;

/**
 * Tell a client about a deposit or payout on WhatsApp, or by SMS when WhatsApp cannot deliver.
 */
class ClientNoticeService
{
    public function send($phone, $message, $copyToAdmin = true)
    {
        $phone = trim((string) $phone);
        $message = trim((string) $message);
        if ($phone === '' || $message === '') {
            return 'none';
        }

        try {
            $whatsapp = app(BeyondWasenderService::class)->sendTextRaw($phone, $message, false, (bool) $copyToAdmin);
            if (! empty($whatsapp['success'])) {
                return 'whatsapp';
            }
        } catch (\Throwable $e) {
            \Log::warning('Client WhatsApp notice failed', ['error' => $e->getMessage()]);
        }

        $smsPhone = $this->smsPhone($phone);
        $smsText = trim(preg_replace('/\*+|_+|━+/u', '', $message));
        $smsText = trim(preg_replace("/\n{3,}/", "\n\n", $smsText));
        try {
            $sms = app(NotificationRouter::class)->sendSms($smsPhone, $smsText !== '' ? $smsText : $message);
            if (! empty($sms['success']) && empty($sms['skipped'])) {
                return 'sms';
            }
        } catch (\Throwable $e) {
            \Log::warning('Client SMS notice failed', ['error' => $e->getMessage()]);
        }

        return 'none';
    }

    protected function smsPhone($phone)
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if ($digits === '') {
            return $phone;
        }

        return '+'.$digits;
    }
}

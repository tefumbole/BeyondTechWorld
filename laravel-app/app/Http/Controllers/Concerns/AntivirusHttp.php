<?php

namespace App\Http\Controllers\Concerns;

use App\Antivirus\AntivirusRuleException;
use App\Services\Messaging\NotificationRouter;
use App\Support\AntivirusOtp;
use App\Support\WhatsAppPhone;

trait AntivirusHttp
{
    protected function antivirusFail(\Throwable $e)
    {
        if ($e instanceof AntivirusRuleException) {
            return back()->withInput()->withErrors([$e->field => $e->getMessage()]);
        }
        throw $e;
    }

    protected function sendAntivirusCode($purpose, $subject, $phone)
    {
        $code = AntivirusOtp::issue($purpose, $subject);
        try {
            $sent = app(NotificationRouter::class)->sendWhatsAppOtp(
                WhatsAppPhone::forWasender($phone),
                $code,
                'antivirus',
                10
            );
            if (empty($sent['success'])) {
                throw new \RuntimeException('The code could not be sent.');
            }
        } catch (AntivirusRuleException $e) {
            throw $e;
        } catch (\Throwable $e) {
            AntivirusOtp::forget($purpose, $subject);
            throw new AntivirusRuleException('otp', 'The code could not be sent. Check the number and try again.');
        }
    }

    protected function checkAntivirusCode($purpose, $subject, $typed)
    {
        $result = AntivirusOtp::check($purpose, $subject, $typed);
        if ($result !== 'ok') {
            throw new AntivirusRuleException('otp', AntivirusOtp::message($result));
        }
    }
}

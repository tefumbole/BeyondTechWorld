<?php

namespace App\Services\Messaging;

use App\Contracts\Sms\SmsProviderInterface;

class SmsProviderRegistry
{
    public function known()
    {
        return ['orange_cm', 'mtn_cm', 'infobip', 'fake'];
    }

    /**
     * @return SmsProviderInterface
     */
    public function resolve($providerCode)
    {
        $code = strtolower((string) $providerCode);
        if (config('messaging.sms.driver') === 'fake' && ($code === 'fake' || $code === '')) {
            return app(FakeSmsProvider::class);
        }
        if (config('messaging.sms.driver') === 'fake' && in_array($code, ['orange_cm', 'mtn_cm', 'infobip'], true)) {
            return app(FakeSmsProvider::class);
        }
        if (config('messaging.sms.driver') === 'infobip' && $code === 'infobip') {
            return app(InfobipSmsProvider::class);
        }

        return new UnactivatedSmsProvider($code === '' ? 'disabled' : $code);
    }
}

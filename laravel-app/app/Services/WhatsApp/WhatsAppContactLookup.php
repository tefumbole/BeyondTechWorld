<?php

namespace App\Services\WhatsApp;

use App\Services\Cloud\CloudTenantContext;
use App\Services\Cloud\CloudTenantColumns;
use App\Services\Cloud\MissingCloudTenantException;
use App\WhatsApp\WhatsAppContact;

/**
 * A phone number is unique inside one company, not across the installation.
 */
class WhatsAppContactLookup
{
    public function find($phone)
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return null;
        }
        $this->requireTenant();

        return WhatsAppContact::query()->where('normalized_phone', $digits)->first();
    }

    public function requireTenant()
    {
        $model = new WhatsAppContact();
        if (! CloudTenantColumns::present($model)) {
            return;
        }
        if (! app(CloudTenantContext::class)->has()) {
            throw new MissingCloudTenantException('WhatsApp contact lookup requires an active company.');
        }
    }
}

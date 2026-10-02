<?php

namespace App\Services\Cloud;

use App\Cloud\CloudSubscriptionPayment;

/**
 * Test provider. It does not mark a payment paid. Tests confirm through the webhook.
 */
class CloudSandboxBillingProvider implements CloudBillingProviderInterface
{
    public function provider()
    {
        return 'sandbox';
    }

    public function begin(CloudSubscriptionPayment $payment)
    {
        $payment->provider = 'sandbox';
        $payment->provider_reference = 'sandbox-'.$payment->id;
        $payment->save();

        return null;
    }
}

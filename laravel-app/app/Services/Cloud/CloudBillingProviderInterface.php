<?php

namespace App\Services\Cloud;

use App\Cloud\CloudSubscriptionPayment;

/**
 * Subscription billing provider. ERP customer payments are a different ledger.
 */
interface CloudBillingProviderInterface
{
    public function provider();

    /**
     * Start a checkout. Return a URL, or null when the provider only confirms later.
     */
    public function begin(CloudSubscriptionPayment $payment);
}

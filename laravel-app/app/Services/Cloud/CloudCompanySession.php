<?php

namespace App\Services\Cloud;

class CloudCompanySession
{
    public function forgetTenantState()
    {
        session()->forget([
            CloudTenantResolver::SESSION_KEY,
            'cart',
            'quotation_cart',
            'rental_cart',
            'pos_cart',
            'sale_cart',
        ]);
    }
}

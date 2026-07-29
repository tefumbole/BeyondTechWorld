<?php

namespace App\Support;

use App\Sale;

class SaleInvoiceQr
{
    public static function token(Sale $sale): string
    {
        return substr(hash_hmac(
            'sha256',
            'sale-invoice|'.$sale->id.'|'.$sale->reference_no,
            (string) config('app.key')
        ), 0, 20);
    }

    public static function scanUrl(Sale $sale): string
    {
        return url('/verify/invoice/'.$sale->id.'/'.self::token($sale));
    }

    public static function isValid(Sale $sale, string $token): bool
    {
        return hash_equals(self::token($sale), (string) $token);
    }
}

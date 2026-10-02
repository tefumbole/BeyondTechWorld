<?php

namespace App\Cloud;

/**
 * Stable module codes. Authorization must use these, not display names.
 */
class CloudModuleCode
{
    const WHATSAPP_HUB = 'WHATSAPP_HUB';
    const SALES_INVOICES = 'SALES_INVOICES';
    const RENTALS = 'RENTALS';
    const MESSAGING = 'MESSAGING';
    const QUOTATIONS = 'QUOTATIONS';
    const DIGITAL_INVITATIONS = 'DIGITAL_INVITATIONS';

    public static function all()
    {
        return [
            self::WHATSAPP_HUB,
            self::SALES_INVOICES,
            self::RENTALS,
            self::MESSAGING,
            self::QUOTATIONS,
            self::DIGITAL_INVITATIONS,
        ];
    }
}

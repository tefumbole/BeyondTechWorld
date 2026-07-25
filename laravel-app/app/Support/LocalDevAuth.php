<?php

namespace App\Support;

/**
 * Local-only auth shortcuts. Never active when APP_ENV is not "local".
 */
class LocalDevAuth
{
    public static function skipStaffOtp()
    {
        return config('app.env') === 'local';
    }
}

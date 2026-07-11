<?php

namespace App\Support;

use App\Letter;

class LetterQr
{
    public static function scanUrl(Letter $letter): string
    {
        return url('/letters/scan/' . $letter->id);
    }
}

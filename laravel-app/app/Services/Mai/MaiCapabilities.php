<?php

namespace App\Services\Mai;

class MaiCapabilities
{
    public static function matrix()
    {
        return [
            'text' => ['supported' => true, 'accepted' => true],
            'media' => ['supported' => false, 'accepted' => false],
            'groups' => ['supported' => false, 'accepted' => false],
            'voice' => ['supported' => false, 'accepted' => false],
            'history_sync' => ['supported' => false, 'accepted' => false],
            'read_receipts' => ['supported' => false, 'accepted' => false],
        ];
    }

    public static function textReady()
    {
        $matrix = self::matrix();

        return ! empty($matrix['text']['accepted']);
    }
}

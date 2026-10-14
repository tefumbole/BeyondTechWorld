<?php

namespace App\Support;

class CaroSendGate
{
    /**
     * Run one WhatsApp send, then wait so the next invitation, scan notice,
     * or host message is at least 5 seconds later.
     */
    public static function send($callback)
    {
        $lock = storage_path('framework/caro-wasender.lock');
        $stamp = storage_path('framework/caro-wasender-last.txt');
        $handle = fopen($lock, 'c');
        if (! $handle) {
            return $callback();
        }
        try {
            flock($handle, LOCK_EX);
            $last = is_file($stamp) ? (int) trim((string) file_get_contents($stamp)) : 0;
            $wait = 5 - (time() - $last);
            if ($last > 0 && $wait > 0) {
                sleep($wait);
            }
            try {
                return $callback();
            } finally {
                file_put_contents($stamp, (string) time());
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}

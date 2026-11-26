<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Cache;

class WhatsAppTyping
{
    public function remember($phone, $kind)
    {
        $digits = $this->digits($phone);
        if ($digits === '') {
            return;
        }
        $kind = strtolower(trim((string) $kind));
        if (! in_array($kind, ['composing', 'recording'], true)) {
            $this->forget($digits);

            return;
        }
        Cache::put($this->key($digits), $kind, now()->addSeconds(12));
    }

    public function forget($phone)
    {
        $digits = $this->digits($phone);
        if ($digits === '') {
            return;
        }
        Cache::forget($this->key($digits));
    }

    public function state($phone)
    {
        $digits = $this->digits($phone);
        if ($digits === '') {
            return '';
        }
        $kind = Cache::get($this->key($digits));
        if ($kind === 'recording') {
            return 'recording';
        }
        if ($kind === 'composing') {
            return 'typing';
        }

        return '';
    }

    protected function key($digits)
    {
        return 'wa.typing.'.$digits;
    }

    protected function digits($phone)
    {
        return preg_replace('/\D/', '', (string) $phone);
    }
}

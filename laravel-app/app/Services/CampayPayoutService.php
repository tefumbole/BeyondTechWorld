<?php

namespace App\Services;

use App\CampayPayout;
use Illuminate\Support\Facades\Log;

class CampayPayoutService
{
    public function momoNumber($phone)
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if (strpos($digits, '237') === 0) {
            $digits = substr($digits, 3);
        }
        if (strlen($digits) !== 9 || $digits[0] !== '6') {
            return null;
        }
        $three = substr($digits, 0, 3);
        $two = substr($digits, 0, 2);
        $mtn = $two === '67' || ($three >= '650' && $three <= '654') || ($three >= '680' && $three <= '683');
        $orange = $two === '69' || ($three >= '655' && $three <= '659') || ($three >= '690' && $three <= '699');
        if (! $mtn && ! $orange) {
            return null;
        }

        return '237'.$digits;
    }

    public function operatorName($phone)
    {
        $to = $this->momoNumber($phone);
        if (! $to) {
            return '';
        }
        $digits = substr($to, 3);
        $three = substr($digits, 0, 3);
        $two = substr($digits, 0, 2);
        $mtn = $two === '67' || ($three >= '650' && $three <= '654') || ($three >= '680' && $three <= '683');

        return $mtn ? 'MTN' : 'Orange';
    }

    public function balance()
    {
        $body = $this->call('GET', 'balance/', []);
        if (! is_array($body)) {
            return null;
        }
        $lines = [];
        foreach ($body as $key => $value) {
            if (is_numeric($value)) {
                $lines[] = $this->balanceLabel($key).' '.number_format((float) $value, 0, '.', ' ');
            } elseif (is_array($value)) {
                foreach ($value as $innerKey => $inner) {
                    if (is_numeric($inner)) {
                        $lines[] = $this->balanceLabel($innerKey).' '.number_format((float) $inner, 0, '.', ' ');
                    }
                }
            }
        }

        return $lines ? implode(' · ', $lines) : null;
    }

    protected function balanceLabel($key)
    {
        $names = [
            'total_balance' => 'Total',
            'mtn_balance' => 'MTN',
            'orange_balance' => 'Orange',
            'utility_balance' => 'Utility',
            'utility_commission_balance' => 'Commission',
        ];
        $key = strtolower((string) $key);

        return isset($names[$key]) ? $names[$key] : ucfirst(str_replace('_', ' ', $key));
    }

    public function collect($phone, $amount, $reference, $description)
    {
        return $this->call('POST', 'collect/', [
            'amount' => (string) (int) $amount,
            'currency' => 'XAF',
            'from' => (string) $phone,
            'description' => (string) $description,
            'external_reference' => (string) $reference,
        ]);
    }

    public function cardLink($phone, $amount, $reference, $description, $returnUrl, $name)
    {
        $parts = preg_split('/\s+/', trim((string) $name), 2);
        $first = isset($parts[0]) && $parts[0] !== '' ? $parts[0] : 'Client';
        $last = isset($parts[1]) && $parts[1] !== '' ? $parts[1] : $first;

        return $this->call('POST', 'get_payment_link/', [
            'amount' => (string) (int) $amount,
            'currency' => 'XAF',
            'from' => (string) $phone,
            'description' => (string) $description,
            'external_reference' => (string) $reference,
            'redirect_url' => (string) $returnUrl,
            'failure_redirect_url' => (string) $returnUrl,
            'first_name' => $first,
            'last_name' => $last,
            'payment_options' => 'CARD',
        ]);
    }

    public function transaction($reference)
    {
        $reference = trim((string) $reference);
        if ($reference === '') {
            return null;
        }

        return $this->call('GET', 'transaction/'.$reference.'/', []);
    }

    public function pay(CampayPayout $row)
    {
        $to = $this->momoNumber($row->phone);
        if (! $to) {
            $row->status = 'failed';
            $row->error = 'This number is not an MTN or Orange Cameroon number.';
            $row->save();

            return $row;
        }
        $row->phone = $to;
        $body = $this->call('POST', 'withdraw/', [
            'amount' => (string) (int) $row->amount,
            'currency' => 'XAF',
            'to' => $to,
            'description' => $row->note !== '' ? (string) $row->note : 'Payout',
            'external_reference' => (string) $row->external_reference,
        ]);
        if (! is_array($body)) {
            $row->status = 'failed';
            $row->error = 'Campay did not answer. Nothing was confirmed.';
            $row->save();

            return $row;
        }
        $status = strtoupper((string) (isset($body['status']) ? $body['status'] : ''));
        $row->campay_reference = isset($body['reference']) ? (string) $body['reference'] : null;
        $row->operator = isset($body['operator']) ? (string) $body['operator'] : null;
        if ($status === 'SUCCESSFUL') {
            $row->status = 'paid';
            $row->error = null;
        } else {
            $row->status = 'failed';
            $row->error = $this->errorText($body, $status);
        }
        $row->save();

        return $row;
    }

    protected function errorText(array $body, $status)
    {
        foreach (['message', 'detail', 'error', 'reason'] as $key) {
            if (! empty($body[$key]) && is_string($body[$key])) {
                return substr($body[$key], 0, 250);
            }
        }
        if ($status === 'FAILED') {
            return 'Campay refused this payout.';
        }
        if ($status === '') {
            return 'Campay did not confirm the payout.';
        }

        return 'Campay status: '.$status;
    }

    protected function call($method, $path, array $payload)
    {
        $token = $this->token();
        if (! $token) {
            Log::warning('Campay payout has no token');

            return null;
        }
        $base = rtrim((string) config('services.campay.base_url', 'https://www.campay.net/api'), '/');
        $ch = curl_init($base.'/'.ltrim($path, '/'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Token '.$token,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 40);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }
        $raw = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $decoded = json_decode((string) $raw, true);
        if (! is_array($decoded)) {
            Log::warning('Campay payout bad response', ['path' => $path, 'http' => $http]);

            return null;
        }
        if ($http >= 400 && ! isset($decoded['status'])) {
            $decoded['status'] = 'FAILED';
        }

        return $decoded;
    }

    protected function token()
    {
        $username = config('services.campay.username');
        $password = config('services.campay.password');
        if ($username && $password) {
            $base = rtrim((string) config('services.campay.base_url', 'https://www.campay.net/api'), '/');
            $ch = curl_init($base.'/token/');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'username' => $username,
                'password' => $password,
            ]));
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            $raw = curl_exec($ch);
            curl_close($ch);
            $decoded = json_decode((string) $raw, true);
            if (is_array($decoded) && ! empty($decoded['token'])) {
                return (string) $decoded['token'];
            }
        }
        $static = config('services.campay.token');

        return $static ? (string) $static : null;
    }
}

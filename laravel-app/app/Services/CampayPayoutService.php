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
        $this->massPay([$row]);

        return $row;
    }

    public function massPay(array $rows)
    {
        $rows = array_values($rows);
        if (count($rows) < 1) {
            return;
        }
        $sheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $grid = $sheet->getActiveSheet();
        $grid->setCellValue('A1', 'Numéro / Number (ex 237........)');
        $grid->setCellValue('B1', 'Nom / Name ou Description(Facultif / Optional)');
        $grid->setCellValue('C1', 'Amount');
        $rowNumber = 2;
        foreach ($rows as $row) {
            $phone = $this->momoNumber($row->phone);
            if (! $phone) {
                $row->status = 'failed';
                $row->error = 'This number is not an MTN or Orange Cameroon number.';
                $row->save();
                continue;
            }
            $row->phone = $phone;
            $grid->setCellValueExplicit('A'.$rowNumber, $phone, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $grid->setCellValue('B'.$rowNumber, (string) $row->person_name);
            $grid->setCellValue('C'.$rowNumber, (int) $row->amount);
            $rowNumber++;
        }
        if ($rowNumber === 2) {
            return;
        }
        $path = storage_path('app/campay-mass-'.date('YmdHis').'-'.mt_rand(1000, 9999).'.xlsx');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($sheet))->save($path);
        $body = $this->postFile('mass_payout/', $path);
        @unlink($path);
        $this->applyMassResult($rows, $body);
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

    protected function applyMassResult(array $rows, $body)
    {
        $items = $this->massItems($body);
        foreach ($rows as $row) {
            if ($row->status === 'failed' && $row->error === 'This number is not an MTN or Orange Cameroon number.') {
                continue;
            }
            $item = $this->matchMassItem($row, $items);
            $source = $item ?: (is_array($body) ? $body : []);
            $status = strtoupper((string) (isset($source['status']) ? $source['status'] : ''));
            if ($item === null && is_array($body) && isset($body['status']) && count($rows) > 1 && $items === []) {
                $status = strtoupper((string) $body['status']);
            }
            $row->campay_reference = $this->massReference($source) ?: $this->massReference(is_array($body) ? $body : []);
            $row->operator = isset($source['operator']) ? (string) $source['operator'] : $row->operator;
            if ($status === 'SUCCESSFUL') {
                $row->status = 'paid';
                $row->error = null;
            } elseif ($status === 'PENDING') {
                $row->status = 'pending';
                $row->error = 'Campay status: PENDING';
            } else {
                $row->status = 'failed';
                $row->error = is_array($body) ? $this->errorText($source !== [] ? $source : $body, $status) : 'Campay Mass Payout did not accept this payment.';
            }
            $row->save();
        }
    }

    protected function massItems($body)
    {
        if (! is_array($body)) {
            return [];
        }
        if (isset($body[0]) && is_array($body[0])) {
            return $body;
        }
        foreach (['results', 'payouts', 'withdrawals', 'data', 'transactions', 'beneficiaries'] as $key) {
            if (isset($body[$key]) && is_array($body[$key])) {
                return array_values($body[$key]);
            }
        }

        return [];
    }

    protected function matchMassItem(CampayPayout $row, array $items)
    {
        $phone = $this->momoNumber($row->phone);
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            foreach (['to', 'phone', 'phone_number', 'number', 'msisdn'] as $key) {
                if (! empty($item[$key]) && $this->momoNumber($item[$key]) === $phone) {
                    return $item;
                }
            }
        }
        if (count($items) === 1 && is_array($items[0])) {
            return $items[0];
        }

        return null;
    }

    protected function massReference(array $source)
    {
        foreach (['reference', 'campay_reference', 'uuid', 'id'] as $key) {
            if (! empty($source[$key]) && is_string($source[$key])) {
                return substr($source[$key], 0, 64);
            }
        }

        return null;
    }

    protected function postFile($path, $filePath)
    {
        $token = $this->token();
        if (! $token || ! is_file($filePath)) {
            Log::warning('Campay mass payout has no token');

            return null;
        }
        $base = rtrim((string) config('services.campay.base_url', 'https://www.campay.net/api'), '/');
        $ch = curl_init($base.'/'.ltrim($path, '/'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Token '.$token,
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'file' => new \CURLFile($filePath, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'mass-payout.xlsx'),
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        $raw = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $decoded = json_decode((string) $raw, true);
        if (! is_array($decoded)) {
            Log::warning('Campay mass payout bad response', ['path' => $path, 'http' => $http]);

            return null;
        }
        if ($http >= 400 && ! isset($decoded['status'])) {
            $decoded['status'] = 'FAILED';
        }

        return $decoded;
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

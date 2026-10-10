<?php

namespace App\Services;

class BinancePayService
{
    public function instructions($amountXaf, $donationId)
    {
        $usdt = $this->usdtAmount($amountXaf);
        if ($usdt === null) {
            return ['ok' => false, 'error' => 'The crypto rate could not be loaded. Try again in a moment.'];
        }
        $exact = number_format(((float) $usdt) + (((int) $donationId % 97) + 1) / 10000, 4, '.', '');
        $body = $this->signedGet('/sapi/v1/capital/deposit/address', [
            'coin' => 'USDT',
            'network' => 'TRX',
        ]);
        $address = is_array($body) && ! empty($body['address']) ? trim((string) $body['address']) : '';
        if ($address === '' || stripos($address, 'T') !== 0) {
            return ['ok' => false, 'error' => $this->spotError($body)];
        }

        return [
            'ok' => true,
            'address' => $address,
            'network' => 'Tron (TRC20)',
            'usdt' => $exact,
            'coin' => 'USDT',
        ];
    }

    public function findPayment($address, $usdt, $sinceMs)
    {
        $rows = $this->signedGet('/sapi/v1/capital/deposit/hisrec', [
            'coin' => 'USDT',
            'startTime' => (int) $sinceMs,
            'limit' => 50,
        ]);
        if (! is_array($rows) || isset($rows['msg']) || isset($rows['code'])) {
            return null;
        }
        $wanted = number_format((float) $usdt, 4, '.', '');
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (trim((string) (isset($row['address']) ? $row['address'] : '')) !== $address) {
                continue;
            }
            $amount = number_format((float) (isset($row['amount']) ? $row['amount'] : 0), 4, '.', '');
            if ($amount !== $wanted) {
                continue;
            }
            $state = (int) (isset($row['status']) ? $row['status'] : 0);
            if (! in_array($state, [1, 6], true)) {
                continue;
            }
            $tx = trim((string) (isset($row['txId']) ? $row['txId'] : ''));
            if ($tx !== '') {
                return substr($tx, 0, 64);
            }
        }

        return null;
    }

    public function createOrder($tradeNo, $amountXaf, $description, $returnUrl)
    {
        $usdt = $this->usdtAmount($amountXaf);
        if ($usdt === null) {
            return ['ok' => false, 'error' => 'The crypto rate could not be loaded. Try again in a moment.'];
        }
        $body = [
            'env' => ['terminalType' => 'WEB'],
            'merchantTradeNo' => (string) $tradeNo,
            'orderAmount' => $usdt,
            'currency' => 'USDT',
            'description' => substr((string) $description, 0, 80),
            'returnUrl' => (string) $returnUrl,
            'cancelUrl' => (string) $returnUrl,
            'goodsDetails' => [[
                'goodsType' => '02',
                'goodsCategory' => 'Z000',
                'referenceGoodsId' => (string) $tradeNo,
                'goodsName' => 'Donation',
                'goodsDetail' => substr((string) $description, 0, 80),
            ]],
        ];
        $decoded = $this->post('/binancepay/openapi/v3/order', $body);
        if (! is_array($decoded) || (isset($decoded['status']) ? $decoded['status'] : '') !== 'SUCCESS') {
            return ['ok' => false, 'error' => $this->errorText($decoded)];
        }
        $data = isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : [];
        $url = '';
        foreach (['checkoutUrl', 'universalUrl', 'deeplink', 'qrcodeLink'] as $key) {
            if (! empty($data[$key]) && is_string($data[$key])) {
                $url = $data[$key];
                break;
            }
        }
        if ($url === '') {
            return ['ok' => false, 'error' => 'Binance did not return a payment page.'];
        }

        return ['ok' => true, 'url' => $url, 'usdt' => $usdt];
    }

    public function status($tradeNo)
    {
        $decoded = $this->post('/binancepay/openapi/v2/order/query', [
            'merchantTradeNo' => (string) $tradeNo,
        ]);
        if (! is_array($decoded) || (isset($decoded['status']) ? $decoded['status'] : '') !== 'SUCCESS') {
            return '';
        }
        $data = isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : [];

        return strtoupper((string) (isset($data['status']) ? $data['status'] : ''));
    }

    public function usdtAmount($amountXaf)
    {
        $rate = $this->eurUsdt();
        if ($rate === null || $rate <= 0) {
            return null;
        }
        $usdt = round(((float) $amountXaf) / 655.957 * $rate, 2);
        if ($usdt < 0.01) {
            $usdt = 0.01;
        }

        return number_format($usdt, 2, '.', '');
    }

    protected function eurUsdt()
    {
        $ch = curl_init('https://api.binance.com/api/v3/ticker/price?symbol=EURUSDT');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $raw = curl_exec($ch);
        curl_close($ch);
        $decoded = json_decode((string) $raw, true);
        if (! is_array($decoded) || ! isset($decoded['price']) || ! is_numeric($decoded['price'])) {
            return null;
        }

        return (float) $decoded['price'];
    }

    protected function post($path, array $body)
    {
        $key = trim((string) config('services.binance.key'));
        $secret = trim((string) config('services.binance.secret'));
        if ($key === '' || $secret === '') {
            return ['status' => 'FAIL', 'errorMessage' => 'Crypto payments are not configured.'];
        }
        $json = json_encode($body);
        $timestamp = (string) round(microtime(true) * 1000);
        $nonce = substr(bin2hex(random_bytes(16)), 0, 32);
        $payload = $timestamp."\n".$nonce."\n".$json."\n";
        $signature = strtoupper(hash_hmac('sha512', $payload, $secret));
        $base = rtrim((string) config('services.binance.base_url', 'https://bpay.binanceapi.com'), '/');
        $ch = curl_init($base.$path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_TIMEOUT, 25);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'BinancePay-Timestamp: '.$timestamp,
            'BinancePay-Nonce: '.$nonce,
            'BinancePay-Certificate-SN: '.$key,
            'BinancePay-Signature: '.$signature,
        ]);
        $raw = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $decoded = json_decode((string) $raw, true);
        if (! is_array($decoded)) {
            \Log::warning('Binance Pay did not return JSON', ['http' => $http, 'path' => $path]);

            return ['status' => 'FAIL', 'errorMessage' => 'Binance did not answer.'];
        }
        if ($http >= 400 && ! isset($decoded['errorMessage'])) {
            $decoded['errorMessage'] = 'Binance refused this payment.';
        }

        return $decoded;
    }

    protected function signedGet($path, array $params)
    {
        $key = trim((string) config('services.binance.key'));
        $secret = trim((string) config('services.binance.secret'));
        if ($key === '' || $secret === '') {
            return ['msg' => 'Crypto payments are not configured.'];
        }
        $params['timestamp'] = (string) round(microtime(true) * 1000);
        $params['recvWindow'] = 10000;
        $query = http_build_query($params);
        $signature = hash_hmac('sha256', $query, $secret);
        $ch = curl_init('https://api.binance.com'.$path.'?'.$query.'&signature='.$signature);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-MBX-APIKEY: '.$key]);
        $raw = curl_exec($ch);
        curl_close($ch);
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : ['msg' => 'Binance did not answer.'];
    }

    protected function spotError($body)
    {
        $message = is_array($body) && ! empty($body['msg']) ? trim((string) $body['msg']) : '';
        if ($message === '') {
            return 'Binance could not open a crypto payment.';
        }

        return substr($message, 0, 180);
    }

    protected function errorText($decoded)
    {
        if (! is_array($decoded)) {
            return 'Binance did not answer.';
        }
        $message = isset($decoded['errorMessage']) ? trim((string) $decoded['errorMessage']) : '';
        if ($message === '') {
            return 'Binance could not open this payment.';
        }

        return substr($message, 0, 180);
    }
}

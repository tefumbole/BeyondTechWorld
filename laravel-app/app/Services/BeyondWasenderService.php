<?php

namespace App\Services;

use App\Support\WhatsAppPhone;

class BeyondWasenderService
{
    /** Wasender account protection: one outbound message every 5s, measured after the previous send finishes. */
    const SEND_INTERVAL_SECONDS = 5.5;

    private static $lastSendAt = 0.0;

    protected function sendIntervalSeconds()
    {
        $configured = ((int) config('services.whatsapp.min_send_interval_ms', 5500)) / 1000.0;

        return max(self::SEND_INTERVAL_SECONDS, $configured > 0 ? $configured : self::SEND_INTERVAL_SECONDS);
    }

    protected function beginOutboundSend()
    {
        $interval = $this->sendIntervalSeconds();
        $dir = storage_path('app');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $path = $dir.'/whatsapp-send.lock';
        $fp = @fopen($path, 'c+');
        if (! $fp) {
            $this->waitSince($interval, self::$lastSendAt);

            return null;
        }
        flock($fp, LOCK_EX);
        $raw = stream_get_contents($fp);
        $last = is_numeric(trim((string) $raw)) ? (float) trim($raw) : self::$lastSendAt;
        $this->waitSince($interval, $last);

        return $fp;
    }

    protected function endOutboundSend($fp)
    {
        $now = microtime(true);
        if ($fp) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, sprintf('%.6f', $now));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
        }
        self::$lastSendAt = $now;
    }

    protected function waitSince($interval, $last)
    {
        if ($last <= 0) {
            return;
        }
        $wait = $interval - (microtime(true) - $last);
        if ($wait > 0) {
            usleep((int) round($wait * 1000000));
        }
    }

    protected function isProtectionError($error)
    {
        return is_string($error) && $error !== '' && preg_match(
            '/account protection|every 5 seconds|rate.?limit|too many messages/i',
            $error
        );
    }

    /**
     * POST /send-message with a process-wide lock and one automatic retry on Wasender protection.
     *
     * @return array{success:bool,http?:int,decoded:?array,error?:string,body?:string}
     */
    protected function postSendMessage(array $payload, $timeout = 30)
    {
        $fp = $this->beginOutboundSend();
        try {
            $result = $this->curlSendMessage($payload, $timeout);
            if ($this->isProtectionError(isset($result['error']) ? $result['error'] : '')) {
                usleep((int) round($this->sendIntervalSeconds() * 1000000));
                $result = $this->curlSendMessage($payload, $timeout);
            }

            return $result;
        } finally {
            $this->endOutboundSend($fp);
        }
    }

    protected function curlSendMessage(array $payload, $timeout)
    {
        $base = rtrim(config('services.whatsapp.wasender_base_url', 'https://wasenderapi.com/api'), '/');
        $ch = curl_init($base.'/send-message');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '.config('services.whatsapp.wasender_api_key'),
                'Accept: application/json',
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => (int) $timeout,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($err) {
            return ['success' => false, 'error' => $err, 'http' => $http, 'decoded' => null, 'body' => null];
        }
        $decoded = json_decode($body, true);
        $apiSuccess = is_array($decoded) ? ($decoded['success'] ?? null) : null;
        $apiMessage = is_array($decoded)
            ? (string) ($decoded['message'] ?? $decoded['error'] ?? '')
            : '';
        $looksFailed = $http >= 400
            || $apiSuccess === false
            || ($apiSuccess !== true && $apiMessage !== '' && preg_match(
                '/not connected|rejected|does not exist|rate|protection|failed|invalid|unauthorized/i',
                $apiMessage
            ));
        if ($looksFailed) {
            return [
                'success' => false,
                'error' => $apiMessage !== '' ? $apiMessage : ('HTTP '.$http),
                'http' => $http,
                'decoded' => $decoded,
                'body' => is_string($body) ? $body : null,
            ];
        }

        return [
            'success' => true,
            'http' => $http,
            'decoded' => $decoded,
            'error' => null,
            'body' => is_string($body) ? $body : null,
        ];
    }

    public function isConfigured()
    {
        $key = config('services.whatsapp.wasender_api_key');
        $session = config('services.whatsapp.wasender_session_id');

        return ! empty($key) && ! empty($session) && strpos($key, 'your_') !== 0;
    }

    public function formatPhone($phone)
    {
        return WhatsAppPhone::forWasender($phone);
    }

    public function sendOtp($phone, $code, $label = 'login')
    {
        // Route through NotificationRouter (Twilio Content SID when configured, else Wasender).
        return app(\App\Services\Messaging\NotificationRouter::class)
            ->sendWhatsAppOtp($phone, $code, $label, 10);
    }

    /**
     * Public send — respects WHATSAPP_SERVICE (Wasender by default; Twilio templates when TWILIO).
     */
    public function sendText($phone, $message)
    {
        return app(\App\Services\Messaging\NotificationRouter::class)
            ->sendWhatsAppText($phone, $message);
    }

    /**
     * Direct WasenderAPI send (used only as NotificationRouter fallback).
     */
    public function sendTextRaw($phone, $message)
    {
        if (! $this->isConfigured()) {
            if (app()->environment('local')) {
                \Log::info('[beyond-whatsapp] Wasender not configured — message: '.$message);

                return ['success' => true, 'dev' => true];
            }

            \Log::warning('[beyond-whatsapp] Wasender not configured (missing WASENDER_API_KEY or WASENDER_SESSION_ID)');

            return ['success' => false, 'error' => 'WhatsApp messaging is not configured.'];
        }

        try {
            $to = $this->formatPhone($phone);
            if (! $to) {
                return ['success' => false, 'error' => 'Invalid WhatsApp number'];
            }

            $message = \App\Support\LetterReference::applyToMessage((string) $message, 'whatsapp');
            $posted = $this->postSendMessage(['to' => $to, 'text' => $message], 30);
            $decoded = $posted['decoded'];
            $http = (int) ($posted['http'] ?? 0);
            if (empty($posted['success'])) {
                \Log::warning('[beyond-whatsapp] send failed', [
                    'error' => $posted['error'] ?? 'unknown',
                    'to' => $to,
                    'http' => $http,
                    'body' => isset($posted['body']) ? substr((string) $posted['body'], 0, 500) : null,
                ]);

                return ['success' => false, 'error' => $posted['error'] ?? 'send failed'];
            }

            return [
                'success' => true,
                'http' => $http,
                'msg_id' => is_array($decoded) ? ($decoded['data']['msgId'] ?? null) : null,
                'status' => is_array($decoded) ? ($decoded['data']['status'] ?? null) : null,
            ];
        } catch (\Throwable $e) {
            \Log::warning('[beyond-whatsapp] exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function maskPhone($phone)
    {
        $formatted = $this->formatPhone($phone);
        if (strlen($formatted) < 8) {
            return $phone;
        }

        return substr($formatted, 0, 6).'****'.substr($formatted, -2);
    }

    /**
     * Upload a local file to Wasender and send it as a document attachment.
     *
     * @return array{success:bool,error?:string,msg_id?:mixed,publicUrl?:string}
     */
    public function sendDocument($phone, $localPath, $fileName = null, $caption = null)
    {
        if (! $this->isConfigured()) {
            if (app()->environment('local')) {
                \Log::info('[beyond-whatsapp] Wasender not configured — skip document', [
                    'path' => $localPath,
                    'file' => $fileName,
                ]);

                return ['success' => true, 'dev' => true];
            }

            return ['success' => false, 'error' => 'WhatsApp messaging is not configured.'];
        }

        if (! is_file($localPath)) {
            return ['success' => false, 'error' => 'Document file not found.'];
        }

        $fileName = $fileName ?: basename($localPath);

        try {
            $to = $this->formatPhone($phone);
            if (! $to) {
                return ['success' => false, 'error' => 'Invalid WhatsApp number'];
            }

            $publicUrl = $this->uploadLocalFile($localPath);
            if (empty($publicUrl)) {
                return ['success' => false, 'error' => 'Wasender upload did not return a public URL.'];
            }

            $caption = \App\Support\LetterReference::applyToMessage(
                (string) ($caption !== null && $caption !== '' ? $caption : $fileName),
                'whatsapp'
            );
            $posted = $this->postSendMessage([
                'to' => $to,
                'documentUrl' => $publicUrl,
                'fileName' => $fileName,
                'text' => $caption !== null && $caption !== '' ? $caption : $fileName,
            ], 60);
            if (empty($posted['success'])) {
                return ['success' => false, 'error' => $posted['error'] ?? 'send failed'];
            }
            $decoded = $posted['decoded'];

            return [
                'success' => true,
                'http' => (int) ($posted['http'] ?? 0),
                'publicUrl' => $publicUrl,
                'msg_id' => is_array($decoded) ? ($decoded['data']['msgId'] ?? null) : null,
            ];
        } catch (\Throwable $e) {
            \Log::warning('[beyond-whatsapp] document send exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Upload a local image and send it as a WhatsApp image (not a document).
     *
     * @return array{success:bool,error?:string,msg_id?:mixed,publicUrl?:string}
     */
    public function sendImage($phone, $localPath, $caption = null)
    {
        if (! $this->isConfigured()) {
            if (app()->environment('local')) {
                \Log::info('[beyond-whatsapp] Wasender not configured — skip image', [
                    'path' => $localPath,
                ]);

                return ['success' => true, 'dev' => true];
            }

            return ['success' => false, 'error' => 'WhatsApp messaging is not configured.'];
        }

        if (! is_file($localPath)) {
            return ['success' => false, 'error' => 'Image file not found.'];
        }

        try {
            $to = $this->formatPhone($phone);
            if (! $to) {
                return ['success' => false, 'error' => 'Invalid WhatsApp number'];
            }

            $publicUrl = $this->uploadLocalFile($localPath);
            if (empty($publicUrl)) {
                return ['success' => false, 'error' => 'Wasender upload did not return a public URL.'];
            }

            $payload = [
                'to' => $to,
                'imageUrl' => $publicUrl,
            ];
            if ($caption !== null && trim((string) $caption) !== '') {
                $payload['text'] = \App\Support\LetterReference::applyToMessage(
                    (string) $caption,
                    'whatsapp'
                );
            }
            $posted = $this->postSendMessage($payload, 60);
            if (empty($posted['success'])) {
                return ['success' => false, 'error' => $posted['error'] ?? 'send failed'];
            }
            $decoded = $posted['decoded'];

            return [
                'success' => true,
                'http' => (int) ($posted['http'] ?? 0),
                'publicUrl' => $publicUrl,
                'msg_id' => is_array($decoded) ? ($decoded['data']['msgId'] ?? null) : null,
            ];
        } catch (\Throwable $e) {
            \Log::warning('[beyond-whatsapp] image send exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function uploadLocalFile($path)
    {
        $mime = self::mimeTypeForPath($path);

        $base = rtrim(config('services.whatsapp.wasender_base_url', 'https://wasenderapi.com/api'), '/');
        $url = $base.'/upload';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '.config('services.whatsapp.wasender_api_key'),
                'Accept: application/json',
                'Content-Type: '.$mime,
            ],
            CURLOPT_POSTFIELDS => file_get_contents($path),
            CURLOPT_TIMEOUT => 90,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new \Exception($err);
        }

        $decoded = json_decode($body, true);
        if (! empty($decoded['publicUrl'])) {
            return $decoded['publicUrl'];
        }

        throw new \Exception($decoded['message'] ?? $decoded['error'] ?? 'Wasender upload failed.');
    }

    /**
     * Wasender rejects application/octet-stream. Prefer extension mapping for Office docs
     * because mime_content_type often returns octet-stream for .docx on Linux.
     */
    public static function mimeTypeForPath($path, $fileName = null)
    {
        $name = $fileName ?: basename((string) $path);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $byExt = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'zip' => 'application/zip',
        ];
        if (isset($byExt[$ext])) {
            return $byExt[$ext];
        }

        $detected = null;
        if (is_file($path) && function_exists('mime_content_type')) {
            $detected = @mime_content_type($path) ?: null;
        }
        if ($detected && $detected !== 'application/octet-stream') {
            return $detected;
        }

        return 'application/octet-stream';
    }

    /**
     * Resolve a WhatsApp display name for a phone (same Wasender contacts API used in voting).
     * Returns null when Wasender is unavailable or no name is found.
     */
    public function getContactName($phone): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $to = $this->formatPhone($phone);
            if (! $to) {
                return null;
            }
            $digits = ltrim(preg_replace('/\D+/', '', $to), '0');
            if ($digits === '') {
                return null;
            }

            $base = rtrim(config('services.whatsapp.wasender_base_url', 'https://wasenderapi.com/api'), '/');
            $url = $base.'/contacts/'.rawurlencode($digits);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_HTTPGET => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer '.config('services.whatsapp.wasender_api_key'),
                    'Accept: application/json',
                ],
                CURLOPT_TIMEOUT => 20,
            ]);
            $body = curl_exec($ch);
            $err = curl_error($ch);
            $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($err || $http >= 400 || ! is_string($body)) {
                \Log::info('[beyond-whatsapp] contact lookup failed', [
                    'phone' => $digits,
                    'http' => $http,
                    'error' => $err ?: null,
                ]);

                return null;
            }

            $decoded = json_decode($body, true);
            $data = is_array($decoded) ? ($decoded['data'] ?? $decoded) : null;
            if (! is_array($data)) {
                return null;
            }

            foreach (['name', 'notify', 'verifiedName', 'verified_name', 'pushname', 'pushName'] as $key) {
                $value = trim((string) ($data[$key] ?? ''));
                if ($value !== '' && strcasecmp($value, $digits) !== 0) {
                    return $value;
                }
            }
        } catch (\Throwable $e) {
            \Log::info('[beyond-whatsapp] contact lookup exception', ['error' => $e->getMessage()]);
        }

        return null;
    }

    public function listGroups()
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'groups' => []];
        }
        $base = rtrim(config('services.whatsapp.wasender_base_url', 'https://wasenderapi.com/api'), '/');
        $ch = curl_init($base.'/groups');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '.config('services.whatsapp.wasender_api_key'),
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 20,
        ]);
        $body = curl_exec($ch);
        $decoded = json_decode((string) $body, true);
        $rows = [];
        $data = is_array($decoded) && isset($decoded['data']) ? $decoded['data'] : [];
        if (is_array($data)) {
            foreach ($data as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $rows[] = [
                    'jid' => isset($row['id']) ? $row['id'] : (isset($row['jid']) ? $row['jid'] : ''),
                    'name' => isset($row['subject']) ? $row['subject'] : (isset($row['name']) ? $row['name'] : ''),
                    'description' => isset($row['description']) ? $row['description'] : null,
                ];
            }
        }

        return ['success' => true, 'groups' => $rows];
    }

    public function sendGroupText($groupJid, $message)
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'WhatsApp messaging is not configured.'];
        }
        $posted = $this->postSendMessage(['to' => $groupJid, 'text' => (string) $message], 30);
        if (empty($posted['success'])) {
            return ['success' => false, 'error' => isset($posted['error']) ? $posted['error'] : 'send failed'];
        }
        $decoded = isset($posted['decoded']) ? $posted['decoded'] : [];

        return [
            'success' => true,
            'msg_id' => is_array($decoded) && isset($decoded['data']['msgId']) ? $decoded['data']['msgId'] : null,
        ];
    }
}

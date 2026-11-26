<?php

namespace App\Services\WhatsApp;

use App\WhatsApp\WhatsAppMessage;
use App\WhatsApp\WhatsAppWebhookEvent;

class WhatsAppMediaLibrary
{
    public function hydrate(WhatsAppMessage $message)
    {
        $type = strtoupper((string) $message->type);
        $media = $message->media();
        $missing = empty($media['url']) && empty($media['stored']);
        if (! $missing && ! in_array($type, ['UNKNOWN', ''], true)) {
            return $message;
        }
        if (! $message->provider_message_id) {
            return $message;
        }
        $events = WhatsAppWebhookEvent::where('provider_event_id', $message->provider_message_id)
            ->orderByDesc('id')
            ->limit(8)
            ->get();
        $parser = app(WaSenderEventParser::class);
        $best = null;
        foreach ($events as $event) {
            $parsed = $parser->parse($event->payloadArray());
            if (! empty($parsed['media']['url'])) {
                $best = $parsed;
                break;
            }
            if (! $best && ! empty($parsed['message_type']) && $parsed['message_type'] !== 'UNKNOWN') {
                $best = $parsed;
            }
        }
        if (! $best) {
            return $message;
        }
        $changed = false;
        if ($missing && ! empty($best['media']) && is_array($best['media'])) {
            $message->media_json = json_encode($best['media']);
            $changed = true;
        }
        $newType = strtoupper((string) (isset($best['message_type']) ? $best['message_type'] : ''));
        if ($newType !== '' && $newType !== 'UNKNOWN' && ($type === '' || $type === 'UNKNOWN')) {
            $message->type = $newType;
            $changed = true;
        }
        $body = isset($best['body']) ? trim((string) $best['body']) : '';
        if ($body !== '' && trim((string) $message->body) === '') {
            $message->body = $body;
            $changed = true;
        }
        if ($changed) {
            $message->save();
        }

        return $message->fresh() ?: $message;
    }

    public function file(WhatsAppMessage $message)
    {
        $media = $message->media();
        $stored = isset($media['stored']) ? (string) $media['stored'] : '';
        if ($stored !== '' && strpos($stored, 'whatsapp-media/') === 0) {
            $path = storage_path('app/'.$stored);
            if (is_file($path)) {
                return [
                    'path' => $path,
                    'mime' => $this->mime($media, $message),
                    'name' => $this->downloadName($media, $message),
                ];
            }
        }

        $public = isset($media['url']) ? (string) $media['url'] : '';
        if ($public !== '' && strpos($public, 'whatsapp-chat/') === 0) {
            $path = public_path($public);
            if (is_file($path)) {
                return [
                    'path' => $path,
                    'mime' => $this->mime($media, $message),
                    'name' => $this->downloadName($media, $message),
                ];
            }
        }

        $remote = $this->decrypt($message);
        if ($remote === '') {
            return $this->thumbFile($message);
        }
        $saved = $this->saveRemote($message, $remote, $media);
        if (! $saved) {
            return null;
        }

        return $saved;
    }

    protected function decrypt(WhatsAppMessage $message)
    {
        if (! $message->provider_message_id) {
            return '';
        }
        $event = WhatsAppWebhookEvent::where('provider_event_id', $message->provider_message_id)->orderByDesc('id')->first();
        if (! $event) {
            return '';
        }
        $payload = $event->payloadArray();
        $messages = isset($payload['data']['messages']) && is_array($payload['data']['messages']) ? $payload['data']['messages'] : [];
        $inner = isset($messages['message']) && is_array($messages['message']) ? $messages['message'] : [];
        $keyName = $this->blockName($message->type);
        $block = ($keyName && isset($inner[$keyName]) && is_array($inner[$keyName])) ? $inner[$keyName] : [];
        if (! $block) {
            foreach (['imageMessage', 'videoMessage', 'audioMessage', 'documentMessage', 'stickerMessage'] as $name) {
                if (isset($inner[$name]) && is_array($inner[$name]) && ! empty($inner[$name]['mediaKey'])) {
                    $keyName = $name;
                    $block = $inner[$name];
                    break;
                }
            }
        }
        if (! $keyName || empty($block['url']) || empty($block['mediaKey'])) {
            return '';
        }
        if (! empty($block['viewOnce'])) {
            $media = $message->media();
            if (empty($media['view_once'])) {
                $media['view_once'] = true;
                $message->media_json = json_encode($media);
                $message->save();
            }
        }
        $fields = array_intersect_key($block, array_flip(['url', 'mimetype', 'mediaKey', 'fileSha256', 'fileLength', 'fileName']));
        $id = isset($messages['key']['id']) ? (string) $messages['key']['id'] : (string) $message->provider_message_id;
        $base = rtrim((string) config('services.whatsapp.wasender_base_url', 'https://wasenderapi.com/api'), '/');
        $ch = curl_init($base.'/decrypt-media');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '.config('services.whatsapp.wasender_api_key'),
                'Accept: application/json',
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'data' => [
                    'messages' => [
                        'key' => ['id' => $id],
                        'message' => [$keyName => $fields],
                    ],
                ],
            ]),
            CURLOPT_TIMEOUT => 90,
        ]);
        $body = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $decoded = json_decode((string) $body, true);
        $url = is_array($decoded) && ! empty($decoded['publicUrl']) ? (string) $decoded['publicUrl'] : '';
        if ($http >= 400 || $url === '' || ! preg_match('#^https://#i', $url)) {
            \Log::warning('[whatsapp-media] decrypt failed', ['message_id' => $message->id, 'http' => $http]);

            return '';
        }

        return $url;
    }

    protected function saveRemote(WhatsAppMessage $message, $url, array $media)
    {
        $dir = storage_path('app/whatsapp-media');
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true)) {
            return null;
        }
        $ext = $this->extension($this->mime($media, $message), isset($media['file_name']) ? $media['file_name'] : '');
        $relative = 'whatsapp-media/'.$message->id.'.'.$ext;
        $path = storage_path('app/'.$relative);
        $fp = fopen($path, 'wb');
        if (! $fp) {
            return null;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_MAXFILESIZE => 80 * 1024 * 1024,
        ]);
        curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);
        if ($http >= 400 || ! is_file($path) || filesize($path) < 32) {
            @unlink($path);
            \Log::warning('[whatsapp-media] download failed', ['message_id' => $message->id, 'http' => $http]);

            return null;
        }
        $media['stored'] = $relative;
        $message->media_json = json_encode($media);
        $message->save();

        return [
            'path' => $path,
            'mime' => $this->mime($media, $message),
            'name' => $this->downloadName($media, $message),
        ];
    }

    protected function thumbFile(WhatsAppMessage $message)
    {
        if (! $message->provider_message_id) {
            return null;
        }
        $events = WhatsAppWebhookEvent::where('provider_event_id', $message->provider_message_id)
            ->orderByDesc('id')
            ->limit(6)
            ->get();
        $thumb = '';
        foreach ($events as $event) {
            $thumb = $this->findThumb($event->payloadArray());
            if ($thumb !== '') {
                break;
            }
        }
        $bin = base64_decode($thumb, true);
        if ($bin === false || strlen($bin) < 32 || strlen($bin) > 200000) {
            return null;
        }
        $dir = storage_path('app/whatsapp-media');
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true)) {
            return null;
        }
        $relative = 'whatsapp-media/'.$message->id.'-thumb.jpg';
        $path = storage_path('app/'.$relative);
        if (file_put_contents($path, $bin) === false) {
            return null;
        }
        $media = $message->media();
        $media['thumb'] = $relative;
        $message->media_json = json_encode($media);
        $message->save();

        return [
            'path' => $path,
            'mime' => 'image/jpeg',
            'name' => 'preview-'.$message->id.'.jpg',
        ];
    }

    protected function findThumb(array $payload)
    {
        $messages = isset($payload['data']['messages']) && is_array($payload['data']['messages']) ? $payload['data']['messages'] : [];
        $inner = isset($messages['message']) && is_array($messages['message']) ? $messages['message'] : [];
        foreach (['imageMessage', 'videoMessage', 'stickerMessage'] as $name) {
            if (! empty($inner[$name]['jpegThumbnail']) && is_string($inner[$name]['jpegThumbnail'])) {
                return $inner[$name]['jpegThumbnail'];
            }
        }

        return '';
    }

    protected function blockName($type)
    {
        $map = [
            'IMAGE' => 'imageMessage',
            'STICKER' => 'stickerMessage',
            'VIDEO' => 'videoMessage',
            'AUDIO' => 'audioMessage',
            'DOCUMENT' => 'documentMessage',
        ];
        $type = strtoupper((string) $type);

        return isset($map[$type]) ? $map[$type] : null;
    }

    protected function mime(array $media, WhatsAppMessage $message)
    {
        if (! empty($media['mimetype']) && is_string($media['mimetype'])) {
            return $media['mimetype'];
        }
        $type = strtoupper((string) $message->type);
        if ($type === 'IMAGE' || $type === 'STICKER') {
            return $type === 'STICKER' ? 'image/webp' : 'image/jpeg';
        }
        if ($type === 'VIDEO') {
            return 'video/mp4';
        }
        if ($type === 'AUDIO') {
            return 'audio/ogg';
        }

        return 'application/octet-stream';
    }

    protected function extension($mime, $fileName)
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/3gpp' => '3gp',
            'audio/ogg' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/mp4' => 'm4a',
            'application/pdf' => 'pdf',
        ];
        $mime = strtolower(trim(explode(';', (string) $mime)[0]));
        if (isset($map[$mime])) {
            return $map[$mime];
        }
        $ext = strtolower(pathinfo((string) $fileName, PATHINFO_EXTENSION));
        if ($ext !== '' && preg_match('/^[a-z0-9]{1,5}$/', $ext)) {
            return $ext;
        }

        return 'bin';
    }

    protected function downloadName(array $media, WhatsAppMessage $message)
    {
        $name = isset($media['file_name']) ? (string) $media['file_name'] : '';
        $name = preg_replace('/[^A-Za-z0-9._-]/', '', $name);
        if ($name === '') {
            $name = strtolower((string) $message->type).'-'.$message->id;
        }

        return $name;
    }
}

<?php

namespace App\Services\WhatsApp;

use App\WhatsApp\WhatsAppMessage;

class WhatsAppAudioTranscriber
{
    public function forAssistant(array $media)
    {
        unset($media['url'], $media['media_key'], $media['file_sha256'], $media['file_enc_sha256'], $media['provider_message_id']);

        return $media;
    }

    public function acceptedMime($mime)
    {
        $mime = strtolower(trim(strtok((string) $mime, ';')));
        $allowed = [
            'audio/ogg', 'audio/opus', 'audio/mpeg', 'audio/mp4', 'audio/aac',
            'audio/wav', 'audio/x-wav', 'audio/webm', 'audio/mp3',
        ];

        return in_array($mime, $allowed, true);
    }

    public function withinSize($bytes)
    {
        $bytes = (int) $bytes;
        if ($bytes <= 0) {
            return true;
        }

        return $bytes <= (int) config('assistant.transcription_max_bytes');
    }

    public function transcribe(WhatsAppMessage $message)
    {
        $media = $message->media();
        $media['provider_message_id'] = (string) $message->provider_message_id;
        if (! empty($media['transcript'])) {
            return [
                'success' => true,
                'text' => (string) $media['transcript'],
                'language' => isset($media['spoken_language']) ? $media['spoken_language'] : null,
                'cached' => true,
            ];
        }
        if (! $this->acceptedMime(isset($media['mimetype']) ? $media['mimetype'] : '')) {
            return ['success' => false, 'error' => 'unsupported_audio'];
        }
        if (! $this->withinSize(isset($media['file_length']) ? $media['file_length'] : 0)) {
            return ['success' => false, 'error' => 'audio_too_large'];
        }

        $file = $this->download($media);
        if (empty($file['path'])) {
            return ['success' => false, 'error' => isset($file['error']) ? $file['error'] : 'audio_unavailable'];
        }
        try {
            $heard = $this->transcribeFile($file['path'], isset($media['mimetype']) ? $media['mimetype'] : 'audio/ogg');
        } finally {
            if (is_file($file['path'])) {
                @unlink($file['path']);
            }
        }
        if (empty($heard['text'])) {
            return ['success' => false, 'error' => isset($heard['error']) ? $heard['error'] : 'audio_unclear'];
        }
        $media['transcript'] = $heard['text'];
        $media['spoken_language'] = isset($heard['language']) ? $heard['language'] : null;
        unset($media['url'], $media['media_key'], $media['file_sha256'], $media['file_enc_sha256'], $media['provider_message_id']);
        $message->media_json = json_encode($media);
        $message->body = $heard['text'];
        $message->save();

        return [
            'success' => true,
            'text' => $heard['text'],
            'language' => $media['spoken_language'],
            'cached' => false,
        ];
    }

    protected function download(array $media)
    {
        $url = isset($media['url']) ? (string) $media['url'] : '';
        if ($url !== '' && ! $this->needsDecrypt($url)) {
            return $this->saveRemote($url);
        }
        $public = $this->decrypt($media, isset($media['provider_message_id']) ? $media['provider_message_id'] : '');
        if ($public === '') {
            return ['error' => 'audio_unavailable'];
        }

        return $this->saveRemote($public);
    }

    protected function needsDecrypt($url)
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === '' || strpos($host, 'whatsapp.net') !== false || strpos($host, 'whatsapp.com') !== false;
    }

    protected function decrypt(array $media, $messageId)
    {
        $key = trim((string) config('services.whatsapp.wasender_api_key'));
        if ($key === '' || empty($media['media_key']) || empty($media['url']) || $messageId === '') {
            return '';
        }
        $base = rtrim((string) config('services.whatsapp.wasender_base_url', 'https://wasenderapi.com/api'), '/');
        $payload = [
            'data' => [
                'messages' => [
                    'key' => ['id' => (string) $messageId],
                    'message' => [
                        'audioMessage' => array_filter([
                            'url' => $media['url'],
                            'mimetype' => isset($media['mimetype']) ? $media['mimetype'] : null,
                            'mediaKey' => $media['media_key'],
                            'fileSha256' => isset($media['file_sha256']) ? $media['file_sha256'] : null,
                            'fileEncSha256' => isset($media['file_enc_sha256']) ? $media['file_enc_sha256'] : null,
                            'fileLength' => isset($media['file_length']) ? (string) $media['file_length'] : null,
                        ]),
                    ],
                ],
            ],
        ];
        $decoded = $this->postJson($base.'/decrypt-media', $key, $payload);
        if (! is_array($decoded)) {
            return '';
        }
        foreach (['publicUrl', 'url'] as $field) {
            if (! empty($decoded[$field]) && is_string($decoded[$field])) {
                return $decoded[$field];
            }
        }
        if (! empty($decoded['data']['publicUrl'])) {
            return (string) $decoded['data']['publicUrl'];
        }

        return '';
    }

    protected function saveRemote($url)
    {
        $max = (int) config('assistant.transcription_max_bytes');
        $path = tempnam(sys_get_temp_dir(), 'mbole-audio-');
        if ($path === false) {
            return ['error' => 'audio_unavailable'];
        }
        $written = 0;
        $handle = fopen($path, 'wb');
        if (! $handle) {
            @unlink($path);

            return ['error' => 'audio_unavailable'];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $handle,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => (int) config('assistant.transcription_timeout'),
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => function ($resource, $downloadTotal, $downloaded, $uploadTotal, $uploaded) use ($max, &$written) {
                $written = (int) $downloaded;
                if ($written > $max) {
                    return 1;
                }

                return 0;
            },
        ]);
        $ok = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($handle);
        if (! $ok || $error !== '' || $written > $max || ! is_file($path) || filesize($path) < 32) {
            @unlink($path);

            return ['error' => $written > $max ? 'audio_too_large' : 'audio_unavailable'];
        }

        return ['path' => $path];
    }

    protected function transcribeFile($path, $mime)
    {
        $key = trim((string) config('assistant.api_key'));
        if ($key === '') {
            return ['error' => 'transcription_not_configured'];
        }
        $url = (string) config('assistant.transcription_url');
        $file = new \CURLFile($path, $mime, 'voice.ogg');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int) config('assistant.transcription_timeout'),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '.$key,
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => [
                'model' => (string) config('assistant.transcription_model'),
                'response_format' => 'verbose_json',
                'file' => $file,
            ],
        ]);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($error !== '' || $http >= 400) {
            return ['error' => 'transcription_failed'];
        }
        $decoded = json_decode((string) $body, true);
        $text = is_array($decoded) && isset($decoded['text']) ? trim((string) $decoded['text']) : '';
        if (mb_strlen($text) < 2) {
            return ['error' => 'audio_unclear'];
        }

        return [
            'text' => $text,
            'language' => is_array($decoded) && isset($decoded['language']) ? (string) $decoded['language'] : null,
        ];
    }

    protected function postJson($url, $key, array $payload)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int) config('assistant.transcription_timeout'),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '.$key,
                'Accept: application/json',
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        $decoded = json_decode((string) $body, true);

        return is_array($decoded) ? $decoded : null;
    }
}

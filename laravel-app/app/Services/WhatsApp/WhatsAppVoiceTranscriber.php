<?php

namespace App\Services\WhatsApp;

use App\Services\Assistant\AssistantAiConfig;
use App\WhatsApp\WhatsAppMessage;

class WhatsAppVoiceTranscriber
{
    /**
     * Put the spoken words on the message so the assistant can answer them.
     */
    public function apply(WhatsAppMessage $message)
    {
        if (strtoupper((string) $message->type) !== 'AUDIO') {
            return trim((string) $message->body);
        }
        $existing = trim((string) $message->body);
        if ($existing !== '') {
            return $existing;
        }
        $text = trim($this->transcribe($message));
        if ($text === '') {
            return '';
        }
        $message->body = $text;
        $message->save();

        return $text;
    }

    public function transcribe(WhatsAppMessage $message)
    {
        if (! AssistantAiConfig::isConfigured()) {
            return '';
        }
        $file = app(WhatsAppMediaLibrary::class)->file($message);
        if (! $file || empty($file['path']) || ! is_file($file['path'])) {
            return '';
        }
        $mime = ! empty($file['mime']) ? (string) $file['mime'] : 'audio/ogg';
        $name = basename($file['path']);
        if (pathinfo($name, PATHINFO_EXTENSION) === '') {
            $name .= '.ogg';
        }
        $upload = curl_file_create($file['path'], $mime, $name);
        $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '.AssistantAiConfig::apiKey(),
            ],
            CURLOPT_POSTFIELDS => [
                'model' => 'whisper-1',
                'file' => $upload,
            ],
        ]);
        $raw = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $http >= 400) {
            return '';
        }
        $decoded = json_decode($raw, true);

        return isset($decoded['text']) ? trim((string) $decoded['text']) : '';
    }
}

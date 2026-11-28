<?php

namespace App\Services\Mai;

use App\Contracts\Ai\AiProviderInterface;
use App\Services\Assistant\AssistantBriefService;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppMessage;
use App\WhatsApp\WhatsAppSetting;

/**
 * Personal MAI auto-replies use the owner's briefing notes and optional app data.
 */
class MaiAiReply
{
    public function answer(WhatsAppConversation $conversation, WhatsAppMessage $message)
    {
        $provider = app(AiProviderInterface::class);
        if (! $provider->isConfigured()) {
            return '';
        }
        $incoming = trim($message->hubText());
        if ($incoming === '') {
            $incoming = trim((string) $message->body);
        }
        if ($incoming === '') {
            return '';
        }
        $brief = '';
        try {
            $brief = trim((string) app(AssistantBriefService::class)->promptText());
        } catch (\Throwable $e) {
            $brief = '';
        }
        $appData = $this->appData($conversation);
        $system = 'You reply on WhatsApp for the account owner. Keep answers short, clear, and useful. '
            .'Use only the briefing notes and linked app data below. If the answer is not there, say you will have someone follow up. '
            .'Do not invent prices, dates, or promises. Default language is English unless the person clearly writes in another language — then reply in that language.';
        if ($brief !== '') {
            $system .= "\n\nBriefing notes:\n".$brief;
        }
        if ($appData !== '') {
            $system .= "\n\nLinked application data:\n".$appData;
        }
        $result = $provider->complete([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $incoming],
        ], [
            'temperature' => 0.3,
            'max_tokens' => 400,
        ]);
        if (empty($result['ok'])) {
            return '';
        }

        return trim((string) (isset($result['content']) ? $result['content'] : ''));
    }

    public function appData(WhatsAppConversation $conversation)
    {
        $sessionId = (int) $conversation->mai_session_id;
        $key = $sessionId > 0 ? (string) $sessionId : 'staff';
        $raw = WhatsAppSetting::getValue('mai_app_link_'.$key, '');
        if ($raw === '') {
            return '';
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return '';
        }
        $url = isset($decoded['url']) ? trim((string) $decoded['url']) : '';
        $cached = isset($decoded['snapshot']) ? trim((string) $decoded['snapshot']) : '';
        if ($cached !== '') {
            return function_exists('mb_substr') ? mb_substr($cached, 0, 6000) : substr($cached, 0, 6000);
        }
        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            return '';
        }

        return $this->pull($url, isset($decoded['token']) ? (string) $decoded['token'] : '', $key);
    }

    protected function pull($url, $token, $sessionId)
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($host === '' || stripos($host, 'localhost') !== false || $host === '127.0.0.1') {
            return '';
        }
        $headers = ['Accept: application/json, text/plain, */*'];
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer '.$token;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => 'WhatsMAI/1.0',
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (! is_string($body) || $code < 200 || $code >= 300) {
            return '';
        }
        $text = trim($body);
        if (strlen($text) > 6000) {
            $text = substr($text, 0, 6000);
        }
        $raw = WhatsAppSetting::getValue('mai_app_link_'.$sessionId, '');
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            $decoded = [];
        }
        $decoded['snapshot'] = $text;
        $decoded['fetched_at'] = date('c');
        WhatsAppSetting::putValue('mai_app_link_'.$sessionId, json_encode($decoded));

        return $text;
    }
}

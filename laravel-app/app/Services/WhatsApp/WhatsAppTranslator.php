<?php

namespace App\Services\WhatsApp;

use App\Contracts\Ai\AiProviderInterface;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppMessage;
use App\WhatsApp\WhatsAppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Human replies stay human. Translator mode only changes the language
 * the agent reads and the language the client receives.
 */
class WhatsAppTranslator
{
    public function isOn()
    {
        return $this->flag() === '1';
    }

    public function setOn($on)
    {
        $value = $on ? '1' : '0';
        $this->writeFlag($value);

        if ($on && ! app(AiProviderInterface::class)->isConfigured()) {
            return 'Translator mode is on, but the AI key is missing, so messages stay in the language they were written. You still reply yourself.';
        }
        if ($on) {
            return 'Translator mode is on. Client messages are shown to you in English. Your English reply is sent in the client’s language. You still reply yourself.';
        }

        return 'Translator mode is off. Messages stay in the language they were written.';
    }

    /**
     * @return string|null on|off
     */
    public function command($text)
    {
        $token = $this->token($text);
        $on = [
            'AI TRANSLATE',
            'AI TRANS',
            'TRANS ON',
            'TRANS MODE ON',
            'TRANSLATOR ON',
            'TRANSLATOR MODE ON',
            'TRANSLATION MODE ON',
        ];
        $off = [
            'TRANS OFF',
            'TRANS MODE OFF',
            'AI TRANS OFF',
            'AI TRANSLATE OFF',
            'TRANSLATOR OFF',
            'TRANSLATOR MODE OFF',
            'TRANSLATION MODE OFF',
        ];
        if (in_array($token, $on, true)) {
            return 'on';
        }
        if (in_array($token, $off, true)) {
            return 'off';
        }

        return null;
    }

    public function decorate(WhatsAppMessage $message)
    {
        if (! $this->isOn() || $message->direction !== WhatsAppMessage::DIR_IN) {
            return $message;
        }
        $original = trim((string) $message->body);
        if ($original === '' || isset($message->translationMeta()['english'])) {
            return $message;
        }
        if (mb_strlen($original) > 4000) {
            $original = mb_substr($original, 0, 4000);
        }

        $parsed = $this->ask(
            'You translate a WhatsApp message for a human agent. Reply with JSON only: {"language":"iso-639-1","language_name":"English name of the language","is_english":true,"english":"the message in natural English"}. If it is already English, set is_english true and copy it into english. Do not answer the customer. Do not add notes. Keep names, phone numbers, amounts, and dates unchanged.',
            $original
        );
        if (! $parsed || ! isset($parsed['english'])) {
            return $message;
        }
        $english = trim((string) $parsed['english']);
        if ($english === '') {
            return $message;
        }
        $code = $this->languageCode(isset($parsed['language']) ? $parsed['language'] : '');
        $name = $this->languageName(isset($parsed['language_name']) ? $parsed['language_name'] : '', $code);
        $isEnglish = ! empty($parsed['is_english']) || $code === 'en' || strcasecmp($english, trim((string) $message->body)) === 0;
        if (! $isEnglish) {
            $this->rememberLanguage($message->conversation_id, $code, $name);
        }
        $this->mergeTranslation($message, [
            'english' => $isEnglish ? trim((string) $message->body) : $english,
            'language' => $isEnglish ? 'en' : $code,
            'language_name' => $isEnglish ? 'English' : $name,
        ]);
        $this->syncPreview($message, $isEnglish ? trim((string) $message->body) : $english);

        return $message;
    }

    /**
     * Translate the incoming messages already on screen. Photo captions count.
     */
    public function backfillVisible($messages)
    {
        if (! $this->isOn() || ! $messages) {
            return;
        }
        $pending = [];
        foreach ($messages as $message) {
            if (! $message instanceof WhatsAppMessage) {
                continue;
            }
            if ($message->direction !== WhatsAppMessage::DIR_IN) {
                continue;
            }
            if (trim((string) $message->body) === '' || ! empty($message->translationMeta()['english'])) {
                continue;
            }
            $pending[] = $message;
        }
        $pending = array_slice($pending, -12);
        foreach ($pending as $message) {
            $this->decorate($message);
        }
    }

    protected function learnLanguage(WhatsAppConversation $conversation)
    {
        $rows = WhatsAppMessage::where('conversation_id', $conversation->id)
            ->where('direction', WhatsAppMessage::DIR_IN)
            ->whereNotNull('body')
            ->orderByDesc('id')
            ->limit(8)
            ->get();
        foreach ($rows as $message) {
            $this->decorate($message);
            if ($this->languageFor($conversation->id)) {
                return;
            }
        }
    }

    /**
     * English the agent typed, translated into the client's language.
     *
     * @return array{text:string,staff:string,language:string,language_name:string}|null
     */
    public function forClient(WhatsAppConversation $conversation, $english)
    {
        $english = trim((string) $english);
        if ($english === '' || ! $this->isOn()) {
            return null;
        }
        $known = $this->languageFor($conversation->id);
        if (! $known) {
            $this->learnLanguage($conversation);
            $known = $this->languageFor($conversation->id);
        }
        if (! $known || $known['code'] === 'en') {
            return null;
        }
        $parsed = $this->ask(
            'Translate the agent reply into '.$known['name'].'. Reply with JSON only: {"text":"the translation"}. Keep the tone, names, phone numbers, amounts, and dates. Do not add a greeting or a note. If the reply is already in '.$known['name'].', return it unchanged.',
            $english
        );
        if (! $parsed || ! isset($parsed['text'])) {
            return null;
        }
        $text = trim((string) $parsed['text']);
        if ($text === '') {
            return null;
        }

        return [
            'text' => $text,
            'staff' => $english,
            'language' => $known['code'],
            'language_name' => $known['name'],
        ];
    }

    public function languages()
    {
        return [
            'rw' => 'Kinyarwanda',
            'fr' => 'French',
            'sw' => 'Kiswahili',
            'ln' => 'Lingala',
            'ar' => 'Arabic',
            'pt' => 'Portuguese',
            'es' => 'Spanish',
            'ha' => 'Hausa',
            'yo' => 'Yoruba',
            'am' => 'Amharic',
            'so' => 'Somali',
            'zh' => 'Chinese',
            'de' => 'German',
            'tr' => 'Turkish',
        ];
    }

    /**
     * Language this chat's English reply is sent in. Empty clears it.
     *
     * @return string|null the language name, or null when detection is used
     */
    public function setDestination($conversationId, $code)
    {
        $code = strtolower(trim((string) $code));
        if ($code === '' || $code === 'auto') {
            $this->writeLanguage($conversationId, '');

            return null;
        }
        $languages = $this->languages();
        if (! isset($languages[$code])) {
            return null;
        }
        $this->writeLanguage($conversationId, json_encode([
            'code' => $code,
            'name' => $languages[$code],
            'locked' => true,
        ]));
        if (! $this->isOn()) {
            $this->setOn(true);
        }

        return $languages[$code];
    }

    public function destinationFor($conversationId)
    {
        return $this->languageFor($conversationId);
    }

    public function token($text)
    {
        $token = strtoupper(trim(preg_replace('/\s+/u', ' ', (string) $text)));

        return trim($token, " \t*_~.`'!?,;:");
    }

    protected function ask($instruction, $text)
    {
        try {
            $provider = app(AiProviderInterface::class);
            if (! $provider->isConfigured()) {
                return null;
            }
            $result = $provider->complete([
                ['role' => 'system', 'content' => $instruction],
                ['role' => 'user', 'content' => $text],
            ], [
                'temperature' => 0,
                'max_tokens' => 900,
                'json' => true,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[whatsapp-translator] request failed: '.$e->getMessage());

            return null;
        }
        if (empty($result['ok'])) {
            Log::warning('[whatsapp-translator] '.((isset($result['error']) ? $result['error'] : 'no translation')));

            return null;
        }
        if (! empty($result['json']) && is_array($result['json'])) {
            return $result['json'];
        }
        $decoded = json_decode((string) ($result['content'] ?? ''), true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function mergeTranslation(WhatsAppMessage $message, array $translation)
    {
        $media = $message->media();
        $media['translation'] = $translation;
        $message->media_json = json_encode($media);
        $message->save();
    }

    protected function syncPreview(WhatsAppMessage $message, $shown)
    {
        $shown = trim((string) $shown);
        if ($shown === '') {
            return;
        }
        $conversation = WhatsAppConversation::find($message->conversation_id);
        if (! $conversation) {
            return;
        }
        $latestId = WhatsAppMessage::where('conversation_id', $conversation->id)->orderByDesc('id')->value('id');
        if ((int) $latestId !== (int) $message->id) {
            return;
        }
        $preview = mb_substr($shown, 0, 180);
        $conversation->last_message = $preview;
        if (! $conversation->first_message) {
            $conversation->first_message = $preview;
        }
        $conversation->save();
    }

    protected function rememberLanguage($conversationId, $code, $name)
    {
        if (! $conversationId || $code === '' || $code === 'en' || $code === 'und') {
            return;
        }
        $existing = $this->languageFor($conversationId);
        if ($existing && ! empty($existing['locked'])) {
            return;
        }
        $this->writeLanguage($conversationId, json_encode(['code' => $code, 'name' => $name !== '' ? $name : $code]));
    }

    protected function writeLanguage($conversationId, $payload)
    {
        $key = 'translator_lang_'.(int) $conversationId;
        try {
            if (Schema::hasTable('whatsapp_settings')) {
                WhatsAppSetting::putValue($key, $payload);

                return;
            }
        } catch (\Throwable $e) {
        }
        if ($payload === '') {
            Cache::forget('whatsapp.'.$key);

            return;
        }
        Cache::forever('whatsapp.'.$key, $payload);
    }

    protected function languageFor($conversationId)
    {
        $key = 'translator_lang_'.(int) $conversationId;
        $raw = null;
        try {
            if (Schema::hasTable('whatsapp_settings')) {
                $raw = WhatsAppSetting::getValue($key, null);
            }
        } catch (\Throwable $e) {
            $raw = null;
        }
        if ($raw === null || $raw === '') {
            $raw = Cache::get('whatsapp.'.$key);
        }
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (! is_array($decoded) || empty($decoded['code'])) {
            return null;
        }
        $code = $this->languageCode($decoded['code']);
        if ($code === '') {
            return null;
        }

        return [
            'code' => $code,
            'name' => $this->languageName(isset($decoded['name']) ? $decoded['name'] : '', $code),
            'locked' => ! empty($decoded['locked']),
        ];
    }

    protected function languageCode($value)
    {
        $code = strtolower(trim((string) $value));
        $code = preg_replace('/[^a-z-]/', '', $code);
        if (strlen($code) < 2 || strlen($code) > 12) {
            return '';
        }

        return $code;
    }

    protected function languageName($value, $code)
    {
        $name = trim((string) $value);
        $name = preg_replace('/[^A-Za-z \'-]/', '', $name);
        $name = trim($name);
        if ($name !== '') {
            return mb_substr($name, 0, 40);
        }

        return $code !== '' ? strtoupper($code) : 'their language';
    }

    protected function flag()
    {
        try {
            if (Schema::hasTable('whatsapp_settings')) {
                return (string) WhatsAppSetting::getValue('translator_mode', '0');
            }
        } catch (\Throwable $e) {
        }

        return (string) Cache::get('whatsapp.translator_mode', '0');
    }

    protected function writeFlag($value)
    {
        try {
            if (Schema::hasTable('whatsapp_settings')) {
                WhatsAppSetting::putValue('translator_mode', $value);

                return;
            }
        } catch (\Throwable $e) {
        }
        Cache::forever('whatsapp.translator_mode', $value);
    }
}

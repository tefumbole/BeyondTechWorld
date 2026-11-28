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

    public function decorate(WhatsAppMessage $message, $force = false)
    {
        if ($message->direction !== WhatsAppMessage::DIR_IN || ! $this->activeFor($message->conversation_id)) {
            return $message;
        }
        if (! $this->needsTranslation($message)) {
            return $message;
        }
        $original = trim((string) $message->body);
        if (! $force && \Illuminate\Support\Facades\Cache::has('wa.tr.fail.'.$message->id)) {
            return $message;
        }
        if (mb_strlen($original) > 4000) {
            $original = mb_substr($original, 0, 4000);
        }

        $staff = $this->staffLanguage();
        $parsed = $this->ask(
            'Translate this WhatsApp message into clear, simple '.$staff['name'].'. Be straight to the point and meaningful. Fix obvious typing errors so the message makes sense, without changing the meaning. Keep the tone. Do not add facts, prices, or promises. Do not answer them. Reply with JSON only: {"language":"iso-639-1","language_name":"English name of the source language","is_english":true,"english":"the clear '.$staff['name'].'"}. If it is already clear '.$staff['name'].', set is_english true and return a cleaned copy. If it is not, english must be real '.$staff['name'].', never a copy of the original. Keep names, phone numbers, amounts, and dates unchanged.',
            $original
        );
        if (! $parsed || ! isset($parsed['english'])) {
            \Illuminate\Support\Facades\Cache::put('wa.tr.fail.'.$message->id, 1, now()->addSeconds(25));

            return $message;
        }
        $english = trim((string) $parsed['english']);
        if ($english === '') {
            return $message;
        }
        $code = $this->languageCode(isset($parsed['language']) ? $parsed['language'] : '');
        $name = $this->languageName(isset($parsed['language_name']) ? $parsed['language_name'] : '', $code);
        $same = strcasecmp($english, $original) === 0;
        $staffCode = $staff['code'];
        $alreadyMine = ! empty($parsed['is_english']) || $code === $staffCode || $code === 'en';
        if ($alreadyMine) {
            $this->mergeTranslation($message, [
                'english' => $original,
                'language' => $staffCode,
                'language_name' => $staff['name'],
                'style' => 'basic',
                'verified' => true,
            ]);
            $this->syncPreview($message, $original);

            return $message;
        }
        if ($same && $code !== '' && $code !== $staffCode) {
            $fromName = $name !== '' ? $name : 'its original language';
            $again = $this->ask(
                'The WhatsApp message below is in '.$fromName.', not '.$staff['name'].'. Translate it into clear, simple '.$staff['name'].'. Be straight to the point. Fix obvious typing errors so it makes sense, without changing the meaning. Keep the tone. Do not add facts and do not copy the original. If it is already '.$staff['name'].', return a cleaned copy. Reply with JSON only: {"english":"the translation"}. Keep names, phone numbers, amounts, and dates unchanged.',
                $original
            );
            $english = $again && isset($again['english']) ? trim((string) $again['english']) : '';
            if ($english === '' || strcasecmp($english, $original) === 0) {
                \Illuminate\Support\Facades\Cache::put('wa.tr.fail.'.$message->id, 1, now()->addSeconds(25));

                return $message;
            }
            $code = $code !== '' ? $code : 'und';
            $same = false;
        }
        $isMine = $code === $staffCode || $code === 'en' || ($same && ! empty($parsed['is_english']));
        if (! $isMine) {
            $this->rememberLanguage($message->conversation_id, $code, $name);
        }
        $this->mergeTranslation($message, [
            'english' => $isMine ? $original : $english,
            'language' => $isMine ? $staffCode : $code,
            'language_name' => $isMine ? $staff['name'] : $name,
            'style' => 'basic',
            'verified' => true,
        ]);
        $this->syncPreview($message, $isMine ? $original : $english);

        return $message;
    }

    /**
     * Incoming text still shown in the original language.
     * A stored copy of the original is not a translation.
     */
    public function needsTranslation(WhatsAppMessage $message)
    {
        if ($message->direction !== WhatsAppMessage::DIR_IN) {
            return false;
        }
        $body = trim((string) $message->body);
        if ($body === '') {
            return false;
        }
        $meta = $message->translationMeta();
        if (empty($meta['english']) || (isset($meta['style']) ? $meta['style'] : '') !== 'basic') {
            return true;
        }
        $stored = isset($meta['language']) ? (string) $meta['language'] : '';
        $copied = strcasecmp(trim((string) $meta['english']), $body) === 0;
        $staffCode = $this->staffLanguage()['code'];
        if ($stored === $staffCode || $stored === 'en') {
            return false;
        }
        if ($copied && $stored !== '') {
            return true;
        }
        if (! empty($meta['verified'])) {
            return false;
        }

        return $copied;
    }

    /**
     * Translate incoming messages already loaded for this chat, newest first.
     * Photo captions count. $force retries a message that failed a moment ago.
     */
    public function backfillVisible($messages, $limit = 30, $force = false)
    {
        $done = [];
        if (! $messages) {
            return $done;
        }
        $conversationId = null;
        foreach ($messages as $message) {
            if ($message instanceof WhatsAppMessage) {
                $conversationId = $message->conversation_id;
                break;
            }
        }
        if (! $conversationId || ! $this->activeFor($conversationId)) {
            return $done;
        }
        $pending = [];
        foreach ($messages as $message) {
            if ($message instanceof WhatsAppMessage && $this->needsTranslation($message)) {
                $pending[] = $message;
            }
        }
        $limit = (int) $limit;
        if ($limit > 0 && count($pending) > $limit) {
            $pending = array_slice($pending, -$limit);
        }
        foreach ($pending as $message) {
            $this->decorate($message, $force);
            if (! $this->needsTranslation($message)) {
                $done[] = $message;
            }
        }

        return $done;
    }

    /**
     * Older messages in one chat, newest first, for when the chat is opened
     * or translation is switched on.
     */
    public function backfillChat($conversationId, $limit = 40, $force = false)
    {
        $conversationId = (int) $conversationId;
        if ($conversationId < 1 || ! $this->activeFor($conversationId)) {
            return [];
        }
        $rows = WhatsAppMessage::where('conversation_id', $conversationId)
            ->where('direction', WhatsAppMessage::DIR_IN)
            ->whereNotNull('body')
            ->where('body', '!=', '')
            ->orderByDesc('id')
            ->limit(400)
            ->get()
            ->reverse()
            ->values();

        return $this->backfillVisible($rows, $limit, $force);
    }

    public function activeFor($conversationId)
    {
        $known = $this->languageFor($conversationId);

        return $known && ! empty($known['enabled']);
    }

    /**
     * Turn translation on or off for one chat. Other chats stay as they are.
     */
    public function setChat($conversationId, $on)
    {
        $existing = $this->languageFor($conversationId);
        $code = $existing && ! empty($existing['code']) ? $existing['code'] : '';
        $name = $existing && ! empty($existing['name']) ? $existing['name'] : '';
        $locked = $existing && ! empty($existing['locked']);
        $this->writeLanguage($conversationId, json_encode([
            'enabled' => (bool) $on,
            'code' => $code,
            'name' => $name,
            'locked' => $locked || $code !== '',
            'off' => ! $on,
        ]));
        if (! $on) {
            $kept = $name !== '' ? ' '.$name.' stays saved for this person.' : '';

            return 'Translation is off for this chat.'.$kept.' Other chats are unchanged.';
        }
        if ($code !== '' && $code !== $this->staffLanguage()['code']) {
            return 'Translation is on for this chat. Replies still go out in '.$name.'.';
        }
        if (! app(AiProviderInterface::class)->isConfigured()) {
            return 'Translation is on for this chat, but the AI key is missing, so messages stay as they were written.';
        }

        return 'Translation is on for this chat. Messages are shown in English, and the reply language is detected from this person.';
    }

    protected function alreadyInLanguage($text, $destinationCode, $staffCode)
    {
        if ($destinationCode === '' || $destinationCode === $staffCode) {
            return true;
        }
        if ($staffCode === 'en' && $destinationCode !== 'en') {
            return ! $this->looksLikeEnglish($text);
        }

        return false;
    }

    protected function looksLikeEnglish($text)
    {
        $words = preg_split('/\s+/u', mb_strtolower((string) $text));
        if (! is_array($words)) {
            return false;
        }
        $markers = ['the', 'and', 'you', 'your', 'is', 'are', 'to', 'of', 'a', 'i', 'it', 'this', 'that', 'for', 'with', 'not', 'but', 'what', 'how', 'can', 'will', 'was', 'have', 'do', 'we', 'they', 'my', 'me', 'in', 'on', 'at', 'if', 'or', 'so', 'just', 'please', 'hello', 'hi', 'yes', 'no', 'ok', 'okay', 'sorry', 'mean'];
        $hits = 0;
        $count = 0;
        foreach ($words as $word) {
            $word = trim($word, ".,!?;:'\"“”");
            if ($word === '') {
                continue;
            }
            $count++;
            if (in_array($word, $markers, true) || $word === "don't" || $word === "i'm" || $word === "it's") {
                $hits++;
            }
        }

        return $hits >= 2 || ($hits >= 1 && $count <= 4);
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
    public function utterance($text, $languageName)
    {
        $text = trim((string) $text);
        $languageName = trim((string) $languageName);
        if ($text === '' || $languageName === '') {
            return '';
        }
        $parsed = $this->ask(
            'Translate the spoken line into simple, direct '.$languageName.'. Correct slips so it makes sense. Do not add facts. Reply with JSON only: {"text":"the translation"}. Keep names, phone numbers, amounts, and dates.',
            $text
        );
        if (! $parsed || empty($parsed['text'])) {
            return '';
        }

        return trim((string) $parsed['text']);
    }

    public function forClient(WhatsAppConversation $conversation, $english)
    {
        $english = trim((string) $english);
        if ($english === '') {
            return null;
        }
        $known = $this->outgoingLanguage($conversation->id);
        $staff = $this->staffLanguage();
        if (! $known || $known['code'] === '' || $known['code'] === $staff['code']) {
            return null;
        }
        if ($this->alreadyInLanguage($english, $known['code'], $staff['code'])) {
            return null;
        }
        $parsed = $this->ask(
            'The message below was written in '.$staff['name'].'. Translate it into clear, simple '.$known['name'].'. Be straight to the point and meaningful. Fix obvious typing errors so it makes sense, without changing the meaning. Keep the tone. Do not add a greeting, a fact, or a note that was not written. Reply with JSON only: {"text":"the translation"}. Keep names, phone numbers, amounts, and dates. If it is already clear '.$known['name'].', return a cleaned copy.',
            $english
        );
        if (! $parsed || ! isset($parsed['text'])) {
            return ['failed' => true, 'language_name' => $known['name']];
        }
        $text = trim((string) $parsed['text']);
        if ($text === '') {
            return ['failed' => true, 'language_name' => $known['name']];
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
        $languages = [
            'en' => 'English',
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
            'it' => 'Italian',
            'nl' => 'Dutch',
            'ru' => 'Russian',
            'hi' => 'Hindi',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'pl' => 'Polish',
            'el' => 'Greek',
            'he' => 'Hebrew',
            'uk' => 'Ukrainian',
            'vi' => 'Vietnamese',
            'id' => 'Indonesian',
            'th' => 'Thai',
            'bn' => 'Bengali',
            'ur' => 'Urdu',
            'pa' => 'Punjabi',
            'ps' => 'Pashto',
            'sd' => 'Sindhi',
            'zu' => 'Zulu',
            'af' => 'Afrikaans',
            'ti' => 'Tigrinya',
        ];
        asort($languages);

        return $languages;
    }

    public function staffLanguage()
    {
        $languages = $this->languages();
        $code = $this->setting('translator_staff_lang', 'en');
        if (! isset($languages[$code])) {
            $code = 'en';
        }

        return ['code' => $code, 'name' => $languages[$code]];
    }

    public function setStaffLanguage($code)
    {
        $languages = $this->languages();
        $code = strtolower(trim((string) $code));
        if (! isset($languages[$code])) {
            $code = 'en';
        }
        $this->putSetting('translator_staff_lang', $code);

        return $languages[$code];
    }

    public function defaultReply()
    {
        $languages = $this->languages();
        $code = $this->setting('translator_default_reply', '');
        if ($code === '' || ! isset($languages[$code])) {
            return null;
        }

        return ['code' => $code, 'name' => $languages[$code]];
    }

    public function enterSends()
    {
        return $this->setting('hub_enter_sends', '1') !== '0';
    }

    public function setEnterSends($on)
    {
        $this->putSetting('hub_enter_sends', $on ? '1' : '0');
    }

    /**
     * Turn a voice note into words. Incoming notes are kept in the staff language.
     * A note you sent keeps the words you spoke.
     */
    public function hearVoiceNote(WhatsAppMessage $message)
    {
        if (strtoupper((string) $message->type) !== 'AUDIO') {
            return $message;
        }
        if ($message->direction === WhatsAppMessage::DIR_OUT) {
            return $this->hearOutgoingVoice($message);
        }

        return $this->hearIncomingVoice($message);
    }

    /**
     * Words already stored for this voice note, or an empty string when it is still only audio.
     */
    public function voiceTranscript(WhatsAppMessage $message)
    {
        $text = trim($message->hubText());
        if ($this->isVoicePlaceholder($text)) {
            return '';
        }

        return $text;
    }

    /**
     * Hear an incoming voice note and keep the English (or staff language) wording.
     * The spoken words stay on the message. This does not run from the chat poll.
     */
    public function hearIncomingVoice(WhatsAppMessage $message)
    {
        if (strtoupper((string) $message->type) !== 'AUDIO' || $message->direction !== WhatsAppMessage::DIR_IN) {
            return $message;
        }
        $meta = $message->translationMeta();
        if (! empty($meta['english'])) {
            return $message;
        }
        $body = trim((string) $message->body);
        if ($this->isVoicePlaceholder($body)) {
            $message->body = '';
            $message->save();
            app(WhatsAppVoiceTranscriber::class)->apply($message);
            $message = $message->fresh() ?: $message;
            $body = trim((string) $message->body);
        }
        if ($body === '') {
            return $message;
        }
        $heard = $this->toStaffText($body);
        if (! $heard) {
            return $message;
        }
        $this->mergeTranslation($message, [
            'english' => $heard['english'],
            'language' => $heard['language'],
            'language_name' => $heard['language_name'],
            'style' => 'basic',
            'verified' => true,
            'voice' => true,
        ]);
        $this->syncPreview($message, $heard['english']);

        return $message->fresh() ?: $message;
    }

    /**
     * A voice note sent from this chat or the phone. Keep the spoken words on the bubble.
     */
    public function hearOutgoingVoice(WhatsAppMessage $message)
    {
        if (strtoupper((string) $message->type) !== 'AUDIO' || $message->direction !== WhatsAppMessage::DIR_OUT) {
            return $message;
        }
        $meta = $message->translationMeta();
        $staff = trim((string) (isset($meta['staff']) ? $meta['staff'] : ''));
        $body = trim((string) $message->body);
        if (! $this->isVoicePlaceholder($staff) || (! $this->isVoicePlaceholder($body) && $body !== '')) {
            return $message;
        }
        $text = trim(app(WhatsAppVoiceTranscriber::class)->transcribe($message));
        if ($text === '' || $this->isVoicePlaceholder($text)) {
            return $message;
        }
        $heard = $this->toStaffText($text);
        if ($heard && trim((string) $heard['english']) !== '') {
            $text = trim((string) $heard['english']);
        }
        $message->body = $text;
        $media = $message->media();
        $translation = isset($media['translation']) && is_array($media['translation']) ? $media['translation'] : [];
        $translation['staff'] = $text;
        $translation['voice'] = true;
        $media['translation'] = $translation;
        $message->media_json = json_encode($media);
        $message->save();
        $this->syncPreview($message, $text);

        return $message->fresh() ?: $message;
    }

    /**
     * Hear a voice note and store a clear version of what was meant.
     */
    public function clarifyVoice(WhatsAppMessage $message)
    {
        if (strtoupper((string) $message->type) !== 'AUDIO') {
            return $message;
        }
        $raw = trim((string) $message->body);
        if ($this->isVoicePlaceholder($raw)) {
            $raw = trim(app(WhatsAppVoiceTranscriber::class)->transcribe($message));
        }
        if ($raw === '' || $this->isVoicePlaceholder($raw)) {
            return $message;
        }
        $heard = $this->toStaffText($raw);
        $clear = $heard && trim((string) $heard['english']) !== '' ? trim((string) $heard['english']) : '';
        if ($clear === '') {
            return $message;
        }
        if ($message->direction === WhatsAppMessage::DIR_IN) {
            $this->mergeTranslation($message, [
                'english' => $clear,
                'language' => $heard['language'],
                'language_name' => $heard['language_name'],
                'style' => 'basic',
                'verified' => true,
                'voice' => true,
            ]);
        } else {
            $message->body = $clear;
            $media = $message->media();
            $translation = isset($media['translation']) && is_array($media['translation']) ? $media['translation'] : [];
            $translation['staff'] = $clear;
            $translation['voice'] = true;
            $media['translation'] = $translation;
            $message->media_json = json_encode($media);
            $message->save();
        }
        $this->syncPreview($message, $clear);

        return $message->fresh() ?: $message;
    }

    public function isVoicePlaceholder($text)
    {
        $text = strtoupper(trim((string) $text));

        return $text === '' || in_array($text, ['[AUDIO]', 'VOICE MESSAGE', 'AUDIO', 'VOICE NOTE', 'VOICE MAIL', 'VOICEMAIL'], true);
    }

    /**
     * Rewrite spoken or typed words into the staff language.
     *
     * @return array{english:string,language:string,language_name:string}|null
     */
    public function toStaffText($text)
    {
        $text = trim((string) $text);
        if ($text === '') {
            return null;
        }
        if (mb_strlen($text) > 4000) {
            $text = mb_substr($text, 0, 4000);
        }
        $staff = $this->staffLanguage();
        $parsed = $this->ask(
            'Rewrite this voice-note transcript as clear '.$staff['name'].'. Do not copy the speech word for word. Drop fillers, repeats, and false starts, and write complete sentences that say what the person meant. Keep the same facts, names, phone numbers, amounts, and dates. Do not add anything they did not say. Reply with JSON only: {"language":"iso-639-1","language_name":"English name of the source language","is_english":true,"english":"the clear '.$staff['name'].'"}.',
            $text
        );
        if (! $parsed || ! isset($parsed['english'])) {
            return null;
        }
        $english = trim((string) $parsed['english']);
        if ($english === '') {
            return null;
        }
        $code = $this->languageCode(isset($parsed['language']) ? $parsed['language'] : '');
        $name = $this->languageName(isset($parsed['language_name']) ? $parsed['language_name'] : '', $code);
        $staffCode = $staff['code'];
        $already = ! empty($parsed['is_english']) || $code === $staffCode || $code === 'en';

        return [
            'english' => $english,
            'language' => $already ? $staffCode : ($code !== '' ? $code : 'und'),
            'language_name' => $already ? $staff['name'] : ($name !== '' ? $name : 'original'),
        ];
    }

    public function setDefaultReply($code)
    {
        $languages = $this->languages();
        $code = strtolower(trim((string) $code));
        if ($code === '' || $code === 'off' || ! isset($languages[$code])) {
            $this->putSetting('translator_default_reply', '');

            return null;
        }
        $this->putSetting('translator_default_reply', $code);

        return $languages[$code];
    }

    /**
     * Language an outgoing reply is sent in. A choice on this chat wins.
     * A chat set to Off does not fall back to the default.
     *
     * @return array{code:string,name:string,locked:bool,enabled:bool}|null
     */
    public function outgoingLanguage($conversationId)
    {
        $staff = $this->staffLanguage()['code'];
        $known = $this->languageFor($conversationId);
        if ($known && ! empty($known['off'])) {
            return null;
        }
        if ($known && empty($known['enabled'])) {
            return null;
        }
        if ($known && ! empty($known['locked']) && $known['code'] !== '' && $known['code'] !== $staff) {
            return $known;
        }
        if ($known && ! empty($known['enabled']) && $known['code'] === '') {
            $default = $this->defaultReply();
            if ($default && $default['code'] !== $staff) {
                return [
                    'code' => $default['code'],
                    'name' => $default['name'],
                    'locked' => true,
                    'enabled' => true,
                ];
            }

            return $known;
        }
        if ($known) {
            return null;
        }

        return null;
    }

    /**
     * Language this chat's English reply is sent in. Empty clears it.
     *
     * @return string|null the language name, or null when detection is used
     */
    public function setDestination($conversationId, $code)
    {
        $code = strtolower(trim((string) $code));
        if ($code === 'off') {
            $existing = $this->languageFor($conversationId);
            $keptCode = $existing && ! empty($existing['code']) ? $existing['code'] : '';
            $keptName = $existing && ! empty($existing['name']) ? $existing['name'] : '';
            $this->writeLanguage($conversationId, json_encode([
                'enabled' => false,
                'code' => $keptCode,
                'name' => $keptName,
                'locked' => $keptCode !== '',
                'off' => true,
            ]));

            return false;
        }
        if ($code === '' || $code === 'auto') {
            $this->writeLanguage($conversationId, json_encode([
                'enabled' => true,
                'code' => '',
                'name' => '',
                'locked' => false,
            ]));

            return '';
        }
        $languages = $this->languages();
        if (! isset($languages[$code])) {
            return null;
        }
        $this->writeLanguage($conversationId, json_encode([
            'code' => $code,
            'name' => $languages[$code],
            'locked' => true,
            'enabled' => true,
        ]));

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
        if (! $existing || empty($existing['enabled']) || ! empty($existing['off']) || ! empty($existing['locked'])) {
            return;
        }
        $this->writeLanguage($conversationId, json_encode([
            'code' => $code,
            'name' => $name !== '' ? $name : $code,
            'locked' => true,
            'enabled' => true,
            'off' => false,
        ]));
    }

    public function backfillOthers($exceptId)
    {
        $ids = $this->enabledConversationIds();
        if (! $ids) {
            return [];
        }
        $exceptId = (int) $exceptId;
        $offset = (int) Cache::get('wa.translator.scan', 0);
        if ($offset >= count($ids)) {
            $offset = 0;
        }
        $slice = array_slice($ids, $offset, 4);
        Cache::put('wa.translator.scan', $offset + 4, now()->addMinutes(2));
        foreach ($slice as $id) {
            if ((int) $id === $exceptId || ! $this->activeFor($id)) {
                continue;
            }
            $rows = WhatsAppMessage::where('conversation_id', $id)
                ->where('direction', WhatsAppMessage::DIR_IN)
                ->whereNotNull('body')
                ->where('body', '!=', '')
                ->orderByDesc('id')
                ->limit(6)
                ->get()
                ->reverse()
                ->values();
            $done = $this->backfillVisible($rows, 2, false);
            if ($done) {
                return $done;
            }
        }

        return [];
    }

    public function enabledConversationIds()
    {
        $cached = Cache::get('wa.translator.enabled_ids');
        if (is_array($cached)) {
            return $cached;
        }
        $ids = [];
        try {
            if (Schema::hasTable('whatsapp_settings')) {
                $rows = WhatsAppSetting::where('key', 'like', 'translator_lang_%')->get(['key', 'value']);
                foreach ($rows as $row) {
                    if (! preg_match('/^translator_lang_(\d+)$/', (string) $row->key, $match)) {
                        continue;
                    }
                    $decoded = json_decode((string) $row->value, true);
                    if (! is_array($decoded)) {
                        continue;
                    }
                    $code = isset($decoded['code']) ? trim((string) $decoded['code']) : '';
                    $enabled = array_key_exists('enabled', $decoded) ? ! empty($decoded['enabled']) : ($code !== '');
                    if ($enabled) {
                        $ids[] = (int) $match[1];
                    }
                }
            }
        } catch (\Throwable $e) {
            $ids = [];
        }
        Cache::put('wa.translator.enabled_ids', $ids, now()->addMinute());

        return $ids;
    }

    protected function writeLanguage($conversationId, $payload)
    {
        unset($this->languageCache[(int) $conversationId]);
        Cache::forget('wa.translator.enabled_ids');
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

    protected $languageCache = [];

    protected function languageFor($conversationId)
    {
        $id = (int) $conversationId;
        if (array_key_exists($id, $this->languageCache)) {
            return $this->languageCache[$id];
        }
        $key = 'translator_lang_'.$id;
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
        if (! is_array($decoded)) {
            return $this->languageCache[$id] = null;
        }
        $code = $this->languageCode(isset($decoded['code']) ? $decoded['code'] : '');
        $enabled = array_key_exists('enabled', $decoded)
            ? ! empty($decoded['enabled'])
            : ($code !== '');
        if (! $enabled && $code === '' && empty($decoded['off'])) {
            return $this->languageCache[$id] = null;
        }

        return $this->languageCache[$id] = [
            'code' => $code,
            'name' => $code === '' ? '' : $this->languageName(isset($decoded['name']) ? $decoded['name'] : '', $code),
            'locked' => ! empty($decoded['locked']),
            'enabled' => $enabled,
            'off' => ! empty($decoded['off']),
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

    protected function setting($key, $default)
    {
        try {
            if (Schema::hasTable('whatsapp_settings')) {
                $value = WhatsAppSetting::getValue($key, $default);

                return is_string($value) ? $value : (string) $default;
            }
        } catch (\Throwable $e) {
        }
        $cached = Cache::get('whatsapp.'.$key, $default);

        return is_string($cached) ? $cached : (string) $default;
    }

    protected function putSetting($key, $value)
    {
        try {
            if (Schema::hasTable('whatsapp_settings')) {
                WhatsAppSetting::putValue($key, $value);

                return;
            }
        } catch (\Throwable $e) {
        }
        Cache::forever('whatsapp.'.$key, $value);
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

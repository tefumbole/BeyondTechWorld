<?php

namespace App\Services\Assistant;

use App\Services\Assistant\Providers\OpenAiProvider;

/**
 * The latest customer message chooses the reply language.
 * A short reply such as "1" or "ok" keeps the language already in use.
 */
class AssistantLanguage
{
    public function follow($text, $previous = 'English', $previousSample = '')
    {
        $detected = $this->detect($text);
        if ($detected) {
            return $detected;
        }
        $previous = trim((string) $previous);

        return [
            'language' => $previous !== '' ? $previous : 'English',
            'sample' => (string) $previousSample,
        ];
    }

    /**
     * @return array{language:string,sample:string}|null
     */
    public function detect($text)
    {
        $raw = trim((string) $text);
        if ($raw === '' || $this->isTooSmall($raw)) {
            return null;
        }
        if (preg_match('/\p{Arabic}/u', $raw)) {
            return $this->named('Arabic', $raw);
        }
        if (preg_match('/\p{Han}|\p{Hiragana}|\p{Katakana}/u', $raw)) {
            return $this->named('match', $raw);
        }
        if (preg_match('/\p{Cyrillic}/u', $raw)) {
            return $this->named('match', $raw);
        }

        $fold = mb_strtolower($raw);
        $french = $this->hits($fold, [
            'bonjour', 'bonsoir', 'salut', 'merci', 'svp', 'oui', 'comment', 'pourquoi',
            'combien', 'besoin', 'mariage', 'voudrais', 'veux', 'voulez', 'pouvez',
            'aimerais', "aujourd'hui", 'aujourdhui', 'demain', 'sonorisation', 'éclairage',
            'eclairage', 'écran', 'ecran', 'lumière', 'lumiere', 'scène', 'scene',
            "s'il", 'sil vous', 'je suis', 'je veux', "j'ai", 'jai ', "qu'est",
        ]);
        $spanish = $this->hits($fold, [
            'hola', 'gracias', 'por favor', 'necesito', 'quiero', 'buenos', 'buenas',
            'cuánto', 'cuanto', 'también', 'tambien',
        ]);
        $english = $this->hits($fold, [
            'hello', 'hi', 'hey', 'thanks', 'thank', 'please', 'what', 'how', 'can',
            'could', 'would', 'need', 'want', 'with', 'this', 'that', 'wedding',
            'price', 'the ', ' and ', ' for ', ' you',
        ]);
        if (preg_match('/[éèêëàâùûôîïç]/u', $raw)) {
            $french += 2;
        }
        if (preg_match('/[ñ¿¡]/u', $raw)) {
            $spanish += 2;
        }
        if ($french > $english && $french >= $spanish && ($french >= 2 || $english === 0)) {
            return $this->named('French', $raw);
        }
        if ($spanish > $english && $spanish > $french && ($spanish >= 2 || $english === 0)) {
            return $this->named('Spanish', $raw);
        }
        if ($english > $french && $english > $spanish && ($english >= 2 || ($french === 0 && $spanish === 0))) {
            return $this->named('English', $raw);
        }

        return null;
    }

    public function rule($language, $sample = '')
    {
        $language = trim((string) $language);
        if ($language === '' || strcasecmp($language, 'English') === 0) {
            return 'Their latest message is in English. Reply entirely in English.';
        }
        if ($language === 'match') {
            $sample = trim((string) $sample);

            return 'Their latest message is not English. Reply entirely in the same language as this message: "'.$sample.'". Do not answer in English. Earlier English messages do not change this.';
        }

        return 'Their latest message is in '.$language.'. Reply entirely in '.$language.'. Do not answer in English. Earlier English messages do not change this.';
    }

    public function apply($reply, $language, $sample = '')
    {
        $reply = trim((string) $reply);
        $language = trim((string) $language);
        if ($reply === '' || $language === '' || strcasecmp($language, 'English') === 0) {
            return $reply;
        }
        $seen = $this->detect($reply);
        if ($seen && $language !== 'match' && $seen['language'] === $language) {
            return $reply;
        }
        if ($language === 'match' && $seen && $seen['language'] !== 'English') {
            return $reply;
        }
        $into = $language === 'match'
            ? 'the same language as this customer message: "'.trim((string) $sample).'"'
            : $language;
        $result = app(OpenAiProvider::class)->complete([
            ['role' => 'system', 'content' => 'Translate the WhatsApp reply into '.$into.'. Keep a number at the start of a line, such as "1. ". Keep prices, dates, phone numbers, and people\'s names unchanged. Return only the translated message.'],
            ['role' => 'user', 'content' => $reply],
        ], [
            'temperature' => 0.2,
            'max_tokens' => 700,
        ]);
        if (empty($result['ok']) || ! is_string($result['content'])) {
            return $reply;
        }
        $translated = trim($result['content']);
        if ($translated === '' || $translated === $reply) {
            return $reply;
        }

        return trim($translated, "\" \n");
    }

    protected function named($language, $raw)
    {
        return [
            'language' => $language,
            'sample' => mb_substr($raw, 0, 180),
        ];
    }

    protected function isTooSmall($raw)
    {
        if (preg_match('/^[\d\W_]+$/u', $raw)) {
            return true;
        }

        return (bool) preg_match('/^(ok|okay|yes|yeah|yep|no|k|1|2|3|4|5)$/i', $raw);
    }

    protected function hits($text, array $needles)
    {
        $score = 0;
        foreach ($needles as $needle) {
            if ($needle !== '' && mb_strpos($text, $needle) !== false) {
                $score++;
            }
        }

        return $score;
    }
}

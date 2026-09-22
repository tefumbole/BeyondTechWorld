<?php

namespace App\Services\WhatsApp;

use App\WhatsApp\LeadCatalog;

class WhatsAppLeadClassifier
{
    public function isMeaningful($body)
    {
        $text = $this->normalize($body);
        if ($text === '') {
            return false;
        }
        if ($this->isNoise($text)) {
            return false;
        }
        if (mb_strlen($text) < 8 && ! $this->hasBusinessKeyword($text)) {
            return false;
        }

        return $this->hasBusinessKeyword($text) || $this->looksLikeRequest($text);
    }

    public function category($body)
    {
        $text = $this->normalize($body);
        $hits = [];
        foreach ($this->keywordMap() as $category => $words) {
            foreach ($words as $word) {
                if ($this->contains($text, $word)) {
                    $hits[$category] = isset($hits[$category]) ? $hits[$category] + 1 : 1;
                }
            }
        }
        if ($hits === []) {
            return null;
        }
        arsort($hits);
        $top = array_keys($hits)[0];
        if ($hits[$top] < 1) {
            return null;
        }

        return $top;
    }

    public function isNoise($text)
    {
        $text = $this->normalize($text);
        if ($text === '') {
            return true;
        }
        $exact = [
            'hi', 'hello', 'hey', 'yo', 'ok', 'okay', 'k', 'kk', 'yes', 'no',
            'thanks', 'thank you', 'thank u', 'merci', 'bonjour', 'bonsoir',
            'how are you', 'how have you been', 'how r u', 'good morning',
            'good evening', 'good night', 'morning', 'evening', 'please',
            'wrong number', 'sorry wrong number', 'who is this',
        ];
        if (in_array($text, $exact, true)) {
            return true;
        }
        if (preg_match('/^[\p{So}\p{Sk}\s]+$/u', $text)) {
            return true;
        }
        if (preg_match('/^(hi+|hello+|hey+|ok+|thanks+|thank you)[\s!.]*$/i', $text)) {
            return true;
        }

        return false;
    }

    protected function looksLikeRequest($text)
    {
        return (bool) preg_match('/\b(need|want|how much|can you|do you|available|quote|price|cost|install|provide|join|book)\b/i', $text);
    }

    protected function hasBusinessKeyword($text)
    {
        foreach ($this->keywordMap() as $words) {
            foreach ($words as $word) {
                if ($this->contains($text, $word)) {
                    return true;
                }
            }
        }

        return $this->looksLikeRequest($text) && mb_strlen($text) >= 12;
    }

    protected function keywordMap()
    {
        return [
            LeadCatalog::CAT_AUDIO => ['speaker', 'speakers', 'sound', 'audio', 'pa system', 'microphone', 'mic'],
            LeadCatalog::CAT_LED_SCREEN => ['led', 'led screen', 'screen', 'display wall'],
            LeadCatalog::CAT_LIGHTING => ['light', 'lighting', 'stage light'],
            LeadCatalog::CAT_EVENT_PRODUCTION => ['wedding', 'event', 'ceremony', 'concert', 'production'],
            LeadCatalog::CAT_EQUIPMENT_RENTAL => ['rent', 'rental', 'hire', 'equipment'],
            LeadCatalog::CAT_CCTV => ['cctv', 'camera', 'surveillance'],
            LeadCatalog::CAT_NETWORKING => ['network', 'networking', 'wifi', 'lan', 'office network'],
            LeadCatalog::CAT_IT_SUPPORT => ['it support', 'computer', 'repair', 'technician'],
            LeadCatalog::CAT_CYBERSECURITY => ['cyber', 'cybersecurity', 'security training'],
            LeadCatalog::CAT_SOFTWARE => ['website', 'web site', 'software', 'app', 'application', 'system'],
            LeadCatalog::CAT_TRAINING => ['training', 'course', 'class'],
            LeadCatalog::CAT_INTERNSHIP => ['intern', 'internship', 'student attachment'],
            LeadCatalog::CAT_PROPERTY => ['hall', 'venue', 'room', 'facility', 'property'],
            LeadCatalog::CAT_BILL_PAYMENT => ['bill', 'pay bill', 'invoice pay'],
        ];
    }

    protected function contains($text, $word)
    {
        $word = $this->normalize($word);
        if ($word === '') {
            return false;
        }

        return preg_match('/\b'.preg_quote($word, '/').'\b/u', $text) === 1;
    }

    protected function normalize($body)
    {
        $text = strtolower(trim(preg_replace('/\s+/', ' ', (string) $body)));
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text);

        return trim(preg_replace('/\s+/', ' ', (string) $text));
    }
}

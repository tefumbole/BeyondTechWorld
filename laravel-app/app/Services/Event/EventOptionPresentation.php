<?php

namespace App\Services\Event;

/**
 * Ensures wedding/event option prompts always ship clickable choices (website)
 * and accept numbered replies (1/2/3) on both website and WhatsApp.
 */
class EventOptionPresentation
{
    public function soundUi()
    {
        $ui = app(EventPackageCatalogService::class)->soundModeGroup();
        if (empty($ui['options'])) {
            $ui = [
                'type' => 'OPTION_GROUP',
                'mode' => 'radio',
                'category' => 'SOUND_MODE',
                'prompt' => '',
                'options' => [
                    ['value' => 'sound_mode:playback', 'label' => '🎵 Playback — basic sound, no live instruments', 'code' => 'PLAYBACK'],
                    ['value' => 'sound_mode:playback_piano', 'label' => '🎹 Piano Bar — playback + piano/keyboard', 'code' => 'PLAYBACK_PIANO'],
                    ['value' => 'sound_mode:full_live', 'label' => '🎸 Full Setup — live band / full instruments', 'code' => 'FULL_LIVE'],
                ],
            ];
        } else {
            foreach ($ui['options'] as &$opt) {
                $code = strtoupper(isset($opt['code']) ? $opt['code'] : '');
                if ($code === 'PLAYBACK') {
                    $opt['label'] = '🎵 Playback — basic sound, no live instruments';
                } elseif ($code === 'PLAYBACK_PIANO') {
                    $opt['label'] = '🎹 Piano Bar — playback + piano/keyboard';
                } elseif ($code === 'FULL_LIVE') {
                    $opt['label'] = '🎸 Full Setup — live band / full instruments';
                }
            }
            unset($opt);
        }
        $ui['options'] = $this->numberLabels($ui['options']);
        $ui['prompt'] = 'What sound experience are you looking for? Tap an option or reply 1, 2, or 3.';

        return $ui;
    }

    public function extrasUi()
    {
        $ui = app(EventPackageCatalogService::class)->extrasCheckboxGroup();
        $ui['options'] = $this->numberLabels($ui['options']);
        $ui['prompt'] = 'Do you also need any of these? Select all that apply, or reply with numbers (e.g. 1 and 3).';

        return $ui;
    }

    /** After the customer already declined a screen — only Lights / Stage / None. */
    public function extrasUiWithoutScreen()
    {
        $ui = [
            'type' => 'OPTION_GROUP',
            'mode' => 'checkbox',
            'category' => 'EXTRAS',
            'prompt' => 'Would you like Lights or Stage for the event? Select all that apply, or reply with numbers.',
            'confirm_label' => 'Continue',
            'options' => [
                ['value' => 'lights', 'label' => '💡 Lights'],
                ['value' => 'stage', 'label' => '🎭 Stage'],
                ['value' => 'none', 'label' => '🚫 None of these'],
            ],
        ];
        $ui['options'] = $this->numberLabels($ui['options']);

        return $ui;
    }

    public function lightingUi()
    {
        $ui = app(EventPackageCatalogService::class)->lightingTierGroup();
        if (empty($ui['options'])) {
            $ui = [
                'type' => 'OPTION_GROUP',
                'mode' => 'radio',
                'category' => 'LIGHTING',
                'prompt' => '',
                'options' => [
                    ['value' => 'lighting:basic', 'label' => '💡 Basic Lights (No Moving heads)', 'code' => 'BASIC'],
                    ['value' => 'lighting:standard', 'label' => '✨ Standard Lights (Par Lights with Par Robots)', 'code' => 'STANDARD'],
                    ['value' => 'lighting:premium', 'label' => '🌟 Premium (All Lights)', 'code' => 'PREMIUM'],
                ],
            ];
        }
        $ui['options'] = $this->numberLabels($ui['options']);
        $ui['prompt'] = 'Which lighting package do you need? Tap an option or reply 1, 2, or 3.';

        return $ui;
    }

    /**
     * Infer option UI from an assistant reply that listed choices in plain text.
     *
     * @return array|null
     */
    public function inferUiFromReply($reply)
    {
        $text = strtolower((string) $reply);
        if ($text === '') {
            return null;
        }

        $hasSound = (strpos($text, 'playback') !== false && (strpos($text, 'piano') !== false || strpos($text, 'piano bar') !== false) && (strpos($text, 'full setup') !== false || strpos($text, 'full live') !== false));
        $hasExtras = (strpos($text, 'lights') !== false && strpos($text, 'screens') !== false && strpos($text, 'stage') !== false);
        $hasLighting = (strpos($text, 'basic') !== false && strpos($text, 'standard') !== false && strpos($text, 'premium') !== false && (strpos($text, 'light') !== false || strpos($text, 'moving') !== false));

        // Prefer extras when the bot already confirmed a sound mode and is asking for add-ons.
        if ($hasExtras && (strpos($text, 'extra') !== false || strpos($text, 'also need') !== false || strpos($text, 'add any') !== false || strpos($text, 'select all') !== false || ! $hasSound)) {
            if ($hasExtras && ! $hasSound) {
                return $this->extrasUi();
            }
            if ($hasExtras && (strpos($text, 'extra') !== false || strpos($text, 'also') !== false || strpos($text, 'add') !== false)) {
                return $this->extrasUi();
            }
        }
        if ($hasSound) {
            return $this->soundUi();
        }
        if ($hasLighting) {
            return $this->lightingUi();
        }
        if ($hasExtras) {
            return $this->extrasUi();
        }

        return null;
    }

    /**
     * Map a numeric / short reply onto the awaiting option group's values.
     *
     * @return string|null rewritten message body, or null if not a match
     */
    public function resolveIncomingChoice($awaitingGroup, $text)
    {
        $group = strtoupper(trim((string) $awaitingGroup));
        $raw = trim((string) $text);
        if ($group === '' || $raw === '') {
            return null;
        }
        $t = strtolower($raw);

        // Already a structured choice from the widget.
        if (preg_match('/^(sound_mode:|lighting:|extras:)/', $t)) {
            return $raw;
        }

        if ($group === 'SOUND_MODE') {
            if (preg_match('/^(1|playback|sound_mode:playback)$/', $t)) {
                return 'sound_mode:playback';
            }
            if (preg_match('/^(2|piano ?bar|playback_piano|playback \+ piano|sound_mode:playback_piano)$/', $t)) {
                return 'sound_mode:playback_piano';
            }
            if (preg_match('/^(3|full( setup| live)?|full_live|sound_mode:full_live)$/', $t)) {
                return 'sound_mode:full_live';
            }
        }

        if ($group === 'LIGHTING') {
            if (preg_match('/^(1|basic|lighting:basic)$/', $t)) {
                return 'lighting:basic';
            }
            if (preg_match('/^(2|standard|lighting:standard)$/', $t)) {
                return 'lighting:standard';
            }
            if (preg_match('/^(3|premium|lighting:premium)$/', $t)) {
                return 'lighting:premium';
            }
        }

        if ($group === 'EXTRAS') {
            if (preg_match('/^(4|none|none of these|extras:none)$/', $t)) {
                return 'extras:none';
            }
            // "1", "1 and 3", "1,2", "lights and stage"
            $picked = [];
            if (preg_match('/\b1\b/', $t) || strpos($t, 'light') !== false) {
                $picked[] = 'lights';
            }
            if (preg_match('/\b2\b/', $t) || strpos($t, 'screen') !== false) {
                $picked[] = 'screens';
            }
            if (preg_match('/\b3\b/', $t) || strpos($t, 'stage') !== false) {
                $picked[] = 'stage';
            }
            if ($picked) {
                return 'extras:'.implode(',', array_unique($picked));
            }
        }

        return null;
    }

    public function awaitingGroupFromUi(array $ui)
    {
        $cat = isset($ui['category']) ? strtoupper((string) $ui['category']) : '';
        if (in_array($cat, ['SOUND_MODE', 'EXTRAS', 'LIGHTING'], true)) {
            return $cat;
        }

        return null;
    }

    public function mediaFromUi(array $ui)
    {
        return [
            'ui' => $ui,
            'choices' => isset($ui['options']) && is_array($ui['options']) ? $ui['options'] : [],
        ];
    }

    /**
     * WhatsApp has no tap cards, so the choices have to be in the message.
     */
    public function textWithChoices($reply, array $ui)
    {
        $reply = trim((string) $reply);
        $lines = $this->choiceLines($ui);
        if (count($lines) < 2) {
            return $reply;
        }
        if ($this->alreadyListsChoices($reply, $lines)) {
            return $reply;
        }
        $reply = preg_replace('/\n*Tap an option below, or reply with the number\.?\s*$/i', '', $reply);
        $reply = preg_replace('/\n*Select all that apply below, or reply with numbers[^\n]*$/i', '', $reply);
        $reply = preg_replace('/\s*Your options are:\s*$/i', '', $reply);
        $reply = trim($reply);
        $cat = isset($ui['category']) ? strtoupper((string) $ui['category']) : '';
        $hint = $cat === 'EXTRAS'
            ? 'Reply with the numbers, for example 1 and 3.'
            : 'Reply with the number.';

        return $reply."\n\n".implode("\n", $lines)."\n\n".$hint;
    }

    /**
     * Labels to show under a chat bubble when the saved text only says to tap an option.
     *
     * @return array
     */
    public function displayChoices($body, $mediaJson = null)
    {
        $decoded = json_decode((string) $mediaJson, true);
        if (is_array($decoded)) {
            $fromMedia = $this->choiceLines(isset($decoded['ui']) && is_array($decoded['ui']) ? $decoded['ui'] : ['options' => isset($decoded['choices']) ? $decoded['choices'] : []]);
            if (count($fromMedia) >= 2) {
                return $fromMedia;
            }
        }
        $text = (string) $body;
        $ui = $this->inferUiFromReply($text);
        if (! $ui && stripos($text, 'sound experience') !== false) {
            $ui = $this->soundUi();
        } elseif (! $ui && (stripos($text, 'lighting package') !== false || stripos($text, 'which lighting') !== false)) {
            $ui = $this->lightingUi();
        } elseif (! $ui && (stripos($text, 'select all that apply') !== false || stripos($text, 'lights or stage') !== false)) {
            $ui = $this->extrasUi();
        }
        if (! $ui) {
            return [];
        }
        $lines = $this->choiceLines($ui);
        if ($this->alreadyListsChoices($text, $lines)) {
            return [];
        }

        return $lines;
    }

    protected function choiceLines(array $ui)
    {
        $options = isset($ui['options']) && is_array($ui['options']) ? $ui['options'] : [];
        $lines = [];
        foreach ($options as $opt) {
            if (! is_array($opt)) {
                continue;
            }
            $label = trim((string) (isset($opt['label']) ? $opt['label'] : ''));
            if ($label !== '') {
                $lines[] = $label;
            }
        }

        return $lines;
    }

    protected function alreadyListsChoices($reply, array $lines)
    {
        if (! preg_match('/\b1[\.\)]/', (string) $reply) || ! preg_match('/\b2[\.\)]/', (string) $reply)) {
            return false;
        }
        $present = 0;
        foreach ($lines as $label) {
            $token = preg_replace('/^\d+\.\s*/', '', $label);
            $token = trim(preg_replace('/^[^\p{L}\p{N}]+/u', '', $token));
            $parts = preg_split('/\s+[—–-]\s+/u', $token);
            $token = trim(isset($parts[0]) ? $parts[0] : $token);
            if (strlen($token) >= 3 && stripos((string) $reply, $token) !== false) {
                $present++;
            }
        }

        return $present >= 2;
    }

    /**
     * Soften a plain-text option dump when clickable choices will be shown.
     */
    public function tidyReplyForUi($reply, array $ui)
    {
        $reply = trim((string) $reply);
        $cat = isset($ui['category']) ? strtoupper((string) $ui['category']) : '';
        $hint = 'Tap an option below, or reply with the number.';

        if ($cat === 'SOUND_MODE') {
            $reply = preg_replace('/\n*\s*(here are your options:?\s*)?(\d+\.\s*)?(playback|piano bar|full setup).*$/is', '', $reply);
            $reply = trim($reply);
            if ($reply === '' || strlen($reply) < 12) {
                $reply = 'What sound experience are you looking for?';
            }
            if (stripos($reply, 'tap an option') === false && stripos($reply, 'reply with') === false) {
                $reply .= "\n\n".$hint;
            }
        } elseif ($cat === 'EXTRAS') {
            if (stripos($reply, 'tap') === false && stripos($reply, 'select') === false) {
                $reply = rtrim($reply)."\n\nSelect all that apply below, or reply with numbers (e.g. 1 and 3).";
            }
        } elseif ($cat === 'LIGHTING') {
            if (stripos($reply, 'tap an option') === false) {
                $reply = rtrim($reply)."\n\n".$hint;
            }
        }

        return $reply;
    }

    protected function numberLabels(array $options)
    {
        $n = 1;
        foreach ($options as &$opt) {
            $label = isset($opt['label']) ? (string) $opt['label'] : (string) $opt['value'];
            if (! preg_match('/^\d+\./', $label)) {
                $opt['label'] = $n.'. '.$label;
            }
            $opt['number'] = $n;
            $n++;
        }
        unset($opt);

        return $options;
    }
}

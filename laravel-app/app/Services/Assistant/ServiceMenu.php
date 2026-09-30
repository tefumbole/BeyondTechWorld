<?php

namespace App\Services\Assistant;

class ServiceMenu
{
    public function text()
    {
        return "You can tap a service below, or reply with a number:\n1. Sound\n2. Light\n3. Screens\n4. IT services\n5. Others and specify";
    }

    public function websitePrompt()
    {
        return 'Pick a service below:';
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    public function choices()
    {
        return [
            ['value' => '1', 'label' => '🔊 Sound'],
            ['value' => '2', 'label' => '💡 Light'],
            ['value' => '3', 'label' => '🖥️ Screens'],
            ['value' => '4', 'label' => '💻 IT services'],
            ['value' => '5', 'label' => '➕ Others and specify'],
        ];
    }

    public function options()
    {
        return ['1. Sound', '2. Light', '3. Screens', '4. IT services', '5. Others and specify'];
    }

    public function match($text)
    {
        $t = strtolower(trim((string) $text));
        if (preg_match('/^(1|sound|1\.\s*sound)$/', $t)) {
            return 'sound';
        }
        if (preg_match('/^(2|light|lights|lighting|2\.\s*light)$/', $t)) {
            return 'light';
        }
        if (preg_match('/^(3|screens?|3\.\s*screens)$/', $t)) {
            return 'screen';
        }
        if (preg_match('/^(4|it|it services|4\.\s*it services)$/', $t)) {
            return 'it';
        }
        if (preg_match('/^(5|others?|others? and specify|5\.\s*others.*)$/', $t)) {
            return 'other';
        }

        return null;
    }

    /**
     * Event-first replies — never dump a random product catalogue SKU list.
     *
     * @return array{reply:string,ui?:array,choices?:array}
     */
    public function describePayload($key, $text)
    {
        if ($key === 'other') {
            $extra = trim(preg_replace('/^(5|others?( and specify)?)\b[:\-\s.]*/i', '', (string) $text));
            if ($extra === '') {
                return [
                    'reply' => "Tell me what you need for your event — for example sound, lighting, LED screen, stage or truss — and I'll build a suitable BeyondTechWorld solution.",
                ];
            }

            return [
                'reply' => "Got it: {$extra}. What is the event date and venue so I can shape the right package?",
            ];
        }
        if ($key === 'sound') {
            $ui = app(\App\Services\Event\EventOptionPresentation::class)->soundUi();

            return [
                'reply' => "Great — let's plan your event sound.\n\nWhat date is the event?\n\n".$ui['prompt'],
                'ui' => $ui,
                'choices' => $ui['options'],
            ];
        }
        if ($key === 'light') {
            $ui = app(\App\Services\Event\EventOptionPresentation::class)->lightingUi();

            return [
                'reply' => $ui['prompt']."\n\nWhat is the event date and venue?",
                'ui' => $ui,
                'choices' => $ui['options'],
            ];
        }
        if ($key === 'screen') {
            return [
                'reply' => app(\App\Services\Event\ScreenPricingService::class)->sizePrompt()
                    ."\n\nAlso share the event date and venue when you can.",
            ];
        }

        return [
            'reply' => 'Tell me a bit about the event (date, venue, guests) and what IT support you need.',
        ];
    }

    public function describe($key, $text)
    {
        $payload = $this->describePayload($key, $text);

        return $payload['reply'];
    }
}

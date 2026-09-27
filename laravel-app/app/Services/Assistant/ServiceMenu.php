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
            $catalog = app(\App\Services\Event\EventPackageCatalogService::class);
            $ui = $catalog->optionGroup('SOUND_MODE', 'For the sound setup, which option would you prefer?');

            return [
                'reply' => "Great — let's design a sound solution for your event (not a single random speaker).\n\nFor the sound setup, which option would you prefer?\n\n🎵 Playback — No Live Instruments\n🎹 Playback + Piano Bar\n🎸 Full Live Setup\n\nAlso share the event date and venue if you haven't yet.",
                'ui' => $ui,
                'choices' => $ui['options'],
            ];
        }
        if ($key === 'light') {
            $catalog = app(\App\Services\Event\EventPackageCatalogService::class);
            $ui = $catalog->optionGroup('LIGHTING', 'Would you also like lighting for the event?');

            return [
                'reply' => "Happy to plan lighting for your event.\n\n💡 Basic Lighting\n✨ Standard Lighting\n🌟 Premium Lighting\n🚫 No Lighting\n\nWhat is the event date and venue?",
                'ui' => $ui,
                'choices' => $ui['options'],
            ];
        }
        if ($key === 'screen') {
            return [
                'reply' => "Would you like an LED screen as part of the setup?\n\n🖥️ LED Screen — yes, please check catalogue options\n🚫 No screen\n\nShare the event date and venue so I can check real availability.",
                'choices' => [
                    ['value' => 'screen:yes', 'label' => '🖥️ LED Screen'],
                    ['value' => 'screen:no', 'label' => '🚫 No screen'],
                ],
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

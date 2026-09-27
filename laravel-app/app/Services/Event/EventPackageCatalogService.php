<?php

namespace App\Services\Event;

use App\EventPackage;
use Illuminate\Support\Facades\Schema;

class EventPackageCatalogService
{
    public function packages($category = null)
    {
        if (! Schema::hasTable('event_packages')) {
            return [];
        }
        $q = EventPackage::active()->orderBy('sort_order');
        if ($category) {
            $q->category($category);
        }

        return $q->get()->map(function ($p) {
            return $this->serialize($p);
        })->values()->all();
    }

    public function find($category, $code)
    {
        if (! Schema::hasTable('event_packages')) {
            return null;
        }

        return EventPackage::active()
            ->category($category)
            ->where('code', strtoupper((string) $code))
            ->with('components')
            ->first();
    }

    public function optionGroup($category, $prompt = null, $mode = 'radio')
    {
        $options = [];
        foreach ($this->packages($category) as $pkg) {
            // Hide NONE from lighting radio when presenting tiers after extras selected.
            if ($category === 'LIGHTING' && $pkg['code'] === 'NONE' && $mode === 'radio') {
                // keep NONE for explicit "no lights" lists; callers can filter
            }
            $label = ($pkg['icon'] ? $pkg['icon'].' ' : '').$pkg['name'];
            if (! empty($pkg['description']) && in_array(strtoupper((string) $category), ['LIGHTING', 'SOUND_MODE'], true)) {
                // Prefer short description for lighting/mode clarity.
                $label = ($pkg['icon'] ? $pkg['icon'].' ' : '').$pkg['name'];
                if ((float) $pkg['base_price'] > 0) {
                    $label .= ' — '.number_format((float) $pkg['base_price'], 0).' CFA';
                }
                if ($pkg['description']) {
                    $label .= ' · '.$pkg['description'];
                }
            } elseif ((float) $pkg['base_price'] > 0) {
                $label .= ' — '.number_format((float) $pkg['base_price'], 0).' CFA';
            }
            $options[] = [
                'value' => strtolower($category).':'.strtolower($pkg['code']),
                'label' => $label,
                'description' => $pkg['description'],
                'code' => $pkg['code'],
                'price' => $pkg['base_price'],
                'icon' => $pkg['icon'],
            ];
        }

        return [
            'type' => 'OPTION_GROUP',
            'mode' => $mode === 'checkbox' ? 'checkbox' : 'radio',
            'category' => strtoupper((string) $category),
            'prompt' => $prompt,
            'options' => $options,
        ];
    }

    public function extrasCheckboxGroup()
    {
        return [
            'type' => 'OPTION_GROUP',
            'mode' => 'checkbox',
            'category' => 'EXTRAS',
            'prompt' => 'Do you also need any of these? Select all that apply.',
            'confirm_label' => 'Continue',
            'options' => [
                ['value' => 'lights', 'label' => '💡 Lights'],
                ['value' => 'screens', 'label' => '🖥️ Screens'],
                ['value' => 'stage', 'label' => '🎭 Stage'],
                ['value' => 'none', 'label' => '🚫 None of these'],
            ],
        ];
    }

    public function lightingTierGroup()
    {
        $ui = $this->optionGroup('LIGHTING', 'Which lighting package do you need?');
        $ui['options'] = array_values(array_filter($ui['options'], function ($o) {
            return strtoupper($o['code']) !== 'NONE';
        }));
        // Clearer business labels
        foreach ($ui['options'] as &$opt) {
            $code = strtoupper($opt['code']);
            if ($code === 'BASIC') {
                $opt['label'] = '💡 Basic Lights (No Moving heads) — '.number_format((float) $opt['price'], 0).' CFA';
            } elseif ($code === 'STANDARD') {
                $opt['label'] = '✨ Standard Lights (Par Lights with Par Robots) — '.number_format((float) $opt['price'], 0).' CFA';
            } elseif ($code === 'PREMIUM') {
                $opt['label'] = '🌟 Premium (All Lights) — '.number_format((float) $opt['price'], 0).' CFA';
            }
        }
        unset($opt);

        return $ui;
    }

    public function soundModeGroup()
    {
        $ui = $this->optionGroup('SOUND_MODE', 'For the sound setup, which option would you prefer?');
        foreach ($ui['options'] as &$opt) {
            $code = strtoupper($opt['code']);
            if ($code === 'PLAYBACK') {
                $opt['label'] = '🎵 Playback — basic sound, no live instruments';
            } elseif ($code === 'PLAYBACK_PIANO') {
                $opt['label'] = '🎹 Piano Bar — playback + piano/keyboard';
            } elseif ($code === 'FULL_LIVE') {
                $opt['label'] = '🎸 Full Setup — live band / full instruments';
            }
        }
        unset($opt);

        return $ui;
    }

    public function serialize(EventPackage $p)
    {
        return [
            'id' => $p->id,
            'category' => $p->category,
            'code' => $p->code,
            'name' => $p->name,
            'description' => $p->description,
            'base_price' => (float) $p->base_price,
            'currency' => $p->currency,
            'duration_days' => (int) $p->duration_days,
            'icon' => $p->icon,
            'components' => $p->relationLoaded('components')
                ? $p->components->map(function ($c) {
                    return [
                        'type' => $c->component_type,
                        'category_key' => $c->category_key,
                        'product_id' => $c->product_id,
                        'qty' => (int) $c->qty,
                        'required' => (bool) $c->required,
                        'search_query' => $c->search_query,
                    ];
                })->values()->all()
                : [],
        ];
    }
}

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

    public function optionGroup($category, $prompt = null)
    {
        $options = [];
        foreach ($this->packages($category) as $pkg) {
            $label = ($pkg['icon'] ? $pkg['icon'].' ' : '').$pkg['name'];
            if ((float) $pkg['base_price'] > 0) {
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
            'category' => strtoupper((string) $category),
            'prompt' => $prompt,
            'options' => $options,
        ];
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

<?php

namespace App\Services\Event;

use App\EventPackage;
use App\EventPricingRule;
use Illuminate\Support\Facades\Schema;

class TrussPricingService
{
    public function options()
    {
        if (Schema::hasTable('event_packages')) {
            $rows = EventPackage::active()->category('TRUSS')->orderBy('sort_order')->get();
            if ($rows->isNotEmpty()) {
                return $rows->map(function ($p) {
                    return [
                        'code' => $p->code,
                        'name' => $p->name,
                        'description' => $p->description,
                        'price' => (float) $p->base_price,
                        'currency' => $p->currency,
                        'icon' => $p->icon,
                    ];
                })->values()->all();
            }
        }

        return [
            ['code' => 'WITHOUT_ROOF', 'name' => 'Truss without Roof', 'price' => EventPricingRule::amountFor('truss_without_roof', 300000), 'icon' => '🏗️'],
            ['code' => 'WITH_ROOF', 'name' => 'Truss with Roof', 'price' => EventPricingRule::amountFor('truss_with_roof', 500000), 'icon' => '🏟️'],
            ['code' => 'NONE', 'name' => 'No Truss', 'price' => 0, 'icon' => '🚫'],
        ];
    }

    public function priceFor($code)
    {
        $code = strtoupper(trim((string) $code));
        foreach ($this->options() as $opt) {
            if ($opt['code'] === $code) {
                return [
                    'success' => true,
                    'code' => $code,
                    'name' => $opt['name'],
                    'price' => (float) $opt['price'],
                    'currency' => 'XAF',
                    'formatted' => number_format((float) $opt['price'], 0).' CFA',
                ];
            }
        }

        return ['success' => false, 'error' => 'unknown_truss_option'];
    }
}

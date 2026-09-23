<?php

namespace App\Services\Rental;

use App\WhatsApp\RentalRequest;

class RentalRecommendationService
{
    protected $availability;
    protected $pricing;

    public function __construct(RentalAvailabilityService $availability, RentalPricingService $pricing)
    {
        $this->availability = $availability;
        $this->pricing = $pricing;
    }

    public function propose(RentalRequest $request, array $slots)
    {
        $range = $this->availability->resolveRange([
            'event_date' => $request->event_date ? $request->event_date->toDateString() : (isset($slots['event_date']) ? $slots['event_date'] : ''),
        ]);
        if (! $range) {
            return ['success' => false, 'error' => 'date_required', 'lines' => []];
        }
        $queries = $this->queries($request, $slots);
        $lines = [];
        $seen = [];
        foreach ($queries as $query) {
            $product = $this->availability->search($query, 1)->first();
            if (! $product || isset($seen[$product->id])) {
                continue;
            }
            $seen[$product->id] = true;
            $qty = isset($slots['qty']) && count($queries) === 1 ? (int) $slots['qty'] : 1;
            $check = $this->availability->assess($product, $qty, $range['start'], $range['end']);
            $price = $this->pricing->priceLine($product, $check['available'] ? $qty : min($qty, (int) $check['available_qty']), $range['days']);
            if (empty($price['success'])) {
                $lines[] = [
                    'success' => false,
                    'error' => 'unpriced',
                    'name' => $product->name,
                    'product_id' => $product->id,
                    'requested_qty' => $qty,
                    'available_qty' => $check['available_qty'],
                    'available' => false,
                ];
                continue;
            }
            $useQty = ! empty($check['available']) ? $qty : (int) $check['available_qty'];
            if ($useQty < 1) {
                $alts = $this->availability->alternatives($product, $qty, $range['start'], $range['end']);
                $lines[] = [
                    'success' => true,
                    'available' => false,
                    'name' => $product->name,
                    'product_id' => $product->id,
                    'requested_qty' => $qty,
                    'available_qty' => 0,
                    'alternatives' => $alts,
                    'line_total' => 0,
                    'unit_price' => $price['unit_price'],
                    'rental_days' => $range['days'],
                ];
                continue;
            }
            if ($useQty !== $qty) {
                $price = $this->pricing->priceLine($product, $useQty, $range['days']);
            }
            $lines[] = array_merge($price, [
                'available' => $useQty >= $qty,
                'requested_qty' => $qty,
                'quantity' => $useQty,
                'available_qty' => $check['available_qty'],
                'partial' => $useQty < $qty,
                'alternatives' => $useQty < $qty ? $this->availability->alternatives($product, $qty - $useQty, $range['start'], $range['end']) : [],
            ]);
        }

        return [
            'success' => true,
            'lines' => $lines,
            'total' => $this->pricing->total($lines),
            'start' => $range['start'],
            'end' => $range['end'],
            'days' => $range['days'],
            'checked_at' => now()->toDateTimeString(),
        ];
    }

    protected function queries(RentalRequest $request, array $slots)
    {
        if (! empty($slots['product'])) {
            return [strtolower($slots['product'])];
        }
        $categories = $request->categories();
        if ($categories === [] && $request->event_type) {
            $categories = ['sound'];
        }
        $guests = (int) $request->attendance;
        $queries = [];
        $profiles = config('rental_recommendations.profiles', []);
        foreach ($categories as $category) {
            $bands = isset($profiles[$category]) ? $profiles[$category] : [];
            foreach ($bands as $band) {
                if ($guests > 0 && $guests > (int) $band['max_guests']) {
                    continue;
                }
                foreach ($band['queries'] as $query) {
                    $queries[] = $query;
                }
                break;
            }
        }

        return array_values(array_unique($queries));
    }
}

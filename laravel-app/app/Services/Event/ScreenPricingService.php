<?php

namespace App\Services\Event;

use App\EventPricingRule;

/**
 * Deterministic LED screen pricing: CFA per square metre.
 */
class ScreenPricingService
{
    public function ratePerM2()
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('products')) {
                $product = \App\Product::where('is_active', 1)
                    ->where('price', '>', 0)
                    ->where(function ($q) {
                        $q->where('name', 'like', '%led screen%')
                            ->orWhere('name', 'like', '%screen per%')
                            ->orWhere('name', 'like', '%per m2%')
                            ->orWhere('name', 'like', '%per m²%');
                    })
                    ->orderBy('id')
                    ->first();
                if ($product && (float) $product->price > 0 && (float) $product->price <= 200000) {
                    return (float) $product->price;
                }
            }
        } catch (\Throwable $e) {
        }

        return EventPricingRule::amountFor('screen_per_m2', 60000);
    }

    public function calculate($lengthM, $widthM)
    {
        $length = max(0, (float) $lengthM);
        $width = max(0, (float) $widthM);
        $area = round($length * $width, 2);

        return $this->priceFromArea($area, $length, $width);
    }

    public function calculateArea($areaM2)
    {
        $area = max(0, (float) $areaM2);

        return $this->priceFromArea($area, null, null);
    }

    public function sizePrompt()
    {
        $rate = number_format($this->ratePerM2(), 0);

        return 'Please type the LED screen Height and Width in meters (for example 3 × 2 or 4*2), or the total square meters (for example 6 m²). Pricing is '.$rate.' CFA per m².';
    }

    /**
     * @return array{0:float,1:float}|array{area:float}|null
     */
    public function parseSize($text)
    {
        $t = trim((string) $text);
        $t = function_exists('mb_strtolower') ? mb_strtolower($t, 'UTF-8') : strtolower($t);
        $dims = app(StagePricingService::class)->parseDimensions($t);
        if ($dims) {
            return $dims;
        }
        // Height and width as "height 3 width 2" / "h:3 w:2"
        if (preg_match('/(?:height|h)\s*[:=]?\s*(\d+(?:\.\d+)?).{0,24}?(?:width|w)\s*[:=]?\s*(\d+(?:\.\d+)?)/i', $t, $m)) {
            return [(float) $m[1], (float) $m[2]];
        }
        if (preg_match('/(?:width|w)\s*[:=]?\s*(\d+(?:\.\d+)?).{0,24}?(?:height|h)\s*[:=]?\s*(\d+(?:\.\d+)?)/i', $t, $m)) {
            return [(float) $m[2], (float) $m[1]];
        }
        // Total square meters only: "6 m2", "6m²", "6 square meters"
        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:m\s*²|m2|sq\.?\s*m(?:eters?|etres?)?|square\s*m(?:eters?|etres?)?)\b/i', $t, $m)) {
            return ['area' => (float) $m[1]];
        }
        if (preg_match('/^(?:total\s*)?(?:area\s*)?(\d+(?:\.\d+)?)\s*(?:m²|m2)?$/i', $t, $m) && (strpos($t, 'm') !== false || strpos($t, 'area') !== false || strpos($t, 'total') !== false)) {
            return ['area' => (float) $m[1]];
        }

        return null;
    }

    public function parseDimensions($text)
    {
        $parsed = $this->parseSize($text);
        if (is_array($parsed) && isset($parsed[0], $parsed[1])) {
            return [$parsed[0], $parsed[1]];
        }

        return null;
    }

    protected function priceFromArea($area, $length = null, $width = null)
    {
        $area = round((float) $area, 2);
        $rate = $this->ratePerM2();
        $price = round($area * $rate, 2);
        if ($length !== null && $width !== null) {
            $message = sprintf(
                'A %.0fm × %.0fm LED screen is %.0f m². At %s CFA per square metre, the screen would be %s CFA.',
                $length,
                $width,
                $area,
                number_format($rate, 0),
                number_format($price, 0)
            );
        } else {
            $message = sprintf(
                'An LED screen of %.0f m² at %s CFA per square metre would be %s CFA.',
                $area,
                number_format($rate, 0),
                number_format($price, 0)
            );
        }

        return [
            'success' => true,
            'length_m' => $length,
            'width_m' => $width,
            'area_m2' => $area,
            'rate_per_m2' => $rate,
            'currency' => 'XAF',
            'price' => $price,
            'formatted' => number_format($price, 0).' CFA',
            'message' => $message,
        ];
    }
}

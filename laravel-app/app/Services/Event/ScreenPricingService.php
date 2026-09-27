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
        return EventPricingRule::amountFor('screen_per_m2', 60000);
    }

    public function calculate($lengthM, $widthM)
    {
        $length = max(0, (float) $lengthM);
        $width = max(0, (float) $widthM);
        $area = round($length * $width, 2);
        $rate = $this->ratePerM2();
        $price = round($area * $rate, 2);

        return [
            'success' => true,
            'length_m' => $length,
            'width_m' => $width,
            'area_m2' => $area,
            'rate_per_m2' => $rate,
            'currency' => 'XAF',
            'price' => $price,
            'formatted' => number_format($price, 0).' CFA',
            'message' => sprintf(
                'A %.0fm × %.0fm LED screen is %.0f m². At %s CFA per square metre, the screen would be %s CFA.',
                $length,
                $width,
                $area,
                number_format($rate, 0),
                number_format($price, 0)
            ),
        ];
    }

    public function parseDimensions($text)
    {
        return app(StagePricingService::class)->parseDimensions($text);
    }
}

<?php

namespace App\Services\Event;

use App\EventPricingRule;

/**
 * Deterministic stage pricing. OpenAI must not invent this math.
 */
class StagePricingService
{
    public function ratePerM2()
    {
        return EventPricingRule::amountFor('stage_per_m2', 40000);
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
                'A %.0fm × %.0fm stage is %.0f m². At %s CFA per square metre, the stage would be %s CFA.',
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
        $t = strtolower(trim((string) $text));
        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:m|meters?|metres?)?\s*(?:[x×]|by)\s*(\d+(?:\.\d+)?)/i', $t, $m)) {
            return [(float) $m[1], (float) $m[2]];
        }
        if (preg_match('/(\d+(?:\.\d+)?)\s*[x×]\s*(\d+(?:\.\d+)?)/i', $t, $m)) {
            return [(float) $m[1], (float) $m[2]];
        }

        return null;
    }
}

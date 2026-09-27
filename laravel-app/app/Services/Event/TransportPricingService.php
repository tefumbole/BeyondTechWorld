<?php

namespace App\Services\Event;

use App\EventPricingRule;

class TransportPricingService
{
    /**
     * Within-town transport tier follows the higher of sound/lighting commercial levels
     * unless an explicit transport_tier is provided.
     */
    public function calculate(array $context)
    {
        $withinTown = ! empty($context['within_town']);
        if (isset($context['location_scope'])) {
            $withinTown = strtolower((string) $context['location_scope']) === 'within_town';
        }
        if (! $withinTown && empty($context['force_within_town'])) {
            return [
                'success' => true,
                'status' => 'TRANSPORT_QUOTE_REQUIRED',
                'price' => null,
                'currency' => 'XAF',
                'message' => 'Transport for this location needs a staff quote (outside configured within-town rates).',
                'pending_pricing' => true,
            ];
        }

        $tier = isset($context['transport_tier']) ? strtoupper((string) $context['transport_tier']) : null;
        if (! $tier) {
            $tier = $this->inferTier(
                isset($context['sound_package']) ? $context['sound_package'] : null,
                isset($context['lighting_package']) ? $context['lighting_package'] : null
            );
        }

        $map = [
            'BASIC' => EventPricingRule::amountFor('transport_basic', 60000),
            'STANDARD' => EventPricingRule::amountFor('transport_standard', 60000),
            'PREMIUM' => EventPricingRule::amountFor('transport_premium', 120000),
        ];
        $amount = isset($map[$tier]) ? $map[$tier] : $map['STANDARD'];

        return [
            'success' => true,
            'status' => 'PRICED',
            'tier' => $tier,
            'within_town' => true,
            'price' => (float) $amount,
            'currency' => 'XAF',
            'formatted' => number_format((float) $amount, 0).' CFA',
            'pending_pricing' => false,
        ];
    }

    protected function inferTier($sound, $lighting)
    {
        $rank = ['NONE' => 0, 'BASIC' => 1, 'STANDARD' => 2, 'PREMIUM' => 3];
        $s = strtoupper((string) $sound);
        $l = strtoupper((string) $lighting);
        $best = max(isset($rank[$s]) ? $rank[$s] : 0, isset($rank[$l]) ? $rank[$l] : 0);
        foreach ($rank as $name => $r) {
            if ($r === $best && $name !== 'NONE') {
                return $name;
            }
        }

        return 'STANDARD';
    }
}

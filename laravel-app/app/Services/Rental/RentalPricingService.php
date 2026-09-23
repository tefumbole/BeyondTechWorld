<?php

namespace App\Services\Rental;

use App\Product;

class RentalPricingService
{
    /**
     * ERP daily rate only. Discounts are never applied here.
     */
    public function priceLine(Product $product, $qty, $days)
    {
        $rate = (float) $product->rent_price_per_day;
        $quantity = max(1, (int) $qty);
        $duration = max(1, (int) $days);
        if ($rate <= 0) {
            return [
                'success' => false,
                'error' => 'unpriced',
                'product_id' => $product->id,
                'name' => $product->name,
                'quantity' => $quantity,
            ];
        }
        $total = round($rate * $quantity * $duration, 2);

        return [
            'success' => true,
            'product_id' => $product->id,
            'name' => $product->name,
            'quantity' => $quantity,
            'unit_price' => $rate,
            'rental_days' => $duration,
            'discount' => 0,
            'line_total' => $total,
            'tax' => 0,
        ];
    }

    public function total(array $lines)
    {
        $sum = 0;
        foreach ($lines as $line) {
            if (! empty($line['success']) && isset($line['line_total'])) {
                $sum += (float) $line['line_total'];
            }
        }

        return round($sum, 2);
    }
}

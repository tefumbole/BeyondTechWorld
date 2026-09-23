<?php

namespace App\Services\Rental;

use App\Product;

class RentalPricingService
{
    /**
     * Same unit price the Quotation screen uses. Not the rental daily rate.
     * Discounts are never applied here.
     */
    public function quotationUnitPrice(Product $product)
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('products', 'price')) {
            return 0.0;
        }
        $today = date('Y-m-d');
        if (! empty($product->promotion) && (float) $product->promotion_price > 0 && ! empty($product->last_date) && $today <= $product->last_date) {
            return (float) $product->promotion_price;
        }

        return (float) $product->price;
    }

    public function priceLine(Product $product, $qty, $days)
    {
        $rate = $this->quotationUnitPrice($product);
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
        $total = round($rate * $quantity, 2);

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

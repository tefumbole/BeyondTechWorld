<?php

namespace App\Support;

use App\Booking;
use App\BookingProduct;
use App\Category;

class BookingCategoryHelper
{
    public static function accommodationCategoryNames()
    {
        return array_map('strtoupper', config('booking.accommodation_categories', []));
    }

    public static function calendarExcludedCategoryIds()
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $names = array_map('strtoupper', config('booking.calendar_excluded_categories', []));

        $cached = Category::all()
            ->filter(function ($category) use ($names) {
                $upper = strtoupper($category->name);
                foreach ($names as $name) {
                    if ($upper === $name || strpos($upper, $name) !== false) {
                        return true;
                    }
                }

                return false;
            })
            ->pluck('id')
            ->all();

        return $cached;
    }

    public static function isAccommodationLine(BookingProduct $line)
    {
        $product = $line->product;
        if (!$product) {
            return false;
        }

        $categoryName = strtoupper(optional($product->category)->name ?? '');
        foreach (self::accommodationCategoryNames() as $name) {
            if ($categoryName === $name || strpos($categoryName, $name) !== false) {
                return true;
            }
        }

        $productName = strtoupper(trim($product->name));
        if (preg_match('/^ROOM\s*\d+/i', $product->name)) {
            return true;
        }
        if (strpos($productName, 'APARTMENT') !== false || strpos($productName, 'ACCOMMOD') !== false) {
            return true;
        }
        if (strpos($productName, 'FURNISHED') !== false && strpos($productName, 'ROOM') !== false) {
            return true;
        }

        return false;
    }

    public static function isAccommodationBooking(Booking $booking)
    {
        $booking->loadMissing('bookingProduct.product.category');

        if ($booking->bookingProduct->isEmpty()) {
            return false;
        }

        foreach ($booking->bookingProduct as $line) {
            if (!self::isAccommodationLine($line)) {
                return false;
            }
        }

        return true;
    }

    public static function contractTypeForBooking(Booking $booking)
    {
        return self::isAccommodationBooking($booking) ? 'accommodation' : 'equipment';
    }
}

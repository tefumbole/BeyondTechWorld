<?php

namespace App\Services\Rental;

use App\BookingProduct;
use App\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Date availability from current warehouse qty plus existing booking lines.
 * Status 1 already reduced product.qty. Status 2 (pending) has not.
 * Draft bookings do not hold stock.
 */
class RentalAvailabilityService
{
    public function dateIssue(array $slots)
    {
        $raw = isset($slots['event_date']) ? strtolower(trim((string) $slots['event_date'])) : '';
        if ($raw === '') {
            return 'missing';
        }
        if ($this->isAmbiguousDate($raw)) {
            return 'ambiguous';
        }
        if (! $this->parseDate($raw)) {
            return 'ambiguous';
        }

        return 'ok';
    }

    public function resolveRange(array $slots)
    {
        if ($this->dateIssue($slots) !== 'ok') {
            return null;
        }
        $raw = strtolower(trim((string) $slots['event_date']));
        $start = $this->parseDate($raw);
        if (! $start) {
            return null;
        }
        $endRaw = isset($slots['event_end']) ? strtolower(trim((string) $slots['event_end'])) : '';
        $end = $endRaw !== '' ? $this->parseDate($endRaw) : null;
        if (! $end || $end->lte($start)) {
            $end = $start->copy()->addDay();
        }

        return [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'days' => max(1, $start->diffInDays($end)),
        ];
    }

    public function search($query, $limit = 5)
    {
        if (! Schema::hasTable('products')) {
            return collect();
        }
        $q = strtolower(trim((string) $query));
        $builder = Product::query()->where('is_active', true);
        if ($q !== '') {
            $terms = array_unique(array_filter([$q, rtrim($q, 's')]));
            $builder->where(function ($inner) use ($terms) {
                foreach ($terms as $term) {
                    $inner->orWhere('name', 'like', '%'.$term.'%')->orWhere('code', 'like', '%'.$term.'%');
                }
            });
        }

        return $builder->orderBy('name')->limit($limit)->get();
    }

    public function assess(Product $product, $qty, $start, $end)
    {
        $requested = max(1, (int) $qty);
        $rate = Schema::hasColumn('products', 'rent_price_per_day') ? (float) $product->rent_price_per_day : 0.0;
        $base = [
            'product_id' => $product->id,
            'name' => $product->name,
            'code' => $product->code,
            'requested_qty' => $requested,
            'day_rate' => $rate,
            'priced' => $rate > 0,
            'start' => $start,
            'end' => $end,
            'listed_day_rate' => $rate,
        ];
        if (! Schema::hasColumn('products', 'qty') || ! Schema::hasTable('booking_products') || ! Schema::hasTable('bookings')) {
            return $base + [
                'success' => true,
                'availability_checked' => false,
                'available' => false,
                'available_qty' => null,
                'reason' => 'inventory_unavailable',
            ];
        }

        $onHand = (float) $product->qty;
        $returning = $this->returningBefore($product->id, $start);
        $pending = $this->pendingOverlap($product->id, $start, $end);
        $available = $onHand + $returning - $pending;
        if ($available < 0) {
            $available = 0;
        }

        return $base + [
            'success' => true,
            'availability_checked' => true,
            'available' => $available >= $requested,
            'available_qty' => $available,
            'on_hand' => $onHand,
            'returning_before' => $returning,
            'pending_overlap' => $pending,
        ];
    }

    public function alternatives(Product $product, $qty, $start, $end, $limit = 3)
    {
        $token = strtok(strtolower((string) $product->name), ' ');
        if (! $token) {
            return [];
        }
        $rows = $this->search($token, 8);
        $out = [];
        foreach ($rows as $row) {
            if ((int) $row->id === (int) $product->id) {
                continue;
            }
            $check = $this->assess($row, $qty, $start, $end);
            if (empty($check['available']) || empty($check['priced'])) {
                continue;
            }
            $out[] = $check;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    protected function returningBefore($productId, $start)
    {
        return (float) $this->linesForStatuses($productId, [1, '1'])
            ->whereDate('booking_products.end', '<=', $start)
            ->when(Schema::hasColumn('booking_products', 'is_return'), function ($q) {
                $q->where(function ($inner) {
                    $inner->whereNull('booking_products.is_return')->orWhere('booking_products.is_return', 0);
                });
            })
            ->sum('booking_products.qty');
    }

    protected function pendingOverlap($productId, $start, $end)
    {
        return (float) $this->linesForStatuses($productId, [2, '2'])
            ->where('booking_products.start', '<', $end.' 23:59:59')
            ->where('booking_products.end', '>', $start.' 00:00:00')
            ->sum('booking_products.qty');
    }

    protected function linesForStatuses($productId, array $statuses)
    {
        return BookingProduct::query()
            ->join('bookings', 'bookings.id', '=', 'booking_products.booking_id')
            ->where('booking_products.product_id', $productId)
            ->whereIn('bookings.booking_status', $statuses);
    }

    public function isAmbiguousDate($raw)
    {
        $raw = strtolower(trim((string) $raw));

        return (bool) preg_match('/\b(this weekend|next weekend|next month|sometime|soon|later)\b/', $raw);
    }

    protected function parseDate($raw)
    {
        $raw = strtolower(trim((string) $raw));
        if ($raw === '' || $this->isAmbiguousDate($raw)) {
            return null;
        }
        if ($raw === 'tomorrow') {
            return Carbon::tomorrow()->startOfDay();
        }
        if (preg_match('/^next\s+(sunday|monday|tuesday|wednesday|thursday|friday|saturday)$/', $raw, $m)) {
            $next = Carbon::parse('next '.$m[1])->startOfDay();
            if (strtolower(Carbon::now()->format('l')) === $m[1]) {
                return Carbon::today()->addWeek();
            }

            return $next;
        }
        $weekdays = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        if (in_array($raw, $weekdays, true)) {
            if (strtolower(Carbon::now()->format('l')) === $raw) {
                return Carbon::today();
            }

            return Carbon::parse('next '.$raw)->startOfDay();
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return Carbon::parse($raw)->startOfDay();
        }
        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $raw, $m)) {
            return Carbon::createFromDate((int) $m[3], (int) $m[2], (int) $m[1])->startOfDay();
        }
        if (preg_match('/^(\d{1,2})\s+([a-z]+)$/', $raw, $m) || preg_match('/^([a-z]+)\s+(\d{1,2})$/', $raw, $m)) {
            $day = ctype_digit($m[1]) ? (int) $m[1] : (int) $m[2];
            $monthName = ctype_digit($m[1]) ? $m[2] : $m[1];
            $month = $this->monthNumber($monthName);
            if (! $month || $day < 1 || $day > 31) {
                return null;
            }
            $year = (int) Carbon::now()->year;
            $date = Carbon::createFromDate($year, $month, $day)->startOfDay();
            if ($date->lt(Carbon::today())) {
                $date->addYear();
            }

            return $date;
        }

        return null;
    }

    protected function monthNumber($name)
    {
        $map = [
            'jan' => 1, 'january' => 1, 'feb' => 2, 'february' => 2, 'mar' => 3, 'march' => 3,
            'apr' => 4, 'april' => 4, 'may' => 5, 'jun' => 6, 'june' => 6, 'jul' => 7, 'july' => 7,
            'aug' => 8, 'august' => 8, 'sep' => 9, 'sept' => 9, 'september' => 9,
            'oct' => 10, 'october' => 10, 'nov' => 11, 'november' => 11, 'dec' => 12, 'december' => 12,
        ];

        return isset($map[$name]) ? $map[$name] : null;
    }
}

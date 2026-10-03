<?php

namespace App\Services\Cloud;

use App\Booking;
use App\Customer;
use App\Payment;
use App\Product;
use App\Quotation;
use App\Sale;
use Illuminate\Support\Facades\Schema;

/**
 * Figures for one customer company's home page.
 * Every query uses the active company scope, so another company's rows stay out.
 */
class CloudCompanyDashboard
{
    public function snapshot()
    {
        $blank = $this->blank();
        $tenant = app(CloudTenantContext::class)->tenant();
        if ($tenant) {
            $blank['companyName'] = $tenant->system_name ?: $tenant->name;
            $blank['currency'] = $tenant->currency ?: 'XAF';
        }
        try {
            $months = [];
            $sales = [];
            $payments = [];
            for ($i = 5; $i >= 0; $i--) {
                $start = now()->startOfMonth()->subMonths($i);
                $end = (clone $start)->endOfMonth();
                $months[] = $start->format('M Y');
                $sales[] = Schema::hasTable('sales')
                    ? (float) Sale::whereBetween('created_at', [$start, $end])->sum('grand_total')
                    : 0;
                $payments[] = Schema::hasTable('payments')
                    ? (float) Payment::whereBetween('created_at', [$start, $end])->sum('amount')
                    : 0;
            }
            $blank['months'] = $months;
            $blank['salesSeries'] = $sales;
            $blank['paymentSeries'] = $payments;
            $blank['customerCount'] = Schema::hasTable('customers') ? (int) Customer::count() : 0;
            $blank['productCount'] = Schema::hasTable('products') ? (int) Product::count() : 0;
            $blank['quotationCount'] = Schema::hasTable('quotations') ? (int) Quotation::count() : 0;
            $blank['bookingCount'] = Schema::hasTable('bookings') ? (int) Booking::count() : 0;
            $blank['saleCount'] = Schema::hasTable('sales') ? (int) Sale::count() : 0;
            $blank['monthSales'] = Schema::hasTable('sales')
                ? (float) Sale::where('created_at', '>=', now()->startOfMonth())->sum('grand_total')
                : 0;
            if (Schema::hasTable('sales')) {
                $blank['recentSales'] = Sale::orderByDesc('id')->take(6)->get()->map(function ($sale) {
                    return [
                        'reference' => $sale->reference_no ?: ('#'.$sale->id),
                        'total' => (float) $sale->grand_total,
                        'when' => $sale->created_at ? $sale->created_at->format('d M Y') : '',
                    ];
                })->all();
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $blank;
    }

    protected function blank()
    {
        return [
            'companyName' => 'Company',
            'currency' => 'XAF',
            'months' => [],
            'salesSeries' => [],
            'paymentSeries' => [],
            'customerCount' => 0,
            'productCount' => 0,
            'quotationCount' => 0,
            'bookingCount' => 0,
            'saleCount' => 0,
            'monthSales' => 0,
            'recentSales' => [],
        ];
    }
}

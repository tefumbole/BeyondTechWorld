<?php

namespace App\Services;

use App\Shareholder;
use Illuminate\Support\Facades\DB;

class ShareSettingsService
{
    public function getSettings()
    {
        $row = DB::table('be_share_settings')->orderBy('id')->first();
        if (! $row) {
            return $this->defaults();
        }

        $total = (int) $row->total_shares_available;
        $override = (int) $row->total_sold_admin_override;
        $sold = Shareholder::where('status', 'approved')->whereNull('deleted_at')->sum('shares_assigned');

        return [
            'price_per_share' => (float) $row->price_per_share,
            'total_shares_available' => $total,
            'total_sold_admin_override' => $override,
            'currency' => $row->currency ?: 'USD',
            'available_shares' => max(0, $total - max($sold, $override)),
        ];
    }

    public function formatPrice($amount, $currency = 'USD')
    {
        $num = (float) $amount;
        $symbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'RWF' => 'RWF ', 'XAF' => 'FCFA '];
        $symbol = $symbols[$currency] ?? '$';
        $formatted = number_format($num, 2);

        return in_array($currency, ['RWF', 'XAF'], true) ? $symbol.$formatted : $symbol.$formatted;
    }

    private function defaults()
    {
        return [
            'price_per_share' => 1000,
            'total_shares_available' => 10000,
            'total_sold_admin_override' => 0,
            'currency' => 'USD',
            'available_shares' => 10000,
        ];
    }
}

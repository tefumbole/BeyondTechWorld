<?php

namespace App\Console\Commands;

use App\BtwContract;
use App\ContractSetting;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessContractExpiryAlerts extends Command
{
    protected $signature = 'contracts:expiry-alerts';

    protected $description = 'Flag/log contracts approaching end_date and mark overdue as expired';

    public function handle()
    {
        $days = (int) ContractSetting::getValue('expiry_alert_days', 30);
        $now = Carbon::today();
        $horizon = $now->copy()->addDays(max(1, $days));

        $expiring = BtwContract::with(['partyB', 'type'])
            ->whereNotNull('end_date')
            ->whereDate('end_date', '>=', $now)
            ->whereDate('end_date', '<=', $horizon)
            ->whereNotIn('status', [
                BtwContract::STATUS_CANCELLED,
                BtwContract::STATUS_SUPERSEDED,
                BtwContract::STATUS_EXPIRED,
            ])
            ->orderBy('end_date')
            ->get();

        Log::info('contracts:expiry-alerts', [
            'within_days' => $days,
            'count' => $expiring->count(),
            'numbers' => $expiring->pluck('number')->all(),
        ]);
        $this->info($expiring->count().' contract(s) expiring within '.$days.' days.');

        $marked = BtwContract::whereNotNull('end_date')
            ->whereDate('end_date', '<', $now)
            ->whereIn('status', [
                BtwContract::STATUS_SIGNED,
                BtwContract::STATUS_AWAITING_CLIENT,
                BtwContract::STATUS_AWAITING_ADMIN,
            ])
            ->update(['status' => BtwContract::STATUS_EXPIRED]);

        if ($marked) {
            $this->info('Marked '.$marked.' contract(s) as expired.');
        }

        return 0;
    }
}

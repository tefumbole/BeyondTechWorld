<?php

namespace App\Services\Contracts;

use App\BtwContract;
use App\ContractSetting;

class ContractNumberService
{
    public function next($typeCode = null)
    {
        $prefix = ContractSetting::getValue('number_prefix', 'CNT');
        $year = date('Y');
        $base = $prefix.'-'.$year.'-';

        $last = BtwContract::withTrashed()
            ->where('number', 'like', $base.'%')
            ->orderByDesc('number')
            ->value('number');

        $seq = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $base.str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }
}

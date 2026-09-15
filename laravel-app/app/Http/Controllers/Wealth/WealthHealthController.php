<?php

namespace App\Http\Controllers\Wealth;

use App\Services\Wealth\FinancialHealthService;
use App\Services\Wealth\WealthFilter;
use Illuminate\Http\Request;

class WealthHealthController extends WealthBaseController
{
    public function index(Request $request, FinancialHealthService $health)
    {
        if (! $this->canAny(['wealth.view', 'wealth.reports.view'])) {
            return $this->deny();
        }
        $filter = WealthFilter::fromRequest($request);
        $data = $health->calculate($filter);
        $history = $health->history($filter);

        return view('wealth.health', $this->lookups() + [
            'wmTab' => 'health',
            'filter' => $filter,
            'health' => $data,
            'history' => $history,
        ]);
    }
}

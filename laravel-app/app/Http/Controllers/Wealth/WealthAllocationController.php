<?php

namespace App\Http\Controllers\Wealth;

use App\Services\Wealth\WealthAllocationService;
use App\Services\Wealth\WealthFilter;
use Illuminate\Http\Request;

class WealthAllocationController extends WealthBaseController
{
    public function index(Request $request, WealthAllocationService $alloc)
    {
        if (! $this->canAny(['wealth.view', 'wealth.reports.view'])) {
            return $this->deny();
        }
        $filter = WealthFilter::fromRequest($request);

        return view('wealth.allocations', $this->lookups() + [
            'wmTab' => 'allocations',
            'filter' => $filter,
            'snapshot' => $alloc->snapshot($filter),
        ]);
    }
}

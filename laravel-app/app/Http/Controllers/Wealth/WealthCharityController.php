<?php

namespace App\Http\Controllers\Wealth;

use App\Services\Wealth\WealthAllocationService;
use App\Services\Wealth\WealthFilter;
use App\Support\WealthMoney;
use App\WealthCharityTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WealthCharityController extends WealthBaseController
{
    public function index(Request $request, WealthAllocationService $alloc)
    {
        if (! $this->can('wealth.charity.view')) {
            return $this->deny();
        }
        $filter = WealthFilter::fromRequest($request);
        $q = WealthCharityTransaction::with('subcategory')->orderByDesc('id');
        if ($filter->billerId) {
            $q->where('biller_id', $filter->billerId);
        }
        if ($filter->programId) {
            $q->where('program_id', $filter->programId);
        }
        if ($request->filled('beneficiary')) {
            $q->where('beneficiary', 'like', '%'.$request->get('beneficiary').'%');
        }
        $rows = $q->get();
        $snap = $alloc->snapshot($filter);
        $cha = $alloc->rowForCode($snap, 'CHARITY');

        return view('wealth.charity', $this->lookups() + [
            'wmTab' => 'charity',
            'filter' => $filter,
            'rows' => $rows,
            'alloc' => $cha,
            'given' => WealthMoney::of($rows->sum('amount')),
        ]);
    }

    public function store(Request $request)
    {
        if (! $this->can('wealth.charity.manage')) {
            return $this->deny();
        }
        $data = $request->validate([
            'beneficiary' => 'required|string|max:190',
            'subcategory_id' => 'nullable|integer',
            'amount' => 'required|numeric|min:0',
            'given_on' => 'nullable|date',
            'biller_id' => 'nullable|integer',
            'user_id' => 'nullable|integer',
            'program_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'payment_method' => 'nullable|string|max:64',
            'notes' => 'nullable|string',
        ]);
        $data['amount'] = WealthMoney::of($data['amount']);
        $data['created_by'] = Auth::id();
        $data['document'] = $this->storeWealthFile($request->file('document'), 'documents');
        WealthCharityTransaction::create($data);

        return back()->with('message', 'Charity recorded.');
    }
}

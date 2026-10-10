<?php

namespace App\Http\Controllers;

use App\CampayPayout;
use App\Customer;
use App\Services\CampayPayoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PayoutController extends Controller
{
    public function index(Request $request)
    {
        $this->guard();
        $q = trim((string) $request->get('q', ''));
        $people = Customer::query()
            ->where('phone_number', '!=', '')
            ->orderBy('name');
        if ($q !== '') {
            $like = '%'.$q.'%';
            $people->where(function ($rows) use ($like) {
                $rows->where('name', 'like', $like)->orWhere('phone_number', 'like', $like);
            });
        }
        $people = $people->limit(80)->get(['id', 'name', 'phone_number']);
        $history = CampayPayout::orderByDesc('id')->limit(40)->get();
        $balance = app(CampayPayoutService::class)->balance();

        return view('payout.index', compact('people', 'q', 'history', 'balance'));
    }

    public function store(Request $request)
    {
        $this->guard();
        $amount = (int) $request->input('amount');
        $note = trim((string) $request->input('note', ''));
        if ($note === '') {
            $note = 'Payout';
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $request->input('people', [])))));
        if ($amount < 100 || $amount > 1000000) {
            return redirect()->route('payout.index')->with('not_permitted', 'Enter an amount from 100 to 1,000,000 XAF.');
        }
        if (count($ids) < 1 || count($ids) > 20) {
            return redirect()->route('payout.index')->with('not_permitted', 'Select between 1 and 20 people.');
        }
        $customers = Customer::whereIn('id', $ids)->get(['id', 'name', 'phone_number']);
        if ($customers->count() !== count($ids)) {
            return redirect()->route('payout.index')->with('not_permitted', 'One of the selected people could not be found.');
        }

        @set_time_limit(180);
        $service = app(CampayPayoutService::class);
        $paid = 0;
        $failed = 0;
        foreach ($customers as $customer) {
            $row = CampayPayout::create([
                'user_id' => Auth::id(),
                'customer_id' => $customer->id,
                'person_name' => (string) $customer->name,
                'phone' => (string) $customer->phone_number,
                'amount' => $amount,
                'currency' => 'XAF',
                'note' => substr($note, 0, 180),
                'external_reference' => 'po-'.date('YmdHis').'-'.$customer->id.'-'.substr(md5(uniqid('', true)), 0, 6),
                'status' => 'pending',
            ]);
            $service->pay($row);
            if ($row->status === 'paid') {
                $paid++;
            } else {
                $failed++;
            }
        }
        $total = number_format($paid * $amount, 0, '.', ' ');

        return redirect()->route('payout.index')->with('message', $paid.' paid ('.$total.' XAF). '.$failed.' not paid. Each result is in the list below.');
    }

    protected function guard()
    {
        if (! Auth::check() || ! in_array((int) Auth::user()->role_id, [1, 2], true)) {
            abort(403);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\CampayPayout;
use App\CampayPayoutLink;
use App\CampayPayoutRequest;
use App\Customer;
use App\Services\CampayPayoutService;
use App\Services\Cloud\CloudTenantContext;
use App\Services\MobileMoneyHolderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PayoutController extends Controller
{
    public function index(Request $request)
    {
        $this->guard();
        $tenantId = $this->tenantId();
        $pending = $this->requests($tenantId)->where('status', 'pending')->orderByDesc('id')->limit(40)->get();
        $totals = $this->totals($pending->pluck('id')->all());
        $open = null;
        $lines = collect();
        $requestId = (int) $request->get('request');
        if ($requestId) {
            $open = $this->requests($tenantId)->where('id', $requestId)->first();
            if ($open) {
                $lines = CampayPayout::where('request_id', $open->id)->orderBy('id')->get();
                $this->fillMomoNames($lines);
            }
        }
        $history = $this->history($tenantId)->orderByDesc('id')->limit(30)->get();
        $balance = app(CampayPayoutService::class)->balance();

        return view('payout.index', compact('pending', 'totals', 'open', 'lines', 'history', 'balance'));
    }

    public function requestLink()
    {
        $this->guard();
        $tenantId = $this->tenantId();
        $query = CampayPayoutLink::query();
        if ($tenantId) {
            $query->where('cloud_tenant_id', $tenantId);
        } else {
            $query->whereNull('cloud_tenant_id');
        }
        $link = $query->first();
        if (! $link) {
            $link = new CampayPayoutLink();
            $link->token = bin2hex(random_bytes(20));
            $link->cloud_tenant_id = $tenantId;
            $link->save();
        }
        $url = route('payout.request.form', ['token' => $link->token]);

        return view('payout.request', compact('url'));
    }

    public function pay(Request $request)
    {
        $this->guard();
        $open = $this->requests($this->tenantId())->where('id', (int) $request->input('request_id'))->first();
        if (! $open) {
            abort(404);
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $request->input('lines', [])))));
        if (count($ids) < 1 || count($ids) > 40) {
            return redirect()->route('payout.index', ['request' => $open->id])->with('not_permitted', 'Select the people to pay.');
        }
        $rows = CampayPayout::where('request_id', $open->id)->whereIn('id', $ids)->where('status', 'pending')->get();
        if ($rows->count() !== count($ids)) {
            return redirect()->route('payout.index', ['request' => $open->id])->with('not_permitted', 'One of those payments is no longer waiting.');
        }
        $amounts = (array) $request->input('amounts', []);
        foreach ($rows as $row) {
            $amount = isset($amounts[$row->id]) ? (int) $amounts[$row->id] : 0;
            if ($amount < 100 || $amount > 1000000) {
                return redirect()->route('payout.index', ['request' => $open->id])->with('not_permitted', 'Each amount must be from 100 to 1,000,000 XAF.');
            }
            $row->amount = $amount;
            if ($open->note) {
                $row->note = $open->note;
            }
            $row->user_id = Auth::id();
            $row->save();
        }

        @set_time_limit(180);
        $service = app(CampayPayoutService::class);
        $paid = 0;
        $failed = 0;
        $total = 0;
        foreach ($rows as $row) {
            $service->pay($row);
            if ($row->status === 'paid') {
                $paid++;
                $total += (int) $row->amount;
            } else {
                $failed++;
            }
        }
        $left = CampayPayout::where('request_id', $open->id)->where('status', 'pending')->count();
        if ($left === 0) {
            $open->status = 'done';
            $open->save();
        }

        return redirect()->route('payout.index', ['request' => $open->id])->with(
            'message',
            $paid.' paid ('.number_format($total, 0, '.', ' ').' XAF). '.$failed.' not paid.'
        );
    }

    public function publicForm(Request $request, $token)
    {
        $link = $this->link($token);
        $q = trim((string) $request->get('q', ''));
        $people = $this->people($link, $q);

        return view('payout.public', compact('people', 'q', 'token'));
    }

    public function publicStore(Request $request, $token)
    {
        $link = $this->link($token);
        $requester = trim((string) $request->input('requester_name', ''));
        $note = trim((string) $request->input('note', ''));
        if ($requester === '' || strlen($requester) > 80) {
            return back()->withInput()->with('not_permitted', 'Enter your name.');
        }
        $service = app(CampayPayoutService::class);
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $request->input('customer_id', [])))));
        $amounts = (array) $request->input('amount', []);
        $lines = [];
        $seen = [];
        if ($ids) {
            $customers = app(CloudTenantContext::class)->withoutIsolation(function () use ($ids, $link) {
                $query = Customer::whereIn('id', $ids);
                if ($link->cloud_tenant_id) {
                    $query->where('cloud_tenant_id', $link->cloud_tenant_id);
                }

                return $query->get(['id', 'name', 'phone_number']);
            });
            if ($customers->count() !== count($ids)) {
                return back()->withInput()->with('not_permitted', 'One of the selected people is not on this list.');
            }
            foreach ($customers as $customer) {
                $phone = $service->momoNumber($customer->phone_number);
                $amount = isset($amounts[$customer->id]) ? (int) $amounts[$customer->id] : 0;
                if (! $phone) {
                    return back()->withInput()->with('not_permitted', $customer->name.' does not have an MTN or Orange Cameroon number.');
                }
                if ($amount < 100 || $amount > 1000000) {
                    return back()->withInput()->with('not_permitted', 'Enter an amount from 100 to 1,000,000 XAF for '.$customer->name.'.');
                }
                $seen[$phone] = true;
                $lines[] = [
                    'customer_id' => $customer->id,
                    'person_name' => (string) $customer->name,
                    'phone' => $phone,
                    'amount' => $amount,
                ];
            }
        }
        $extraPhones = (array) $request->input('extra_phone', []);
        $extraAmounts = (array) $request->input('extra_amount', []);
        foreach ($extraPhones as $index => $rawPhone) {
            $rawPhone = trim((string) $rawPhone);
            if ($rawPhone === '') {
                continue;
            }
            $phone = $service->momoNumber($rawPhone);
            if (! $phone) {
                return back()->withInput()->with('not_permitted', $rawPhone.' is not an MTN or Orange Cameroon number.');
            }
            if (isset($seen[$phone])) {
                return back()->withInput()->with('not_permitted', 'That number is already on the list.');
            }
            $amount = isset($extraAmounts[$index]) ? (int) $extraAmounts[$index] : 0;
            if ($amount < 100 || $amount > 1000000) {
                return back()->withInput()->with('not_permitted', 'Enter an amount from 100 to 1,000,000 XAF for '.$phone.'.');
            }
            $seen[$phone] = true;
            $match = $this->customerByPhone($link, $phone);
            $lines[] = [
                'customer_id' => $match ? $match->id : null,
                'person_name' => $match ? (string) $match->name : $phone,
                'phone' => $phone,
                'amount' => $amount,
            ];
        }
        if (count($lines) < 1 || count($lines) > 40) {
            return back()->withInput()->with('not_permitted', 'Choose between 1 and 40 people.');
        }

        $batch = new CampayPayoutRequest();
        $batch->cloud_tenant_id = $link->cloud_tenant_id;
        $batch->requester_name = $requester;
        $batch->note = $note !== '' ? substr($note, 0, 180) : null;
        $batch->status = 'pending';
        $batch->save();
        foreach ($lines as $line) {
            CampayPayout::create([
                'customer_id' => $line['customer_id'],
                'request_id' => $batch->id,
                'person_name' => substr($line['person_name'], 0, 191),
                'phone' => $line['phone'],
                'amount' => $line['amount'],
                'currency' => 'XAF',
                'note' => $batch->note ?: 'Payout',
                'external_reference' => 'po-'.date('YmdHis').'-'.$batch->id.'-'.substr(md5(uniqid('', true)), 0, 8),
                'status' => 'pending',
            ]);
        }

        return redirect()->route('payout.request.form', ['token' => $token])->with('message', 'Sent. The payment is waiting for approval.');
    }

    protected function fillMomoNames($lines)
    {
        $lookup = app(MobileMoneyHolderService::class);
        $service = app(CampayPayoutService::class);
        @set_time_limit(90);
        $checked = 0;
        foreach ($lines as $line) {
            $line->network = $service->operatorName($line->phone);
            if ((int) $line->momo_checked === 1 || $checked >= 25) {
                continue;
            }
            $checked++;
            $hit = $lookup->lookup($line->phone);
            $line->momo_name = ($hit && ! empty($hit['name'])) ? substr($hit['name'], 0, 180) : '';
            $line->momo_checked = 1;
            $line->save();
        }
    }

    protected function people(CampayPayoutLink $link, $q)
    {
        return app(CloudTenantContext::class)->withoutIsolation(function () use ($link, $q) {
            $query = Customer::query()->where('phone_number', '!=', '')->orderBy('name');
            if ($link->cloud_tenant_id) {
                $query->where('cloud_tenant_id', $link->cloud_tenant_id);
            }
            if ($q !== '') {
                $like = '%'.$q.'%';
                $query->where(function ($rows) use ($like) {
                    $rows->where('name', 'like', $like)->orWhere('phone_number', 'like', $like);
                });
            }

            return $query->limit(80)->get(['id', 'name', 'phone_number']);
        });
    }

    protected function customerByPhone(CampayPayoutLink $link, $phone)
    {
        $tail = substr($phone, -9);

        return app(CloudTenantContext::class)->withoutIsolation(function () use ($link, $tail, $phone) {
            $query = Customer::query()->where('phone_number', 'like', '%'.$tail);
            if ($link->cloud_tenant_id) {
                $query->where('cloud_tenant_id', $link->cloud_tenant_id);
            }
            $service = app(CampayPayoutService::class);
            foreach ($query->limit(20)->get(['id', 'name', 'phone_number']) as $customer) {
                if ($service->momoNumber($customer->phone_number) === $phone) {
                    return $customer;
                }
            }

            return null;
        });
    }

    protected function requests($tenantId)
    {
        $query = CampayPayoutRequest::query();
        if ($tenantId) {
            $query->where('cloud_tenant_id', $tenantId);
        }

        return $query;
    }

    protected function history($tenantId)
    {
        $query = CampayPayout::query()->where('status', '!=', 'pending');
        if ($tenantId) {
            $ids = CampayPayoutRequest::where('cloud_tenant_id', $tenantId)->pluck('id');
            $query->where(function ($rows) use ($ids) {
                $rows->whereIn('request_id', $ids->all())->orWhereNull('request_id');
            });
        }

        return $query;
    }

    protected function totals(array $ids)
    {
        if (! $ids) {
            return collect();
        }

        return CampayPayout::whereIn('request_id', $ids)
            ->selectRaw('request_id, count(*) as people, sum(amount) as total')
            ->groupBy('request_id')
            ->get()
            ->keyBy('request_id');
    }

    protected function link($token)
    {
        $link = CampayPayoutLink::where('token', $token)->first();
        if (! $link) {
            abort(404);
        }

        return $link;
    }

    protected function tenantId()
    {
        return app(CloudTenantContext::class)->id();
    }

    protected function guard()
    {
        if (! Auth::check() || ! in_array((int) Auth::user()->role_id, [1, 2], true)) {
            abort(403);
        }
    }
}

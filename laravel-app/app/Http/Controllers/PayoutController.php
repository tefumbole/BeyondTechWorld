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
        $making = $request->get('new') === '1';
        $q = '';
        $people = collect();

        return view('payout.index', compact('pending', 'totals', 'open', 'lines', 'history', 'balance', 'making', 'q', 'people'));
    }

    public function search(Request $request)
    {
        $this->guard();
        $q = trim((string) $request->get('q', ''));
        if ($q === '') {
            return response()->json(['people' => []]);
        }

        return response()->json(['people' => $this->directory($q)]);
    }

    public function lookup(Request $request)
    {
        $this->guard();
        $service = app(CampayPayoutService::class);
        $phone = $service->momoNumber($request->get('phone'));
        if (! $phone) {
            return response()->json(['ok' => false, 'error' => 'Not an MTN or Orange Cameroon number.']);
        }
        $hit = app(MobileMoneyHolderService::class)->lookup($phone);

        return response()->json([
            'ok' => true,
            'phone' => $phone,
            'name' => ($hit && ! empty($hit['name'])) ? $hit['name'] : '',
            'operator' => $service->operatorName($phone),
        ]);
    }

    public function direct(Request $request)
    {
        $this->guard();
        $note = trim((string) $request->input('note', ''));
        if ($note === '') {
            $note = 'Payout';
        }
        $lines = $this->collectLines($request);
        if (isset($lines['error'])) {
            return redirect()->route('payout.index', ['new' => 1])->with('not_permitted', $lines['error']);
        }
        $lookup = app(MobileMoneyHolderService::class);
        @set_time_limit(90);
        $checked = 0;
        foreach ($lines['lines'] as $index => $line) {
            if ($line['momo_name'] !== '' || $checked >= 20) {
                continue;
            }
            $checked++;
            $hit = $lookup->lookup($line['phone']);
            if ($hit && ! empty($hit['name'])) {
                $lines['lines'][$index]['momo_name'] = $hit['name'];
                if ($line['person_name'] === $line['phone']) {
                    $lines['lines'][$index]['person_name'] = $hit['name'];
                }
            }
        }
        $batch = new CampayPayoutRequest();
        $batch->cloud_tenant_id = $this->tenantId();
        $batch->requester_name = Auth::user() ? (string) Auth::user()->name : 'Payout';
        $batch->note = substr($note, 0, 180);
        $batch->status = 'pending';
        $batch->save();
        $rows = [];
        foreach ($lines['lines'] as $line) {
            $rows[] = CampayPayout::create([
                'user_id' => Auth::id(),
                'customer_id' => $line['customer_id'],
                'request_id' => $batch->id,
                'person_name' => substr($line['person_name'], 0, 191),
                'phone' => $line['phone'],
                'amount' => $line['amount'],
                'currency' => 'XAF',
                'note' => $batch->note,
                'momo_name' => $line['momo_name'] !== '' ? substr($line['momo_name'], 0, 180) : null,
                'momo_checked' => $line['momo_name'] !== '' ? 1 : 0,
                'external_reference' => 'po-'.date('YmdHis').'-'.$batch->id.'-'.substr(md5(uniqid('', true)), 0, 8),
                'status' => 'pending',
            ]);
        }

        return $this->sendRows($batch, $rows);
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

        return $this->sendRows($open, $rows);
    }

    protected function sendRows(CampayPayoutRequest $open, $rows)
    {
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

    protected function collectLines(Request $request)
    {
        $service = app(CampayPayoutService::class);
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $request->input('customer_id', [])))));
        $amounts = (array) $request->input('amount', []);
        $lines = [];
        $seen = [];
        if ($ids) {
            $customers = Customer::whereIn('id', $ids)->get(['id', 'name', 'phone_number']);
            if ($customers->count() !== count($ids)) {
                return ['error' => 'One of the selected people is not on this list.'];
            }
            foreach ($customers as $customer) {
                $phone = $service->momoNumber($customer->phone_number);
                $amount = isset($amounts[$customer->id]) ? (int) $amounts[$customer->id] : 0;
                if (! $phone) {
                    return ['error' => $customer->name.' does not have an MTN or Orange Cameroon number.'];
                }
                if ($amount < 100 || $amount > 1000000) {
                    return ['error' => 'Enter an amount from 100 to 1,000,000 XAF for '.$customer->name.'.'];
                }
                $seen[$phone] = true;
                $lines[] = [
                    'customer_id' => $customer->id,
                    'person_name' => (string) $customer->name,
                    'phone' => $phone,
                    'amount' => $amount,
                    'momo_name' => $this->postedName($request, 'customer_momo', $customer->id),
                ];
            }
        }
        $userIds = array_values(array_unique(array_filter(array_map('intval', (array) $request->input('user_id', [])))));
        $userAmounts = (array) $request->input('user_amount', []);
        if ($userIds) {
            $users = \App\User::whereIn('id', $userIds)->get(['id', 'name', 'phone', 'additional_phone']);
            if ($users->count() !== count($userIds)) {
                return ['error' => 'One of the selected people is not on this list.'];
            }
            foreach ($users as $user) {
                $phone = $service->momoNumber($user->whatsappPhone());
                $amount = isset($userAmounts[$user->id]) ? (int) $userAmounts[$user->id] : 0;
                if (! $phone) {
                    return ['error' => $user->name.' does not have an MTN or Orange Cameroon number.'];
                }
                if (isset($seen[$phone])) {
                    return ['error' => 'That number is already on the list.'];
                }
                if ($amount < 100 || $amount > 1000000) {
                    return ['error' => 'Enter an amount from 100 to 1,000,000 XAF for '.$user->name.'.'];
                }
                $seen[$phone] = true;
                $lines[] = [
                    'customer_id' => null,
                    'person_name' => (string) $user->name,
                    'phone' => $phone,
                    'amount' => $amount,
                    'momo_name' => $this->postedName($request, 'user_momo', $user->id),
                ];
            }
        }
        $extraPhones = (array) $request->input('extra_phone', []);
        $extraAmounts = (array) $request->input('extra_amount', []);
        $extraNames = (array) $request->input('extra_momo', []);
        foreach ($extraPhones as $index => $rawPhone) {
            $rawPhone = trim((string) $rawPhone);
            if ($rawPhone === '') {
                continue;
            }
            $phone = $service->momoNumber($rawPhone);
            if (! $phone) {
                return ['error' => $rawPhone.' is not an MTN or Orange Cameroon number.'];
            }
            if (isset($seen[$phone])) {
                return ['error' => 'That number is already on the list.'];
            }
            $amount = isset($extraAmounts[$index]) ? (int) $extraAmounts[$index] : 0;
            if ($amount < 100 || $amount > 1000000) {
                return ['error' => 'Enter an amount from 100 to 1,000,000 XAF for '.$phone.'.'];
            }
            $seen[$phone] = true;
            $name = isset($extraNames[$index]) ? trim((string) $extraNames[$index]) : '';
            $lines[] = [
                'customer_id' => null,
                'person_name' => $name !== '' ? $name : $phone,
                'phone' => $phone,
                'amount' => $amount,
                'momo_name' => $name,
            ];
        }
        if (count($lines) < 1 || count($lines) > 40) {
            return ['error' => 'Choose between 1 and 40 people.'];
        }

        return ['lines' => $lines];
    }

    protected function postedName(Request $request, $key, $id)
    {
        $bag = (array) $request->input($key, []);

        return isset($bag[$id]) ? trim((string) $bag[$id]) : '';
    }

    protected function customers($q)
    {
        $query = Customer::query()->where('phone_number', '!=', '')->orderBy('name');
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($rows) use ($like) {
                $rows->where('name', 'like', $like)->orWhere('phone_number', 'like', $like);
            });
        }

        return $query->limit(80)->get(['id', 'name', 'phone_number']);
    }

    public function publicForm(Request $request, $token)
    {
        $this->link($token);

        return view('payout.public', compact('token'));
    }

    public function publicSearch(Request $request, $token)
    {
        $link = $this->link($token);
        $q = trim((string) $request->get('q', ''));
        if (strlen($q) < 2) {
            return response()->json(['people' => []]);
        }

        return response()->json(['people' => $this->searchPeople($link, $q)]);
    }

    public function publicLookup(Request $request, $token)
    {
        $this->link($token);
        $service = app(CampayPayoutService::class);
        $phone = $service->momoNumber($request->get('phone'));
        if (! $phone) {
            return response()->json(['ok' => false, 'error' => 'Not an MTN or Orange Cameroon number.']);
        }
        $hit = app(MobileMoneyHolderService::class)->lookup($phone);

        return response()->json([
            'ok' => true,
            'phone' => $phone,
            'name' => ($hit && ! empty($hit['name'])) ? $hit['name'] : '',
            'operator' => $service->operatorName($phone),
        ]);
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
                    'momo_name' => $this->postedName($request, 'customer_momo', $customer->id),
                ];
            }
        }
        $userIds = array_values(array_unique(array_filter(array_map('intval', (array) $request->input('user_id', [])))));
        $userAmounts = (array) $request->input('user_amount', []);
        if ($userIds) {
            $users = \App\User::whereIn('id', $userIds)->get(['id', 'name', 'phone', 'additional_phone']);
            if ($users->count() !== count($userIds)) {
                return back()->withInput()->with('not_permitted', 'One of the selected people is not on this list.');
            }
            foreach ($users as $user) {
                $phone = $service->momoNumber($user->whatsappPhone());
                $amount = isset($userAmounts[$user->id]) ? (int) $userAmounts[$user->id] : 0;
                if (! $phone) {
                    return back()->withInput()->with('not_permitted', $user->name.' does not have an MTN or Orange Cameroon number.');
                }
                if (isset($seen[$phone])) {
                    return back()->withInput()->with('not_permitted', 'That number is already on the list.');
                }
                if ($amount < 100 || $amount > 1000000) {
                    return back()->withInput()->with('not_permitted', 'Enter an amount from 100 to 1,000,000 XAF for '.$user->name.'.');
                }
                $seen[$phone] = true;
                $lines[] = [
                    'customer_id' => null,
                    'person_name' => (string) $user->name,
                    'phone' => $phone,
                    'amount' => $amount,
                    'momo_name' => $this->postedName($request, 'user_momo', $user->id),
                ];
            }
        }
        $extraPhones = (array) $request->input('extra_phone', []);
        $extraAmounts = (array) $request->input('extra_amount', []);
        $extraNames = (array) $request->input('extra_momo', []);
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
            $name = isset($extraNames[$index]) ? trim((string) $extraNames[$index]) : '';
            $lines[] = [
                'customer_id' => $match ? $match->id : null,
                'person_name' => $match ? (string) $match->name : ($name !== '' ? $name : $phone),
                'phone' => $phone,
                'amount' => $amount,
                'momo_name' => $name,
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
                'momo_name' => $line['momo_name'] !== '' ? substr($line['momo_name'], 0, 180) : null,
                'momo_checked' => $line['momo_name'] !== '' ? 1 : 0,
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

    protected function directory($q)
    {
        $like = '%'.$q.'%';
        $customers = Customer::query()->where('phone_number', '!=', '')->orderBy('name')
            ->where(function ($rows) use ($like) {
                $rows->where('name', 'like', $like)->orWhere('phone_number', 'like', $like);
            })
            ->limit(15)
            ->get(['id', 'name', 'phone_number']);
        $users = \App\User::query()
            ->where('is_active', 1)
            ->where(function ($rows) {
                $rows->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->where(function ($rows) {
                $rows->where('phone', '!=', '')->orWhere('additional_phone', '!=', '');
            })
            ->where(function ($rows) use ($like) {
                $rows->where('name', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('additional_phone', 'like', $like);
            })
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name', 'phone', 'additional_phone']);
        $people = [];
        foreach ($customers as $customer) {
            $people[] = [
                'kind' => 'customer',
                'id' => (int) $customer->id,
                'name' => (string) $customer->name,
                'phone' => (string) $customer->phone_number,
            ];
        }
        foreach ($users as $user) {
            $phone = $user->whatsappPhone();
            if (! $phone) {
                continue;
            }
            $people[] = [
                'kind' => 'user',
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'phone' => (string) $phone,
            ];
        }

        return $people;
    }

    protected function searchPeople(CampayPayoutLink $link, $q)
    {
        $like = '%'.$q.'%';
        $customers = app(CloudTenantContext::class)->withoutIsolation(function () use ($link, $like) {
            $query = Customer::query()->where('phone_number', '!=', '')->orderBy('name');
            if ($link->cloud_tenant_id) {
                $query->where('cloud_tenant_id', $link->cloud_tenant_id);
            }
            $query->where(function ($rows) use ($like) {
                $rows->where('name', 'like', $like)->orWhere('phone_number', 'like', $like);
            });

            return $query->limit(15)->get(['id', 'name', 'phone_number']);
        });
        $users = \App\User::query()
            ->where('is_active', 1)
            ->where(function ($rows) {
                $rows->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->where(function ($rows) {
                $rows->where('phone', '!=', '')->orWhere('additional_phone', '!=', '');
            })
            ->where(function ($rows) use ($like) {
                $rows->where('name', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('additional_phone', 'like', $like);
            })
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name', 'phone', 'additional_phone']);
        $people = [];
        foreach ($customers as $customer) {
            $people[] = [
                'kind' => 'customer',
                'id' => (int) $customer->id,
                'name' => (string) $customer->name,
                'phone' => (string) $customer->phone_number,
            ];
        }
        foreach ($users as $user) {
            $phone = $user->whatsappPhone();
            if (! $phone) {
                continue;
            }
            $people[] = [
                'kind' => 'user',
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'phone' => (string) $phone,
            ];
        }

        return $people;
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

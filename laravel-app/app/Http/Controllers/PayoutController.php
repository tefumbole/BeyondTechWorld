<?php

namespace App\Http\Controllers;

use App\CampayPaymentInvite;
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

    public function requestLink(Request $request)
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
        $invites = CampayPaymentInvite::query()
            ->when($tenantId, function ($query) use ($tenantId) {
                $query->where('cloud_tenant_id', $tenantId);
            }, function ($query) {
                $query->whereNull('cloud_tenant_id');
            })
            ->orderByDesc('id')
            ->limit(40)
            ->get();
        $submitted = $this->requests($tenantId)->with('lines')->orderByDesc('id')->limit(40)->get();
        $review = null;
        $reviewLines = collect();
        $reviewId = (int) $request->get('review');
        if ($reviewId) {
            $review = $this->requests($tenantId)->where('id', $reviewId)->first();
            if ($review) {
                $reviewLines = CampayPayout::where('request_id', $review->id)->orderBy('id')->get();
            }
        }

        return view('payout.request', compact('url', 'invites', 'submitted', 'review', 'reviewLines'));
    }

    public function revise(Request $request)
    {
        $this->guard();
        $batch = $this->requests($this->tenantId())->where('id', (int) $request->input('id'))->first();
        if (! $batch) {
            abort(404);
        }
        if ($batch->status !== 'pending') {
            return redirect()->route('payout.request', ['review' => $batch->id])->with('not_permitted', 'This request can no longer be changed.');
        }
        $back = redirect()->route('payout.request', ['review' => $batch->id]);
        if ($request->input('action') === 'reject') {
            CampayPayout::where('request_id', $batch->id)->where('status', 'pending')->update([
                'status' => 'rejected',
                'error' => 'Rejected',
            ]);
            $this->closeBatch($batch);

            return redirect()->route('payout.request')->with('message', 'Request rejected.');
        }
        if ($request->input('action') === 'approve') {
            $rows = CampayPayout::where('request_id', $batch->id)->where('status', 'pending')->orderBy('id')->get();
            if ($rows->count() < 1) {
                return redirect()->route('payout.request')->with('not_permitted', 'There is no one left to pay.');
            }

            return $this->sendRows($batch, $rows, route('payout.request'));
        }
        $amounts = (array) $request->input('amounts', []);
        if ($request->input('action') === 'delete') {
            $removeIds = array_values(array_unique(array_filter(array_map('intval', (array) $request->input('remove_ids', [])))));
            if (count($removeIds) < 1) {
                return $back->with('not_permitted', 'Select the people to delete.');
            }
            foreach ($removeIds as $id) {
                unset($amounts[$id]);
            }
            $saved = $this->savePendingAmounts($batch, $amounts);
            if ($saved !== true) {
                return $back->with('not_permitted', $saved);
            }
            $deleted = CampayPayout::where('request_id', $batch->id)->whereIn('id', $removeIds)->where('status', 'pending')->delete();
            $this->closeBatch($batch);

            return $back->with('message', $deleted.' '.($deleted === 1 ? 'person' : 'people').' deleted.');
        }
        $saved = $this->savePendingAmounts($batch, $amounts);
        if ($saved !== true) {
            return $back->with('not_permitted', $saved);
        }
        $rows = CampayPayout::where('request_id', $batch->id)->where('status', 'pending')->orderBy('id')->get();
        if ($rows->count() < 1) {
            return $back->with('not_permitted', 'There is no one left to pay.');
        }

        return $this->sendRows($batch, $rows, route('payout.request', ['review' => $batch->id]));
    }

    public function resend(Request $request)
    {
        $this->guard();
        $kind = (string) $request->input('kind');
        $id = (int) $request->input('id');
        if ($kind === 'invite') {
            $row = $this->inviteRow($id);
            $url = $this->requestUrl();
            $count = 0;
            foreach ((array) $row->people as $line) {
                if (empty($line['phone']) || empty($line['name'])) {
                    continue;
                }
                $this->sendInvite($line['phone'], $line['name'], (string) $row->reason, $url);
                $count++;
            }
            $row->sent_at = now();
            $row->save();

            return redirect()->route('payout.request')->with('message', 'Resent to '.$count.' '.($count === 1 ? 'person' : 'people').'.');
        }
        if ($kind === 'submitted') {
            $batch = $this->requests($this->tenantId())->where('id', $id)->first();
            if (! $batch) {
                abort(404);
            }
            $count = 0;
            foreach ($batch->lines as $line) {
                $this->sendSubmitted($line->phone, $this->systemName($line), $line->amount, $batch->note);
                $count++;
            }

            return redirect()->route('payout.request')->with('message', 'Resent to '.$count.' '.($count === 1 ? 'person' : 'people').'.');
        }

        return redirect()->route('payout.request')->with('not_permitted', 'That request could not be sent again.');
    }

    public function invite(Request $request)
    {
        $this->guard();
        $reason = trim((string) $request->input('reason', ''));
        $people = $this->invitePeople($request);
        if (isset($people['error'])) {
            return back()->with('not_permitted', $people['error']);
        }
        $url = $this->requestUrl();
        $invite = new CampayPaymentInvite();
        $invite->cloud_tenant_id = $this->tenantId();
        $invite->reason = $reason !== '' ? substr($reason, 0, 191) : null;
        $invite->people = $people['lines'];
        $invite->sent_at = now();
        $invite->save();
        $sent = 0;
        foreach ($people['lines'] as $line) {
            $this->sendInvite($line['phone'], $line['name'], $reason, $url);
            $sent++;
        }

        return redirect()->route('payout.request')->with('message', 'Sent to '.$sent.' '.($sent === 1 ? 'person' : 'people').'.');
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

    protected function sendRows(CampayPayoutRequest $open, $rows, $returnTo = null)
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
                $this->sendPaid($row->phone, $this->systemName($row), $row->amount, $row->note);
            } else {
                $failed++;
            }
        }
        $this->closeBatch($open);

        return redirect()->to($returnTo ?: route('payout.index', ['request' => $open->id]))->with(
            'message',
            $paid.' paid ('.number_format($total, 0, '.', ' ').' XAF). '.$failed.' not paid.'
        );
    }

    protected function savePendingAmounts(CampayPayoutRequest $batch, array $amounts)
    {
        $pending = CampayPayout::where('request_id', $batch->id)->where('status', 'pending')->get();
        foreach ($pending as $line) {
            if (! array_key_exists($line->id, $amounts)) {
                continue;
            }
            $amount = (int) $amounts[$line->id];
            if ($amount < 100 || $amount > 1000000) {
                return 'Each amount must be from 100 to 1,000,000 XAF.';
            }
            $line->amount = $amount;
            if ($batch->note) {
                $line->note = $batch->note;
            }
            $line->user_id = Auth::id();
            $line->save();
        }

        return true;
    }

    protected function closeBatch(CampayPayoutRequest $batch)
    {
        $left = CampayPayout::where('request_id', $batch->id)->where('status', 'pending')->count();
        if ($left > 0) {
            return;
        }
        $paid = CampayPayout::where('request_id', $batch->id)->where('status', 'paid')->count();
        $rejected = CampayPayout::where('request_id', $batch->id)->where('status', 'rejected')->count();
        $total = CampayPayout::where('request_id', $batch->id)->count();
        if ($paid > 0) {
            $batch->status = 'done';
        } elseif ($total === 0 || $rejected === $total) {
            $batch->status = 'rejected';
        } else {
            $failed = CampayPayout::where('request_id', $batch->id)->where('status', 'failed')->count();
            $batch->status = ($failed === $total) ? 'failed' : 'done';
        }
        $batch->save();
    }

    public function retry(Request $request)
    {
        $this->guard();
        $service = app(CampayPayoutService::class);
        if ($request->filled('request_id')) {
            $batch = $this->requests($this->tenantId())->where('id', (int) $request->input('request_id'))->first();
            if (! $batch) {
                abort(404);
            }
            $rows = CampayPayout::where('request_id', $batch->id)->where('status', 'failed')->orderBy('id')->get();
        } else {
            $row = $this->retryRow((int) $request->input('id'));
            $rows = collect([$row]);
            $batch = $row->request_id ? CampayPayoutRequest::find($row->request_id) : null;
        }
        $paid = 0;
        $skipped = 0;
        $failed = 0;
        $seen = [];
        foreach ($rows as $row) {
            $phone = $service->momoNumber($row->phone);
            if (! $phone || isset($seen[$phone]) || $this->alreadyPaid($phone, $row->id)) {
                $skipped++;
                continue;
            }
            $seen[$phone] = true;
            $existing = $this->confirmExisting($service, $row);
            if ($existing === 'paid') {
                $paid++;
                continue;
            }
            if ($existing === 'pending') {
                $skipped++;
                continue;
            }
            $row->external_reference = 'po-'.date('YmdHis').'-'.$row->id.'-'.substr(md5(uniqid('', true)), 0, 8);
            $row->status = 'pending';
            $row->error = null;
            $row->campay_reference = null;
            $row->save();
            $service->pay($row);
            if ($row->status === 'paid') {
                $paid++;
                $this->sendPaid($row->phone, $this->systemName($row), $row->amount, $row->note);
            } else {
                $failed++;
            }
        }
        if ($batch) {
            $this->closeBatch($batch);
        }

        return redirect()->back()->with(
            'message',
            $paid.' paid. '.$failed.' not paid. '.$skipped.' not sent again.'
        );
    }

    protected function retryRow($id)
    {
        $row = CampayPayout::find($id);
        if (! $row) {
            abort(404);
        }
        if ($row->request_id && ! $this->requests($this->tenantId())->where('id', $row->request_id)->exists()) {
            abort(404);
        }
        if ($row->status === 'paid') {
            abort(404);
        }

        return $row;
    }

    protected function alreadyPaid($phone, $exceptId)
    {
        return CampayPayout::where('phone', $phone)
            ->where('id', '!=', $exceptId)
            ->where('status', 'paid')
            ->where('created_at', '>=', now()->subHours(12))
            ->exists();
    }

    protected function confirmExisting(CampayPayoutService $service, CampayPayout $row)
    {
        if (! $row->campay_reference) {
            return false;
        }
        $body = $service->transaction($row->campay_reference);
        $status = strtoupper((string) (is_array($body) && isset($body['status']) ? $body['status'] : ''));
        if ($status === 'SUCCESSFUL') {
            $row->status = 'paid';
            $row->error = null;
            $row->save();
            $this->sendPaid($row->phone, $this->systemName($row), $row->amount, $row->note);

            return 'paid';
        }
        if ($status === 'PENDING') {
            $row->status = 'pending';
            $row->error = 'Campay status: PENDING';
            $row->save();

            return 'pending';
        }

        return false;
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
            $campayName = isset($extraNames[$index]) ? trim((string) $extraNames[$index]) : '';
            $known = $this->nameOnFile($link, $phone, $campayName !== '' ? $campayName : $phone);
            $lines[] = [
                'customer_id' => $known['customer_id'],
                'person_name' => $known['name'],
                'phone' => $phone,
                'amount' => $amount,
                'momo_name' => $campayName,
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

        foreach ($lines as $line) {
            $this->sendSubmitted($line['phone'], $line['person_name'], $line['amount'], $batch->note);
        }
        $this->notifySubmission($requester, $batch->note, $lines);

        return redirect()->route('payout.request.form', ['token' => $token])->with('message', 'Sent. The payment is waiting for approval.');
    }

    protected function requestUrl()
    {
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

        return route('payout.request.form', ['token' => $link->token]);
    }

    protected function invitePeople(Request $request)
    {
        $service = app(CampayPayoutService::class);
        $lines = [];
        $seen = [];
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $request->input('customer_id', [])))));
        if ($ids) {
            $customers = Customer::whereIn('id', $ids)->get(['id', 'name', 'phone_number']);
            foreach ($customers as $customer) {
                $phone = $service->momoNumber($customer->phone_number);
                if (! $phone || isset($seen[$phone])) {
                    continue;
                }
                $seen[$phone] = true;
                $lines[] = ['name' => (string) $customer->name, 'phone' => $phone];
            }
        }
        $userIds = array_values(array_unique(array_filter(array_map('intval', (array) $request->input('user_id', [])))));
        if ($userIds) {
            $users = \App\User::whereIn('id', $userIds)->get(['id', 'name', 'phone', 'additional_phone']);
            foreach ($users as $user) {
                $phone = $service->momoNumber($user->whatsappPhone());
                if (! $phone || isset($seen[$phone])) {
                    continue;
                }
                $seen[$phone] = true;
                $lines[] = ['name' => (string) $user->name, 'phone' => $phone];
            }
        }
        foreach ((array) $request->input('extra_phone', []) as $raw) {
            $phone = $service->momoNumber($raw);
            if (! $phone || isset($seen[$phone])) {
                continue;
            }
            $seen[$phone] = true;
            $tail = substr($phone, -9);
            $match = \App\User::query()->where('is_active', 1)
                ->where(function ($query) use ($tail) {
                    $query->where('phone', 'like', '%'.$tail)->orWhere('additional_phone', 'like', '%'.$tail);
                })
                ->limit(10)
                ->get(['id', 'name', 'phone', 'additional_phone'])
                ->first(function ($user) use ($service, $phone) {
                    return $service->momoNumber($user->whatsappPhone()) === $phone;
                });
            $customer = Customer::query()->where('phone_number', 'like', '%'.$tail)->limit(10)->get(['id', 'name', 'phone_number'])
                ->first(function ($row) use ($service, $phone) {
                    return $service->momoNumber($row->phone_number) === $phone;
                });
            $name = $customer ? (string) $customer->name : ($match ? (string) $match->name : $phone);
            $lines[] = ['name' => $name, 'phone' => $phone];
        }
        if (count($lines) < 1) {
            return ['error' => 'Choose at least one person.'];
        }
        if (count($lines) > 40) {
            return ['error' => 'Choose up to 40 people.'];
        }

        return ['lines' => $lines];
    }

    protected function systemName(CampayPayout $row)
    {
        if ($row->customer_id) {
            $customer = Customer::find($row->customer_id);
            if ($customer && trim((string) $customer->name) !== '') {
                return trim((string) $customer->name);
            }
        }
        $name = trim((string) $row->person_name);

        return $name !== '' ? $name : 'Client';
    }

    protected function moneyText($amount)
    {
        return number_format((float) $amount, 0, '.', ' ');
    }

    protected function sendInvite($phone, $name, $reason, $url)
    {
        $reason = trim((string) $reason);
        $result = app(\App\Services\Messaging\TwilioTemplateSender::class)->sendSharedAction(
            $phone,
            $name,
            \App\Support\WhatsAppMessage::companyName(),
            'submit payment information',
            $reason !== '' ? $reason : 'Request for Payment',
            $url
        );
        if (empty($result['success'])) {
            app(\App\Services\ClientNoticeService::class)->send($phone, $this->inviteText($name, $reason, $url));
        }
    }

    protected function sendSubmitted($phone, $name, $amount, $reason)
    {
        $reason = trim((string) $reason);
        $result = app(\App\Services\Messaging\TwilioTemplateSender::class)->sendSharedStatus(
            $phone,
            $name,
            \App\Support\WhatsAppMessage::companyName(),
            'payment',
            $reason !== '' ? $reason : 'Payment request',
            'Submitted',
            'Your name has been submitted for a payment of '.$this->moneyText($amount).' XAF.'
        );
        if (empty($result['success'])) {
            app(\App\Services\ClientNoticeService::class)->send($phone, $this->submittedText($name, $amount, $reason));
        }
    }

    protected function sendPaid($phone, $name, $amount, $reason)
    {
        $reason = trim((string) $reason);
        if (strcasecmp($reason, 'Payout') === 0) {
            $reason = '';
        }
        $result = app(\App\Services\Messaging\TwilioTemplateSender::class)->sendSharedConfirmation(
            $phone,
            $name,
            \App\Support\WhatsAppMessage::companyName(),
            'payment',
            $reason !== '' ? $reason : 'Payment',
            date('d M Y'),
            'A payment has been made to you.',
            $this->moneyText($amount).' XAF'
        );
        if (empty($result['success'])) {
            app(\App\Services\ClientNoticeService::class)->send($phone, $this->paidText($name, $amount, $reason));
        }
    }

    protected function notifySubmission($requester, $reason, array $lines)
    {
        $parts = [];
        $total = 0;
        foreach ($lines as $line) {
            $total += (int) $line['amount'];
            $parts[] = $line['person_name'].' '.$this->moneyText($line['amount']).' XAF';
        }
        $detail = $requester.' submitted a payment request. '.implode('. ', $parts).'. Total '.$this->moneyText($total).' XAF.';
        $reason = trim((string) $reason);
        if ($reason !== '') {
            $detail .= ' Reason: '.$reason.'.';
        }
        if (function_exists('mb_strlen') && mb_strlen($detail) > 800) {
            $detail = rtrim(mb_substr($detail, 0, 799)).'…';
        }
        $phone = \App\Support\TwilioAdminCopy::PHONE;
        $result = app(\App\Services\Messaging\TwilioTemplateSender::class)->sendSharedStatus(
            $phone,
            $this->copyName(),
            \App\Support\WhatsAppMessage::companyName(),
            'payment request',
            $requester,
            'Submitted',
            $detail
        );
        if (empty($result['success'])) {
            app(\App\Services\ClientNoticeService::class)->send($phone, $detail);
        }
    }

    protected function copyName()
    {
        $tail = substr(preg_replace('/\D/', '', \App\Support\TwilioAdminCopy::PHONE), -9);
        $user = \App\User::query()->where('is_active', 1)
            ->where(function ($query) use ($tail) {
                $query->where('phone', 'like', '%'.$tail)->orWhere('additional_phone', 'like', '%'.$tail);
            })
            ->first(['name', 'phone', 'additional_phone']);
        $name = $user ? trim((string) $user->name) : '';

        return $name !== '' ? $name : 'Admin';
    }

    protected function paidText($name, $amount, $reason)
    {
        $msg = \App\Support\WhatsAppMessage::statusBlock('✅', 'Payment Sent');
        $msg .= 'Dear *'.$name.'*,'."\n\n";
        $msg .= 'A payment of *'.$this->moneyText($amount)."* XAF has been made to you.\n";
        $reason = trim((string) $reason);
        if ($reason !== '' && strcasecmp($reason, 'Payout') !== 0) {
            $msg .= "\n".\App\Support\WhatsAppMessage::bullet('Reason', $reason);
        }
        $msg .= \App\Support\WhatsAppMessage::footer();

        return $msg;
    }

    protected function submittedText($name, $amount, $reason)
    {
        $msg = \App\Support\WhatsAppMessage::statusBlock('📨', 'Payment Submitted');
        $msg .= 'Dear *'.$name.'*,'."\n\n";
        $msg .= 'Your name has been submitted for a payment of *'.$this->moneyText($amount)."* XAF.\n";
        $reason = trim((string) $reason);
        if ($reason !== '') {
            $msg .= "\n".\App\Support\WhatsAppMessage::bullet('Reason', $reason);
        }
        $msg .= \App\Support\WhatsAppMessage::footer();

        return $msg;
    }

    protected function inviteText($name, $reason, $url)
    {
        $msg = \App\Support\WhatsAppMessage::statusBlock('📋', 'Request for Payment');
        $msg .= 'Dear *'.$name.'*,'."\n\n";
        $msg .= "You have been requested to submit payment information.\n";
        $reason = trim((string) $reason);
        if ($reason !== '') {
            $msg .= "\n".\App\Support\WhatsAppMessage::bullet('Reason', $reason);
        }
        $msg .= \App\Support\WhatsAppMessage::actionLink('Submit payment information', $url);
        $msg .= \App\Support\WhatsAppMessage::footer();

        return $msg;
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

    protected function nameOnFile(CampayPayoutLink $link, $phone, $fallback)
    {
        $customer = $this->customerByPhone($link, $phone);
        if ($customer) {
            return ['customer_id' => $customer->id, 'name' => (string) $customer->name];
        }
        $service = app(CampayPayoutService::class);
        $tail = substr($phone, -9);
        $user = \App\User::query()->where('is_active', 1)
            ->where(function ($query) use ($tail) {
                $query->where('phone', 'like', '%'.$tail)->orWhere('additional_phone', 'like', '%'.$tail);
            })
            ->limit(10)
            ->get(['id', 'name', 'phone', 'additional_phone'])
            ->first(function ($row) use ($service, $phone) {
                return $service->momoNumber($row->whatsappPhone()) === $phone;
            });
        if ($user) {
            return ['customer_id' => null, 'name' => (string) $user->name];
        }

        return ['customer_id' => null, 'name' => $fallback];
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

    protected function inviteRow($id)
    {
        $tenantId = $this->tenantId();
        $query = CampayPaymentInvite::query()->where('id', (int) $id);
        if ($tenantId) {
            $query->where('cloud_tenant_id', $tenantId);
        } else {
            $query->whereNull('cloud_tenant_id');
        }
        $row = $query->first();
        if (! $row) {
            abort(404);
        }

        return $row;
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

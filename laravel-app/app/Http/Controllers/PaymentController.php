<?php

namespace App\Http\Controllers;

use App\Account;
use App\CampayPayout;
use App\Customer;
use App\Deposit;
use App\PublicDonation;
use App\Sale;
use App\Services\AccountDepositService;
use App\Services\CampayPayoutService;
use App\Services\ClientNoticeService;
use App\Services\StripeCheckoutService;
use App\Services\MobileMoneyHolderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class PaymentController extends Controller
{
    public function momoPayments()
    {
        $rows = collect();
        $seen = [];
        foreach ($this->campayDeposits()->with('customer')->orderByDesc('id')->get() as $deposit) {
            $ref = trim((string) $deposit->campay_reference);
            if ($ref !== '') {
                $seen[$ref] = true;
            }
            $paid = (int) $deposit->status === 1;
            $waiting = (int) $deposit->status === 0;
            $rows->push([
                'at' => $deposit->created_at,
                'kind' => 'Deposit',
                'person' => optional($deposit->customer)->name ?: '—',
                'amount' => (int) round((float) $deposit->amount),
                'status' => $paid ? 'Paid' : ($waiting ? 'Pending' : 'Not paid'),
                'tone' => $paid ? 'ok' : ($waiting ? 'wait' : 'no'),
                'note' => (string) $deposit->note,
            ]);
        }
        foreach ($this->paidCampayDonations() as $donation) {
            $ref = trim((string) $donation->campay_reference);
            if ($ref !== '' && isset($seen[$ref])) {
                continue;
            }
            $rows->push([
                'at' => $donation->created_at,
                'kind' => 'Deposit',
                'person' => trim((string) $donation->person_name) !== '' ? $donation->person_name : '—',
                'amount' => (int) round((float) $donation->amount),
                'status' => 'Paid',
                'tone' => 'ok',
                'note' => (string) $donation->note,
            ]);
        }
        foreach (CampayPayout::orderByDesc('id')->get() as $payout) {
            $paid = $payout->status === 'paid';
            $waiting = $payout->status === 'pending';
            $rows->push([
                'at' => $payout->created_at,
                'kind' => 'Withdrawal',
                'person' => $payout->person_name ?: '—',
                'amount' => (int) $payout->amount,
                'status' => $paid ? 'Paid' : ($waiting ? 'Pending' : 'Not paid'),
                'tone' => $paid ? 'ok' : ($waiting ? 'wait' : 'no'),
                'note' => (string) $payout->note,
            ]);
        }
        $rows = $rows->sortByDesc(function ($row) {
            return $row['at'] ? $row['at']->timestamp : 0;
        })->values();

        return view('payment.momo', compact('rows'));
    }

    public function index()
    {
        $sales = $this->unpaidSales()->limit(250)->get();
        $lims_account_list = Account::with('departments')->orderByDesc('id')->get();

        return view('payment.customer-data', compact('sales', 'lims_account_list'));
    }

    public function AwaitingPayments($id)
    {
        $sales = $this->unpaidSales()->where('customer_id', $id)->limit(250)->get();
        $lims_account_list = Account::with('departments')->orderByDesc('id')->get();

        return view('payment.customer-data', compact('sales', 'lims_account_list'));
    }

    public function deleteSelection(Request $request)
    {
        if (! Auth::check() || ! in_array((int) Auth::user()->role_id, [1, 2], true)) {
            abort(403);
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $request->input('ids', [])))));
        if (count($ids) < 1) {
            return redirect()->route('payment.index')->with('not_permitted', 'Select the payments to delete.');
        }
        $allowed = Sale::whereIn('id', $ids)->where('payment_status', '!=', 4)->pluck('id')->all();
        if (count($allowed) < 1) {
            return redirect()->route('payment.index')->with('not_permitted', 'Paid sales stay on the sales list.');
        }
        $request->merge(['saleIdArray' => $allowed]);
        app(SaleController::class)->deleteBySelection($request);

        return redirect()->route('payment.index')->with('message', count($allowed).' '.(count($allowed) === 1 ? 'payment was' : 'payments were').' deleted.');
    }

    public function requestSelection(Request $request)
    {
        if (! Auth::check() || ! in_array((int) Auth::user()->role_id, [1, 2], true)) {
            abort(403);
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $request->input('ids', [])))));
        if (count($ids) < 1) {
            return redirect()->route('payment.index')->with('not_permitted', 'Select the payments to request.');
        }
        if (count($ids) > 30) {
            return redirect()->route('payment.index')->with('not_permitted', 'Select up to 30 payments at a time.');
        }
        $result = app(DocumentPayController::class)->requestMany($ids);
        $sent = (int) $result['sent'];
        $skipped = (int) $result['skipped'];
        if ($sent < 1) {
            return redirect()->route('payment.index')->with('not_permitted', 'Nothing was sent. Each sale needs a phone number and at least 100 XAF still due.');
        }
        $message = $sent.' payment '.( $sent === 1 ? 'request was' : 'requests were').' sent.';
        if ($skipped > 0) {
            $message .= ' '.$skipped.' skipped.';
        }

        return redirect()->route('payment.index')->with('message', $message);
    }

    protected function unpaidSales()
    {
        return Sale::with('customer')->select('id', 'reference_no', 'customer_id', 'grand_total', 'paid_amount', 'payment_status', 'created_at')
            ->where('payment_status', '!=', 4)
            ->whereRaw('(COALESCE(grand_total, 0) - COALESCE(paid_amount, 0)) > 0.5')
            ->orderByDesc('id');
    }

    public function Desposit() {
        $this->settleConfirmedMoneySoon();
        $models = $this->campayDeposits()->with(['customer', 'customerGroup', 'user', 'depositor', 'account'])->orderByDesc('id')->get();
        $paidAmounts = $this->paymentPaidAmounts($models);
        $credits = $this->campayCredits();
        $deposits = [];
        $seen = [];
        foreach ($models as $deposit) {
            $ref = trim((string) $deposit->campay_reference);
            if ($ref !== '') {
                $seen[$ref] = true;
            }
            $received = (float) $deposit->amount;
            $paid = isset($paidAmounts[(int) $deposit->id]) ? (float) $paidAmounts[(int) $deposit->id] : $received;
            if ($paid < $received) {
                $paid = $received;
            }
            $tone = (int) $deposit->status === 1 ? 'ok' : ((int) $deposit->status === 0 ? 'wait' : 'no');
            $statusLabel = (int) $deposit->status === 1 ? 'Paid' : ((int) $deposit->status === 0 ? 'Waiting' : 'Not paid');
            $deposits[] = [
                'id' => (int) $deposit->id,
                'sort' => $deposit->created_at ? $deposit->created_at->timestamp : 0,
                'time' => $deposit->created_at ? $deposit->created_at->format('M j H:i') : '',
                'client' => optional($deposit->customer)->name ?: '—',
                'paid' => $paid,
                'received' => $received,
                'tone' => $tone,
                'status' => $statusLabel,
                'reason' => $this->depositReason($deposit->failure_reason),
                'note' => (string) $deposit->note,
            ];
        }
        foreach ($this->paidCampayDonations() as $donation) {
            $ref = trim((string) $donation->campay_reference);
            if ($ref !== '' && isset($seen[$ref])) {
                continue;
            }
            $paid = (float) $donation->amount;
            $received = ($ref !== '' && isset($credits[$ref])) ? (float) $credits[$ref] : $paid;
            if ($received < 1 || $received > $paid) {
                $received = $paid;
            }
            $deposits[] = [
                'id' => 0,
                'sort' => $donation->created_at ? $donation->created_at->timestamp : 0,
                'time' => $donation->created_at ? $donation->created_at->format('M j H:i') : '',
                'client' => trim((string) $donation->person_name) !== '' ? $donation->person_name : '—',
                'paid' => $paid,
                'received' => $received,
                'tone' => 'ok',
                'status' => 'Paid',
                'reason' => '',
                'note' => (string) $donation->note,
            ];
        }
        usort($deposits, function ($a, $b) {
            if ($a['sort'] === $b['sort']) {
                return 0;
            }

            return $a['sort'] < $b['sort'] ? 1 : -1;
        });
        $accounts = Account::where('is_active', true)->orderBy('name')->get();
        $making = request()->get('new') === '1';

        return view('payment.deposits', compact('deposits', 'accounts', 'making'));
    }

    public function depositLive()
    {
        $cached = Cache::get('btw.deposit.live');
        if (is_array($cached)) {
            return response()->json($cached);
        }
        $this->settleConfirmedMoneySoon();
        $total = (string) Cache::get('btw.campay.wallet_label', '');
        if ($total === '') {
            try {
                $total = (string) app(CampayPayoutService::class)->appBalance();
            } catch (\Throwable $e) {
                $total = '';
            }
            if ($total !== '') {
                Cache::put('btw.campay.wallet_label', $total, now()->addSeconds(90));
            }
        }
        $rows = [];
        $pending = false;
        foreach ($this->campayDeposits()->orderByDesc('id')->limit(80)->get(['id', 'status']) as $row) {
            $state = (int) $row->status === 1 ? 'Paid' : ((int) $row->status === 2 ? 'Not paid' : 'Waiting');
            if ($state === 'Waiting') {
                $pending = true;
            }
            $rows[] = [
                'id' => (int) $row->id,
                'status' => $state,
                'tone' => $state === 'Paid' ? 'ok' : ($state === 'Not paid' ? 'no' : 'wait'),
            ];
        }

        $payload = [
            'total' => $total,
            'balance' => $total,
            'pending' => $pending,
            'rows' => $rows,
        ];
        Cache::put('btw.deposit.live', $payload, now()->addSeconds(20));

        return response()->json($payload);
    }

    protected function campayDeposits()
    {
        return Deposit::where('payment_method', 3);
    }

    protected function paidCampayDonations()
    {
        if (! Schema::hasTable('public_donations')) {
            return collect();
        }

        return PublicDonation::where('method', 'momo')->where('status', 'paid')->orderByDesc('id')->get();
    }

    protected function campayCredits()
    {
        $credits = [];
        try {
            $items = Cache::get('btw.campay.collects');
            if (! is_array($items)) {
                $items = app(CampayPayoutService::class)->confirmedCollections(date('Y-m-d', strtotime('-45 days')), date('Y-m-d'));
            }
        } catch (\Throwable $e) {
            return $credits;
        }
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $ref = trim((string) (isset($item['reference_uuid']) ? $item['reference_uuid'] : ''));
            if ($ref === '') {
                continue;
            }
            $credits[$ref] = (float) (isset($item['credit']) ? $item['credit'] : 0);
        }

        return $credits;
    }

    protected function depositReason($reason)
    {
        $reason = strtoupper((string) $reason);
        if ($reason === '') {
            return '';
        }
        if (strpos($reason, 'LOW_BALANCE') !== false || strpos($reason, 'INSUFFICIENT') !== false) {
            return 'Not enough money on the phone';
        }
        if (strpos($reason, 'CANCEL') !== false || strpos($reason, 'APPROV') !== false || strpos($reason, 'DENIED') !== false || strpos($reason, 'REJECT') !== false) {
            return 'Not approved';
        }

        return 'Payment was not completed';
    }

    protected function settleConfirmedMoneySoon()
    {
        if (! Cache::has('btw.campay.settled')) {
            $this->settleWaitingDeposits();
            Cache::put('btw.campay.settled', 1, now()->addSeconds(30));
        }
        $this->importConfirmedCollections();
        $this->applySaleCollections();
        $this->notifyUnsentDeposits();
    }

    public function settleConfirmedMoney()
    {
        $this->settleWaitingDeposits();
        $this->importConfirmedCollections();
        $this->applySaleCollections();
    }

    public function applyConfirmedSalePayments()
    {
        $this->applySaleCollections();
    }

    public function importConfirmedCollections()
    {
        if (Cache::has('btw.campay.imported')) {
            return;
        }
        if (! Schema::hasTable('deposits') || ! Schema::hasColumn('deposits', 'campay_reference')) {
            return;
        }
        try {
            $items = app(CampayPayoutService::class)->confirmedCollections(date('Y-m-d', strtotime('-45 days')), date('Y-m-d'));
        } catch (\Throwable $e) {
            return;
        }
        Cache::put('btw.campay.collects', $items, now()->addMinutes(15));
        $accountId = Account::where('is_active', true)->value('id');
        if (! $accountId) {
            return;
        }
        foreach ($items as $item) {
            $reference = trim((string) (isset($item['reference_uuid']) ? $item['reference_uuid'] : ''));
            if ($reference === '' || Deposit::where('campay_reference', $reference)->exists()) {
                continue;
            }
            if ($this->collectionAlreadyRecorded($reference)) {
                continue;
            }
            $credit = (int) round((float) $item['credit']);
            if ($credit < 1) {
                continue;
            }
            $phone = isset($item['phone_number']) ? (string) $item['phone_number'] : '';
            $customer = $this->customerMatchingPhone($phone);
            $when = date('Y-m-d H:i:s');
            if (! empty($item['datetime'])) {
                $stamp = strtotime((string) $item['datetime']);
                if ($stamp) {
                    $when = date('Y-m-d H:i:s', $stamp);
                }
            }
            $note = trim((string) (isset($item['description']) ? $item['description'] : ''));
            if ($note === '' || strcasecmp($note, 'None') === 0) {
                $note = 'Campay confirmed';
            }
            $deposit = Deposit::create([
                'amount' => $credit,
                'customer_id' => $customer ? $customer->id : null,
                'user_id' => Auth::id() ?: 1,
                'account_id' => $accountId,
                'mtn_number' => $customer ? $customer->phone_number : $phone,
                'payment_method' => 3,
                'status' => 1,
                'paid_notice' => 0,
                'note' => substr($note, 0, 180),
                'campay_reference' => $reference,
                'created_at' => $when,
                'updated_at' => $when,
            ]);
            $this->rememberCollectionFee($deposit, $item);
            $this->applyDepositToSale($deposit);
            if ($this->depositIsClientMoney($note)) {
                $this->sendDepositNotice($deposit);
            }
        }
        Cache::put('btw.campay.imported', 1, now()->addMinutes(15));
    }

    protected function collectionAlreadyRecorded($reference)
    {
        if (Schema::hasTable('document_payment_links') && \App\DocumentPaymentLink::where('campay_reference', $reference)->exists()) {
            return true;
        }
        if (Schema::hasTable('public_donations') && \App\PublicDonation::where('campay_reference', $reference)->exists()) {
            return true;
        }

        return false;
    }

    protected function customerMatchingPhone($raw)
    {
        $phone = app(CampayPayoutService::class)->momoNumber($raw);
        if (! $phone) {
            return null;
        }
        $tail = substr($phone, -9);

        return Customer::where(function ($query) use ($phone, $tail) {
            $query->where('phone_number', $phone)->orWhere('phone_number', 'like', '%'.$tail);
        })->first();
    }

    protected function paymentPaidAmounts($deposits)
    {
        $byRef = [];
        try {
            $items = Cache::remember('btw.campay.collects', 90, function () {
                return app(CampayPayoutService::class)->confirmedCollections(
                    date('Y-m-d', strtotime('-45 days')),
                    date('Y-m-d')
                );
            });
            foreach ($items as $item) {
                $ref = trim((string) (isset($item['reference_uuid']) ? $item['reference_uuid'] : ''));
                if ($ref === '') {
                    continue;
                }
                $credit = (float) (isset($item['credit']) ? $item['credit'] : 0);
                $charge = (float) (isset($item['charge']) ? $item['charge'] : 0);
                $amount = (float) (isset($item['amount']) ? $item['amount'] : 0);
                $gross = $credit + max(0, $charge);
                if ($amount > $gross) {
                    $gross = $amount;
                }
                $byRef[$ref] = $gross;
            }
        } catch (\Throwable $e) {
            $byRef = [];
        }
        $paid = [];
        foreach ($deposits as $deposit) {
            $received = (float) $deposit->amount;
            $ref = trim((string) $deposit->campay_reference);
            $gross = ($ref !== '' && isset($byRef[$ref])) ? (float) $byRef[$ref] : $received;
            if ($gross < $received) {
                $gross = $received;
            }
            $paid[(int) $deposit->id] = $gross;
        }

        return $paid;
    }

    public function depositDeleteSelection(Request $request)
    {
        if (! Auth::check() || ! in_array((int) Auth::user()->role_id, [1, 2], true)) {
            abort(403);
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $request->input('ids', [])))));
        if (count($ids) < 1) {
            return redirect()->route('deposit.index')->with('not_permitted', 'Select the deposits to delete.');
        }
        $deleted = Deposit::whereIn('id', $ids)->delete();

        return redirect()->route('deposit.index')->with('message', $deleted.' '.($deleted === 1 ? 'deposit was' : 'deposits were').' deleted.');
    }

    public function searchCustomers(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        if ($q === '') {
            return response()->json(['people' => []]);
        }
        $like = '%'.$q.'%';
        $rows = Customer::query()
            ->where('phone_number', '!=', '')
            ->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)->orWhere('phone_number', 'like', $like);
            })
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name', 'phone_number']);
        $people = [];
        foreach ($rows as $row) {
            $people[] = [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'phone' => (string) $row->phone_number,
            ];
        }

        return response()->json(['people' => $people]);
    }

    public function phoneLookup(Request $request)
    {
        $campay = app(CampayPayoutService::class);
        $phone = $campay->momoNumber($request->get('phone'));
        if (! $phone) {
            return response()->json(['ok' => false, 'error' => 'Not an MTN or Orange Cameroon number.']);
        }
        $hit = app(MobileMoneyHolderService::class)->lookup($phone, true);

        return response()->json([
            'ok' => true,
            'phone' => $phone,
            'name' => ($hit && ! empty($hit['name'])) ? $hit['name'] : '',
            'operator' => $campay->operatorName($phone),
            'source' => ($hit && ! empty($hit['source'])) ? $hit['source'] : null,
        ]);
    }

    public function clientCheck(Request $request)
    {
        $customer = Customer::find((int) $request->get('customer_id'));
        if (! $customer) {
            return response()->json(['ok' => false]);
        }
        $campay = app(CampayPayoutService::class);
        $phone = $campay->momoNumber($customer->phone_number);
        $lookup = $phone ? app(MobileMoneyHolderService::class)->lookup($phone, true) : null;
        $momoName = is_array($lookup) && ! empty($lookup['name']) ? (string) $lookup['name'] : '';
        $last = $phone
            ? Deposit::where('mtn_number', $phone)->where('status', 2)->orderByDesc('id')->first()
            : null;
        $low = $last && strpos(strtoupper((string) $last->failure_reason), 'LOW_BALANCE') !== false;
        $recent = $low && $last->created_at && $last->created_at->gt(now()->subMinutes(20));

        return response()->json([
            'ok' => true,
            'phone' => $phone ? $phone : (string) $customer->phone_number,
            'operator' => $phone ? $campay->operatorName($phone) : '',
            'momo_name' => $momoName,
            'balance' => null,
            'balance_note' => 'Campay does not show the money on this phone until the client approves or refuses.',
            'warning' => $low ? 'The last request of '.$this->money($last->amount).' XAF failed because this number did not have enough money.' : '',
            'block_amount' => $recent ? (int) $last->amount : 0,
        ]);
    }

    public function depositStatus($id)
    {
        $deposit = Deposit::findOrFail($id);
        if ((int) $deposit->status === 0 && $deposit->campay_reference) {
            $this->settleOne($deposit);
            $deposit = $deposit->fresh();
        }
        $state = (int) $deposit->status === 1 ? 'paid' : ((int) $deposit->status === 2 ? 'failed' : 'pending');

        return response()->json([
            'status' => $state,
            'message' => $state === 'paid'
                ? 'The client approved '.$this->money($deposit->amount).' XAF.'
                : ($state === 'failed'
                    ? $this->failureText($deposit->failure_reason, $deposit->amount)
                    : 'Waiting for the client to approve on their phone.'),
        ]);
    }

    public function storeDeposit(Request $request, AccountDepositService $deposits)
    {
        if ($request->input('pay_mode') === 'split') {
            return $this->storeSplitDeposits($request);
        }
        $data = $request->validate([
            'account_id' => 'required|integer|exists:accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:1,3,4',
            'note' => 'nullable|string|max:1000',
            'customer_id' => 'nullable|integer',
            'client_phone' => 'nullable|string|max:30',
        ]);
        $account = Account::where('is_active', true)->findOrFail($data['account_id']);
        $method = (int) $data['payment_method'];
        $note = isset($data['note']) ? $data['note'] : null;
        $customer = null;
        if (! empty($data['customer_id'])) {
            $customer = Customer::find($data['customer_id']);
        }
        if (! $customer && ! empty($data['client_phone'])) {
            $customer = $this->customerFromPhone($data['client_phone']);
        }
        if ($method === 1) {
            $deposit = $deposits->record($account, $data['amount'], $method, $note, Auth::id());
            if ($customer) {
                $deposit->customer_id = $customer->id;
                $deposit->mtn_number = $customer->phone_number;
                $deposit->save();
                $this->sendDepositNotice($deposit);
            }

            return redirect()->route('deposit.index')->with('message', 'Deposit of '.$this->money($deposit->amount).' XAF saved to '.$account->name.'.');
        }

        if (! $customer) {
            return $this->depositProblem($request, 'Choose the client who is paying.');
        }
        $campay = app(CampayPayoutService::class);
        $phone = $campay->momoNumber($customer->phone_number);
        if ($method === 3 && ! $phone) {
            return $this->depositProblem($request, $customer->name.' does not have an MTN or Orange Cameroon number.');
        }
        if (! $phone) {
            $digits = preg_replace('/\D/', '', (string) $customer->phone_number);
            if ($digits === '') {
                return $this->depositProblem($request, $customer->name.' does not have a phone number.');
            }
            $phone = $digits;
        }
        $amount = (int) round((float) $data['amount']);
        if ($amount < 100 || abs((float) $data['amount'] - $amount) > 0.001) {
            return $this->depositProblem($request, 'Enter a whole amount of at least 100 XAF.');
        }
        if ($method === 3 && $phone) {
            $recentLow = Deposit::where('mtn_number', $phone)->where('status', 2)->where('created_at', '>=', now()->subMinutes(20))->orderByDesc('id')->first();
            if ($recentLow && strpos(strtoupper((string) $recentLow->failure_reason), 'LOW_BALANCE') !== false && $amount >= (int) $recentLow->amount) {
                return $this->depositProblem($request, 'This number could not pay '.$this->money($recentLow->amount).' XAF a few minutes ago. Use a smaller amount, or wait until the phone has enough money.');
            }
        }
        $reference = 'dep-'.date('YmdHis').'-'.substr(md5(uniqid('', true)), 0, 8);
        $deposit = Deposit::create([
            'amount' => $amount,
            'customer_id' => $customer->id,
            'user_id' => Auth::id(),
            'depositor_id' => Auth::id(),
            'account_id' => $account->id,
            'note' => $note !== null && $note !== '' ? $note : null,
            'payment_method' => $method,
            'payment_reference' => $reference,
            'mtn_number' => $phone,
            'status' => 0,
            'paid_notice' => 0,
        ]);
        $description = $note !== null && trim((string) $note) !== '' ? substr(trim((string) $note), 0, 80) : 'Deposit';
        if ($method === 3) {
            $body = $campay->collect($phone, $amount, $reference, $description);
            $campayStatus = strtoupper((string) (is_array($body) && isset($body['status']) ? $body['status'] : ''));
            if (! is_array($body) || empty($body['reference']) || $campayStatus === 'FAILED') {
                $reason = is_array($body) && ! empty($body['reason']) ? (string) $body['reason'] : $campayStatus;
                $text = $reason !== '' ? $this->failureText($reason, $amount) : $this->campayError($body, 'Campay could not ask the client to approve.');
                if ($reason !== '') {
                    $this->tellClient($phone, $text);
                }
                $deposit->status = 2;
                $deposit->failure_reason = $reason !== '' ? substr($reason, 0, 180) : null;
                $deposit->campay_reference = is_array($body) && ! empty($body['reference']) ? (string) $body['reference'] : null;
                $deposit->save();

                return $this->depositProblem($request, $text);
            }
            $deposit->campay_reference = (string) $body['reference'];
            $deposit->save();
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['ok' => true, 'id' => $deposit->id, 'status' => 'pending']);
            }

            return redirect()->route('deposit.index', ['new' => 1, 'wait' => $deposit->id]);
        }

        $back = route('deposit.index');
        $card = app(StripeCheckoutService::class)->checkout(
            'Deposit',
            $amount,
            $back.'?session_id={CHECKOUT_SESSION_ID}',
            $back,
            ['deposit' => (string) $deposit->id]
        );
        if (empty($card['ok'])) {
            $deposit->delete();

            return $this->depositProblem($request, isset($card['error']) ? $card['error'] : 'VISA could not be opened.');
        }
        $deposit->campay_reference = (string) $card['id'];
        $deposit->payment_link = (string) $card['url'];
        $deposit->save();
        $channel = $this->tellClient($phone, 'Open this link and enter your card details to pay '.$this->money($amount).' XAF: '.$card['url']);

        return redirect()->route('deposit.index')->with('message', $channel === 'none'
            ? 'The card link was created, but the client could not be notified.'
            : 'A card link was sent to '.$customer->name.'.');
    }

    protected function settleWaitingDeposits()
    {
        $waiting = Deposit::where('status', 0)->whereNotNull('campay_reference')->orderBy('id')->limit(8)->get();
        foreach ($waiting as $deposit) {
            $this->settleOne($deposit);
        }
    }

    protected function settleOne(Deposit $deposit)
    {
        if ((int) $deposit->status !== 0 || ! $deposit->campay_reference) {
            return;
        }
        if (strpos((string) $deposit->campay_reference, 'cs_') === 0) {
            if (app(StripeCheckoutService::class)->isPaid($deposit->campay_reference)) {
                $paid = app(AccountDepositService::class)->creditPending($deposit);
                if ($paid) {
                    \App\Gbhs\GbhsDonate::noteDepositPaid($paid);
                }
                if ($paid) {
                    $this->sendDepositNotice($paid);
                }
            }

            return;
        }
        $outcome = app(CampayPayoutService::class)->collectionOutcome(
            $deposit->campay_reference,
            $deposit->amount,
            $deposit->mtn_number
        );
        if ($outcome['state'] === 'paid' && ! empty($outcome['confirmed'])) {
            $bill = ! empty($outcome['bill']) ? (int) $outcome['bill'] : (int) $deposit->amount;
            if ($bill > 0 && $bill < (int) round((float) $deposit->amount)) {
                $deposit->amount = $bill;
                $deposit->save();
            }
            if (! empty($outcome['fee'])) {
                Cache::put('deposit-fee:'.$deposit->id, [
                    'fee' => (int) $outcome['fee'],
                    'operator' => isset($outcome['operator']) ? (string) $outcome['operator'] : '',
                ], now()->addDays(2));
            }
            $paid = app(AccountDepositService::class)->creditPending($deposit);
            if ($paid) {
                \App\Gbhs\GbhsDonate::noteDepositPaid($paid);
            }
            if ($paid) {
                $this->sendDepositNotice($paid);
            }
        } elseif ($outcome['state'] === 'failed' && ! empty($outcome['confirmed'])) {
            $reason = isset($outcome['reason']) ? substr((string) $outcome['reason'], 0, 180) : 'FAILED';
            $updated = Deposit::where('id', $deposit->id)->where('status', 0)->update([
                'status' => 2,
                'failure_reason' => $reason !== '' ? $reason : 'FAILED',
            ]);
            if ($updated) {
                $this->tellClient($deposit->mtn_number, $this->failureText($reason, $deposit->amount));
            }
        }
    }

    protected function storeSplitDeposits(Request $request)
    {
        $data = $request->validate([
            'account_id' => 'required|integer|exists:accounts,id',
            'payment_method' => 'required|in:3,4',
            'note' => 'nullable|string|max:1000',
            'split_phone' => 'required|array|min:2',
            'split_phone.*' => 'nullable|string|max:30',
            'split_amount' => 'required|array|min:2',
            'split_amount.*' => 'nullable|numeric',
        ]);
        $account = Account::where('is_active', true)->findOrFail($data['account_id']);
        $method = (int) $data['payment_method'];
        $lines = [];
        foreach ($data['split_amount'] as $i => $raw) {
            $amount = (int) round((float) $raw);
            $phone = isset($data['split_phone'][$i]) ? trim((string) $data['split_phone'][$i]) : '';
            if ($amount <= 0 && $phone === '') {
                continue;
            }
            if ($amount < 100 || $phone === '') {
                return $this->depositProblem($request, 'Each share needs a number and a whole amount of at least 100 XAF.');
            }
            $lines[] = ['phone' => $phone, 'amount' => $amount];
        }
        if (count($lines) < 2) {
            return $this->depositProblem($request, 'Add at least two numbers and amounts.');
        }
        $asked = 0;
        $errors = [];
        foreach ($lines as $line) {
            $error = $this->openDepositLine($account, $line['phone'], $line['amount'], $method, isset($data['note']) ? $data['note'] : null);
            if ($error) {
                $errors[] = $line['phone'].': '.$error;
            } else {
                $asked++;
            }
        }
        if (! $asked) {
            return $this->depositProblem($request, $errors ? $errors[0] : 'None of the deposits could be sent.');
        }
        $message = 'Asked '.$asked.' '.($asked === 1 ? 'number' : 'numbers').' to pay.';
        if ($errors) {
            $message .= ' '.implode(' ', $errors);
        }

        return redirect()->route('deposit.index')->with('message', $message);
    }

    protected function openDepositLine(Account $account, $rawPhone, $amount, $method, $note)
    {
        $customer = $this->customerFromPhone($rawPhone);
        if (! $customer) {
            return 'That number could not be used.';
        }
        $campay = app(CampayPayoutService::class);
        $phone = $campay->momoNumber($customer->phone_number);
        if ($method === 3 && ! $phone) {
            return 'Not an MTN or Orange Cameroon number.';
        }
        if (! $phone) {
            $phone = preg_replace('/\D/', '', (string) $customer->phone_number);
        }
        $reference = 'dep-'.date('YmdHis').'-'.substr(md5(uniqid('', true)), 0, 8);
        $deposit = Deposit::create([
            'amount' => $amount,
            'customer_id' => $customer->id,
            'user_id' => Auth::id(),
            'depositor_id' => Auth::id(),
            'account_id' => $account->id,
            'note' => $note !== null && $note !== '' ? $note : null,
            'payment_method' => $method,
            'payment_reference' => $reference,
            'mtn_number' => $phone,
            'status' => 0,
            'paid_notice' => 0,
        ]);
        $description = $note !== null && trim((string) $note) !== '' ? substr(trim((string) $note), 0, 80) : 'Deposit';
        if ($method === 3) {
            $body = $campay->collect($phone, $amount, $reference, $description);
            $campayStatus = strtoupper((string) (is_array($body) && isset($body['status']) ? $body['status'] : ''));
            if (! is_array($body) || empty($body['reference']) || $campayStatus === 'FAILED') {
                $reason = is_array($body) && ! empty($body['reason']) ? (string) $body['reason'] : $campayStatus;
                $deposit->status = 2;
                $deposit->failure_reason = $reason !== '' ? substr($reason, 0, 180) : null;
                $deposit->save();

                return $reason !== '' ? $this->failureText($reason, $amount) : 'Campay could not ask this number to approve.';
            }
            $deposit->campay_reference = (string) $body['reference'];
            $deposit->save();

            return null;
        }
        $back = route('deposit.index');
        $card = app(StripeCheckoutService::class)->checkout(
            'Deposit',
            $amount,
            $back.'?session_id={CHECKOUT_SESSION_ID}',
            $back,
            ['deposit' => (string) $deposit->id]
        );
        if (empty($card['ok'])) {
            $deposit->delete();

            return isset($card['error']) ? $card['error'] : 'VISA could not be opened.';
        }
        $deposit->campay_reference = (string) $card['id'];
        $deposit->payment_link = (string) $card['url'];
        $deposit->save();
        $this->tellClient($phone, 'Open this link and enter your card details to pay '.$this->money($amount).' XAF: '.$card['url']);

        return null;
    }

    protected function depositProblem(Request $request, $message)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => false, 'message' => $message], 422);
        }

        return back()->withInput()->with('not_permitted', $message);
    }

    protected function failureText($reason, $amount)
    {
        $reason = strtoupper((string) $reason);
        $money = $this->money($amount);
        if (strpos($reason, 'LOW_BALANCE') !== false || strpos($reason, 'INSUFFICIENT') !== false) {
            return 'Your Mobile Money payment of '.$money.' XAF failed because the number does not have enough money.';
        }
        if ($reason === '' || strpos($reason, 'CANCEL') !== false || strpos($reason, 'TIMEOUT') !== false || strpos($reason, 'EXPIRED') !== false || strpos($reason, 'NOT_APPROV') !== false || strpos($reason, 'DENIED') !== false || strpos($reason, 'REJECT') !== false) {
            return 'Your Mobile Money payment of '.$money.' XAF failed because it was not approved.';
        }

        return 'Your Mobile Money payment of '.$money.' XAF failed.';
    }

    protected function customerFromPhone($raw)
    {
        $campay = app(CampayPayoutService::class);
        $phone = $campay->momoNumber($raw);
        if (! $phone) {
            $digits = preg_replace('/\D/', '', (string) $raw);
            $phone = $digits !== '' ? $digits : null;
        }
        if (! $phone) {
            return null;
        }
        $tail = substr($phone, -9);
        $customer = Customer::where(function ($query) use ($phone, $tail) {
            $query->where('phone_number', $phone)->orWhere('phone_number', 'like', '%'.$tail);
        })->first();
        if ($customer) {
            return $customer;
        }
        $hit = app(MobileMoneyHolderService::class)->lookup($phone);
        $name = ($hit && ! empty($hit['name'])) ? $hit['name'] : $phone;
        $groupId = \App\CustomerGroup::query()->value('id');

        return Customer::create([
            'customer_group_id' => $groupId ? $groupId : 1,
            'name' => $name,
            'phone_number' => $phone,
            'address' => 'N/A',
            'city' => 'N/A',
            'is_active' => true,
        ]);
    }

    protected function applySaleCollections()
    {
        if (! Schema::hasTable('sales') || ! Schema::hasTable('payments') || ! Schema::hasTable('deposits')) {
            return;
        }
        $rows = Deposit::where('status', 1)
            ->where('payment_method', 3)
            ->where(function ($query) {
                $query->where('note', 'like', '%posr-%')->orWhere('note', 'like', '%sr-%');
            })
            ->orderByDesc('id')
            ->limit(30)
            ->get();
        foreach ($rows as $deposit) {
            $this->applyDepositToSale($deposit);
        }
    }

    protected function applyDepositToSale(Deposit $deposit)
    {
        $note = (string) $deposit->note;
        if (! preg_match('/\b((?:posr|sr)-\d{8}-\d+)\b/i', $note, $match)) {
            return false;
        }
        $sale = Sale::where('reference_no', $match[1])->first();
        if (! $sale) {
            return false;
        }
        $campayRef = trim((string) $deposit->campay_reference);
        if ($campayRef === '') {
            return false;
        }
        if (\App\Payment::where('payment_note', 'like', '%'.$campayRef.'%')->exists()) {
            return false;
        }
        $amount = (int) round((float) $deposit->amount);
        $due = (float) $sale->grand_total - (float) $sale->paid_amount;
        if ($amount < 1 || $due < 0.5) {
            return false;
        }
        if ($amount - $due > 0.5) {
            $amount = (int) round($due);
        }
        $applied = false;
        \Illuminate\Support\Facades\DB::transaction(function () use ($deposit, $sale, $campayRef, $amount, &$applied) {
            $locked = Sale::where('id', $sale->id)->lockForUpdate()->first();
            if (! $locked) {
                return;
            }
            if (\App\Payment::where('payment_note', 'like', '%'.$campayRef.'%')->lockForUpdate()->exists()) {
                return;
            }
            $locked->paid_amount = (float) $locked->paid_amount + $amount;
            $balance = (float) $locked->grand_total - (float) $locked->paid_amount;
            $locked->payment_status = $balance <= 0.5 ? 4 : 3;
            $accountId = $deposit->account_id ?: Account::where('is_active', true)->value('id');
            $payment = new \App\Payment();
            $payment->user_id = $deposit->user_id ?: (Auth::id() ?: 1);
            $payment->sale_id = $locked->id;
            $payment->account_id = $accountId ?: 1;
            $payment->payment_reference = 'spr-'.date('Ymd').'-'.date('His');
            $payment->amount = $amount;
            $payment->change = $balance < 0 ? abs($balance) : 0;
            $payment->paying_method = 'MOMO/OM';
            $payment->payment_note = 'Campay '.$campayRef;
            if (Schema::hasColumn('payments', 'provider')) {
                $payment->provider = 'campay';
                $payment->provider_reference = $campayRef;
            }
            $payment->save();
            $locked->save();
            $applied = true;
        });
        if ($applied && ! $deposit->customer_id && $sale->customer_id) {
            $deposit->customer_id = $sale->customer_id;
            $deposit->save();
        }

        return $applied;
    }

    protected function notifyUnsentDeposits()
    {
        $rows = Deposit::where('status', 1)
            ->where('payment_method', 3)
            ->where(function ($query) {
                $query->whereNull('paid_notice')->orWhere('paid_notice', 0);
            })
            ->orderByDesc('id')
            ->limit(8)
            ->get();
        foreach ($rows as $deposit) {
            if (! $this->depositIsClientMoney($deposit->note)) {
                $deposit->paid_notice = 1;
                $deposit->save();
                continue;
            }
            $reference = trim((string) $deposit->campay_reference);
            if ($reference !== '' && \App\Payment::where('payment_note', 'like', '%'.$reference.'%')->exists()) {
                $deposit->paid_notice = 1;
                $deposit->save();
                continue;
            }
            $this->sendDepositNotice($deposit);
        }
    }

    protected function depositIsClientMoney($note)
    {
        $note = strtolower(trim((string) $note));
        if (strpos($note, 'ecobank') !== false || strpos($note, '[campay]') !== false) {
            return false;
        }

        return true;
    }

    protected function rememberCollectionFee(Deposit $deposit, array $item)
    {
        $credit = isset($item['credit']) ? (int) round((float) $item['credit']) : (int) round((float) $deposit->amount);
        $charge = isset($item['charge']) ? (int) round((float) $item['charge']) : 0;
        $gross = isset($item['amount']) ? (int) round((float) $item['amount']) : 0;
        if ($charge < 1 && $gross > $credit) {
            $charge = $gross - $credit;
        }
        if ($charge < 1) {
            return;
        }
        Cache::put('deposit-fee:'.$deposit->id, [
            'fee' => $charge,
            'operator' => app(CampayPayoutService::class)->operatorName((string) $deposit->mtn_number),
        ], now()->addDays(2));
    }

    protected function sendDepositNotice(Deposit $deposit)
    {
        if ((int) $deposit->status !== 1 || (int) $deposit->paid_notice === 1) {
            return;
        }
        $person = $deposit->customer_id ? Customer::find($deposit->customer_id) : null;
        $phone = trim((string) ($deposit->mtn_number ?: ($person ? $person->phone_number : '')));
        if ($phone === '') {
            return;
        }
        $channel = $this->sendDepositInvoice($deposit);
        if ($channel !== 'none') {
            $deposit->paid_notice = 1;
            $deposit->save();
        }
    }

    protected function sendDepositInvoice($deposit)
    {
        $person = $deposit->customer_id ? Customer::find($deposit->customer_id) : null;
        $phone = $deposit->mtn_number ?: ($person ? $person->phone_number : '');
        $name = $person ? trim((string) $person->name) : '';
        if ($name === '' && trim((string) $phone) !== '') {
            $hit = app(MobileMoneyHolderService::class)->lookup($phone, false);
            if (is_array($hit) && isset($hit['source']) && $hit['source'] === 'campay' && ! empty($hit['name'])) {
                $name = (string) $hit['name'];
            }
        }
        if ($name === '') {
            $name = 'Client';
        }
        $reason = trim((string) $deposit->note);
        $method = (int) $deposit->payment_method;
        $methodLabel = $method === 1 ? 'Cash' : ($method === 4 ? 'VISA' : 'Mobile Money');
        $staff = $deposit->user_id ? \App\User::find($deposit->user_id) : null;
        $staffPhone = ($staff && method_exists($staff, 'whatsappPhone')) ? (string) $staff->whatsappPhone() : '';
        $feeBag = Cache::pull('deposit-fee:'.$deposit->id);
        $receipt = [
            'front' => true,
            'party' => 'CLIENT',
            'description' => 'Payment',
            'name' => $name,
            'client_phone' => $phone,
            'amount' => $deposit->amount,
            'fee' => is_array($feeBag) ? (int) $feeBag['fee'] : 0,
            'operator' => is_array($feeBag) ? (string) $feeBag['operator'] : '',
            'reason' => $reason,
            'reference' => $deposit->payment_reference ?: ($deposit->campay_reference ?: ('deposit-'.$deposit->id)),
            'date' => $deposit->updated_at ?: now(),
            'method' => $methodLabel,
            'requested_by' => $staff ? trim((string) $staff->name) : '',
            'requested_phone' => $staffPhone,
            'created_by' => $staff ? trim((string) $staff->name) : '',
            'title' => 'Payment Confirmation',
            'skip_pdf' => true,
            'skip_admin_copy' => true,
        ];

        return app(\App\Services\MomoMiniReceipt::class)->send($receipt + ['phone' => $phone]);
    }

    protected function tellClient($phone, $message)
    {
        return app(ClientNoticeService::class)->send($phone, $message);
    }

    protected function money($amount)
    {
        return number_format((float) $amount, 0, '.', ' ');
    }

    protected function campayError($body, $fallback)
    {
        if (! is_array($body)) {
            return $fallback;
        }
        foreach (['message', 'detail', 'error', 'reason'] as $key) {
            if (! empty($body[$key]) && is_string($body[$key])) {
                return substr($body[$key], 0, 250);
            }
        }

        return $fallback;
    }
}

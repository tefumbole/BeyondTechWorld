<?php

namespace App\Http\Controllers;

use App\Account;
use App\Customer;
use App\Deposit;
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
    public function index()
    {
        $sales = Sale::with('customer')->where('payment_status', "!=", 4)->orderByDesc('id')->get();
        $lims_account_list = Account::orderByDesc('id')->get();
        return view('payment.customer-data', compact('sales', 'lims_account_list'));
    }


    public function AwaitingPayments($id)
    {
        $sales = Sale::with('customer')->where('customer_id', $id)->where('payment_status', "!=", 4)->orderByDesc('id')->get();
        $lims_account_list = Account::orderByDesc('id')->get();
        return view('payment.customer-data', compact('sales', 'lims_account_list'));
    }

    public function Desposit() {
        $this->settleConfirmedMoney();
        $deposits = Deposit::with(['customer', 'customerGroup', 'user', 'depositor', 'account'])->orderByDesc('created_at')->orderByDesc('id')->get();
        $paidAmounts = $this->paymentPaidAmounts($deposits);
        $accounts = Account::where('is_active', true)->orderBy('name')->get();
        $making = request()->get('new') === '1';
        $campayBalance = null;
        try {
            $campayBalance = app(CampayPayoutService::class)->balance();
        } catch (\Throwable $e) {
            $campayBalance = null;
        }

        return view('payment.deposits', compact('deposits', 'accounts', 'making', 'campayBalance', 'paidAmounts'));
    }

    public function depositLive()
    {
        $this->settleConfirmedMoney();
        $total = '';
        try {
            $total = (string) app(CampayPayoutService::class)->appBalance();
        } catch (\Throwable $e) {
            $total = '';
        }
        $rows = [];
        $pending = false;
        foreach (Deposit::orderByDesc('id')->limit(80)->get(['id', 'status']) as $row) {
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

        return response()->json([
            'total' => $total,
            'balance' => $total,
            'pending' => $pending,
            'rows' => $rows,
        ]);
    }

    public function settleConfirmedMoney()
    {
        $this->settleWaitingDeposits();
        $this->importConfirmedCollections();
    }

    public function importConfirmedCollections()
    {
        if (! Schema::hasTable('deposits') || ! Schema::hasColumn('deposits', 'campay_reference')) {
            return;
        }
        try {
            $items = app(CampayPayoutService::class)->confirmedCollections(date('Y-m-d', strtotime('-2 days')), date('Y-m-d'));
        } catch (\Throwable $e) {
            return;
        }
        $accountId = Account::where('is_active', true)->value('id');
        if (! $accountId) {
            return;
        }
        foreach ($items as $item) {
            $reference = trim((string) (isset($item['reference_uuid']) ? $item['reference_uuid'] : ''));
            if ($reference === '' || Deposit::where('campay_reference', $reference)->exists()) {
                continue;
            }
            $credit = (int) round((float) $item['credit']);
            if ($credit < 1) {
                continue;
            }
            $phone = isset($item['phone_number']) ? (string) $item['phone_number'] : '';
            $customer = null;
            try {
                $customer = $this->customerFromPhone($phone);
            } catch (\Throwable $e) {
                $customer = null;
            }
            $when = date('Y-m-d H:i:s');
            if (! empty($item['datetime'])) {
                $stamp = strtotime((string) $item['datetime']);
                if ($stamp) {
                    $when = date('Y-m-d H:i:s', $stamp);
                }
            }
            $note = trim((string) (isset($item['description']) ? $item['description'] : ''));
            if ($note === '') {
                $note = 'Campay confirmed';
            }
            $deposit = Deposit::create([
                'amount' => $credit,
                'customer_id' => $customer ? $customer->id : null,
                'user_id' => Auth::id() ?: 1,
                'account_id' => $accountId,
                'mtn_number' => $customer ? $customer->phone_number : $phone,
                'payment_method' => 3,
                'status' => 0,
                'note' => substr($note, 0, 180),
                'campay_reference' => $reference,
                'created_at' => $when,
                'updated_at' => $when,
            ]);
            app(AccountDepositService::class)->creditPending($deposit);
        }
    }

    protected function paymentPaidAmounts($deposits)
    {
        $byRef = [];
        try {
            $items = Cache::remember('btw.campay.collects', 30, function () {
                return app(CampayPayoutService::class)->confirmedCollections(
                    date('Y-m-d', strtotime('-14 days')),
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
                $deposit->paid_notice = 1;
                $deposit->save();
                $this->tellClient($customer->phone_number, 'Beyond Enterprise received your cash deposit of '.$this->money($deposit->amount).' XAF.');
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
                if ($paid && (int) $paid->paid_notice !== 1) {
                    $paid->paid_notice = 1;
                    $paid->save();
                    $this->tellClient($paid->mtn_number, 'Beyond Enterprise received your deposit of '.$this->money($paid->amount).' XAF.');
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
            $paid = app(AccountDepositService::class)->creditPending($deposit);
            if ($paid) {
                \App\Gbhs\GbhsDonate::noteDepositPaid($paid);
            }
            if ($paid && (int) $paid->paid_notice !== 1) {
                $paid->paid_notice = 1;
                $paid->save();
                $this->tellClient($paid->mtn_number, 'Beyond Enterprise received your deposit of '.$this->money($paid->amount).' XAF.');
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

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
        $this->settleWaitingDeposits();
        $deposits = Deposit::with(['customer', 'customerGroup', 'user', 'depositor', 'account'])->orderByDesc('id')->get();
        $accounts = Account::where('is_active', true)->orderBy('name')->get();
        $making = request()->get('new') === '1';

        return view('payment.deposits', compact('deposits', 'accounts', 'making'));
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
        $hit = app(MobileMoneyHolderService::class)->lookup($phone);

        return response()->json([
            'ok' => true,
            'phone' => $phone,
            'name' => ($hit && ! empty($hit['name'])) ? $hit['name'] : '',
            'operator' => $campay->operatorName($phone),
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
        $lookup = $phone ? app(MobileMoneyHolderService::class)->lookup($phone) : null;
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
                if ($paid && (int) $paid->paid_notice !== 1) {
                    $paid->paid_notice = 1;
                    $paid->save();
                    $this->tellClient($paid->mtn_number, 'Beyond Enterprise received your deposit of '.$this->money($paid->amount).' XAF.');
                }
            }

            return;
        }
        $body = app(CampayPayoutService::class)->transaction($deposit->campay_reference);
        if (! is_array($body)) {
            return;
        }
        $status = strtoupper((string) (isset($body['status']) ? $body['status'] : ''));
        if ($status === 'SUCCESSFUL') {
            $paid = app(AccountDepositService::class)->creditPending($deposit);
            if ($paid && (int) $paid->paid_notice !== 1) {
                $paid->paid_notice = 1;
                $paid->save();
                $this->tellClient($paid->mtn_number, 'Beyond Enterprise received your deposit of '.$this->money($paid->amount).' XAF.');
            }
        } elseif ($status === 'FAILED') {
            $reason = isset($body['reason']) ? substr((string) $body['reason'], 0, 180) : 'FAILED';
            $updated = Deposit::where('id', $deposit->id)->where('status', 0)->update([
                'status' => 2,
                'failure_reason' => $reason,
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

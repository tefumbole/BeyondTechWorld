<?php

namespace App\Http\Controllers;

use App\Account;
use App\Customer;
use App\Deposit;
use App\Sale;
use App\Services\AccountDepositService;
use App\Services\CampayPayoutService;
use App\Services\ClientNoticeService;
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

    public function storeDeposit(Request $request, AccountDepositService $deposits)
    {
        $data = $request->validate([
            'account_id' => 'required|integer|exists:accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:1,3,4',
            'note' => 'nullable|string|max:1000',
            'customer_id' => 'nullable|integer',
        ]);
        $account = Account::where('is_active', true)->findOrFail($data['account_id']);
        $method = (int) $data['payment_method'];
        $note = isset($data['note']) ? $data['note'] : null;
        $customer = null;
        if (! empty($data['customer_id'])) {
            $customer = Customer::find($data['customer_id']);
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
            return back()->withInput()->with('not_permitted', 'Choose the client who is paying.');
        }
        $campay = app(CampayPayoutService::class);
        $phone = $campay->momoNumber($customer->phone_number);
        if ($method === 3 && ! $phone) {
            return back()->withInput()->with('not_permitted', $customer->name.' does not have an MTN or Orange Cameroon number.');
        }
        if (! $phone) {
            $digits = preg_replace('/\D/', '', (string) $customer->phone_number);
            if ($digits === '') {
                return back()->withInput()->with('not_permitted', $customer->name.' does not have a phone number.');
            }
            $phone = $digits;
        }
        $amount = (int) round((float) $data['amount']);
        if ($amount < 100 || abs((float) $data['amount'] - $amount) > 0.001) {
            return back()->withInput()->with('not_permitted', 'Enter a whole amount of at least 100 XAF.');
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
            if (! is_array($body) || empty($body['reference']) || strtoupper((string) (isset($body['status']) ? $body['status'] : '')) === 'FAILED') {
                $deposit->delete();

                return back()->withInput()->with('not_permitted', $this->campayError($body, 'Campay could not ask the client to approve.'));
            }
            $deposit->campay_reference = (string) $body['reference'];
            $deposit->save();
            $this->tellClient($phone, 'Beyond Enterprise is requesting '.$this->money($amount).' XAF from your Mobile Money. Approve the prompt on your phone.');
            $this->settleWaitingDeposits();

            return redirect()->route('deposit.index')->with('message', 'The client has been asked to approve '.$this->money($amount).' XAF on their phone.');
        }

        $body = $campay->cardLink($phone, $amount, $reference, $description, route('deposit.index'), $customer->name);
        if (! is_array($body) || empty($body['link'])) {
            $deposit->delete();

            return back()->withInput()->with('not_permitted', $this->campayError($body, 'Campay did not return a card link.'));
        }
        $deposit->campay_reference = isset($body['reference']) ? (string) $body['reference'] : null;
        $deposit->payment_link = (string) $body['link'];
        $deposit->save();
        $channel = $this->tellClient($phone, 'Beyond Enterprise: pay '.$this->money($amount).' XAF by card here: '.$body['link']);

        return redirect()->route('deposit.index')->with('message', $channel === 'none'
            ? 'The card link was created, but the client could not be notified.'
            : 'A card link was sent to '.$customer->name.'.');
    }

    protected function settleWaitingDeposits()
    {
        $waiting = Deposit::where('status', 0)->whereNotNull('campay_reference')->orderBy('id')->limit(8)->get();
        if ($waiting->isEmpty()) {
            return;
        }
        $campay = app(CampayPayoutService::class);
        $accounts = app(AccountDepositService::class);
        foreach ($waiting as $deposit) {
            $body = $campay->transaction($deposit->campay_reference);
            if (! is_array($body)) {
                continue;
            }
            $status = strtoupper((string) (isset($body['status']) ? $body['status'] : ''));
            if ($status === 'SUCCESSFUL') {
                $paid = $accounts->creditPending($deposit);
                if ($paid && (int) $paid->paid_notice !== 1) {
                    $paid->paid_notice = 1;
                    $paid->save();
                    $this->tellClient($paid->mtn_number, 'Beyond Enterprise received your deposit of '.$this->money($paid->amount).' XAF.');
                }
            } elseif ($status === 'FAILED') {
                $deposit->status = 2;
                $deposit->save();
            }
        }
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

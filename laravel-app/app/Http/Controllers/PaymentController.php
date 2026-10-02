<?php

namespace App\Http\Controllers;

use App\Account;
use App\Deposit;
use App\Sale;
use App\Services\AccountDepositService;
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
        $deposits = Deposit::with(['customer', 'customerGroup', 'user', 'depositor', 'account'])->orderByDesc('id')->get();
        $accounts = Account::where('is_active', true)->orderBy('name')->get();
        return view('payment.deposits', compact('deposits', 'accounts'));
    }

    public function storeDeposit(Request $request, AccountDepositService $deposits)
    {
        $data = $request->validate([
            'account_id' => 'required|integer|exists:accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:1,2,3',
            'note' => 'nullable|string|max:1000',
        ]);
        $account = Account::where('is_active', true)->findOrFail($data['account_id']);
        $deposits->record($account, $data['amount'], $data['payment_method'], isset($data['note']) ? $data['note'] : null, Auth::id());

        return redirect()->route('deposit.index')->with('message', 'Deposit of '.number_format((float) $data['amount'], 2).' saved to '.$account->name.'.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Account;
use App\Deposit;
use App\Sale;

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
        $deposits = Deposit::orderByDesc('id')->get();
        return view('payment.deposits', compact('deposits'));
    }
}

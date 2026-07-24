<?php

namespace App\Http\Controllers;

use App\Booking;
use App\Product;
use App\Product_Warehouse;
use App\ProductBatch;
use App\ProductVariant;
use App\Unit;
use Illuminate\Http\Request;
use App\Sale;
use App\Returns;
use App\ReturnPurchase;
use App\ProductPurchase;
use App\Purchase;
use App\Expense;
use App\Payroll;
use App\Quotation;
use App\Payment;
use App\Account;
use App\Product_Sale;
use App\Customer;
use DB;
use Auth;
use Printing;
use Rawilk\Printing\Contracts\Printer;
use Spatie\Permission\Models\Role;
use Twilio\Rest\Client;

/*use vendor\autoload;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer;*/

class HomeController extends Controller
{
//    public function __construct()
//    {
//        $this->middleware('auth');
//    }

    public function dashboard()
    {
        if(Auth::user()->otp_verify == 0) {
            return redirect()->route('check.otp');
        }
        $role = Role::find(Auth::user()->role_id);
        return view('home');
    }

    public function logout() {
        $user = Auth::user();
        $user->update(['otp_verify' => '0']);
        Auth::logout();
        return redirect()->route('login');
    }

    public function otpCheck(){
        $user = Auth::user();

        try {
            $this->sendOTP($user);
        } catch (\Exception $e) {
            return view('otp_screen', [
                'resend_seconds' => $this->otpResendSecondsRemaining($user),
                'whatsapp_error' => $e->getMessage(),
            ]);
        }

        $user->refresh();

        return view('otp_screen', [
            'resend_seconds' => $this->otpResendSecondsRemaining($user),
        ]);
    }

    public function otpResend(Request $request)
    {
        $user = Auth::user();
        $remaining = $this->otpResendSecondsRemaining($user);

        if ($remaining > 0) {
            $minutes = (int) ceil($remaining / 60);
            return redirect()->back()->with(
                'not_permitted',
                'You can resend OTP in ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's') . '.'
            );
        }

        try {
            $this->forceSendOTP($user);
        } catch (\Exception $e) {
            return redirect()->back()->with('not_permitted', $e->getMessage());
        }

        return redirect()->back()->with('message', 'A new OTP has been sent to your WhatsApp.');
    }

    public function otpCheckStore(Request $request) {
        $user = Auth::user();

        if ($request->otp == $user->otp && $user->otp_time > date('Y-m-d H:i:s', strtotime('-5 minutes'))) {
            $user->update(['otp' => null, 'otp_time' => null, 'otp_verify' => '1']);
            return redirect('/admin');
        }

        return redirect()->back()->with('not_permitted', 'Invalid or expired OTP');
    }

    protected function otpResendSecondsRemaining($user)
    {
        if (empty($user->otp_time)) {
            return 0;
        }

        $elapsed = time() - strtotime($user->otp_time);

        return max(0, 300 - $elapsed);
    }

    public function sendOTP($user) {
        if (!empty($user->otp_time) && $user->otp_time >= date('Y-m-d H:i:s', strtotime('-5 minutes'))) {
            return null;
        }

        return $this->forceSendOTP($user);
    }

    protected function forceSendOTP($user)
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $phone = trim((string) $user->phone);
        if ($phone === '') {
            throw new \Exception('No phone number on this account for WhatsApp OTP.');
        }

        $result = app(\App\Services\Messaging\NotificationRouter::class)
            ->sendWhatsAppOtp($phone, $otp, 'login', 10);

        if (empty($result['success'])) {
            throw new \Exception('Could not send OTP via WhatsApp: '.($result['error'] ?? 'unknown error'));
        }

        $user->update(['otp' => $otp, 'otp_time' => date('Y-m-d H:i:s')]);

        return $otp;
    }

    public function whatsapp()
    {
        $sid = getenv("ACCOUNT_SID");
        $token = getenv("AUTH_TOKEN");
        $twilio = new Client($sid, $token);

        $message = $twilio->messages->create(
            'whatsapp:+923410060960', // +23775321739
            array(
                'from' => getenv("TWILIO_FROM"),
                'body' => 'hi twilio here'
            )
        );

        print($message->sid);
    }


    public function mobileMoneyToken(){
        $curl = curl_init();


        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://demo.campay.net/api/token/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>'{
                                "username": "tzTGYFo9eF9d4E8VdQ6G_WnCmOtTQSlZKbY5bJaoeSZpUhD5Z6hMTzaZ8of39L0-FvHBS6YMyZyDxtclpsEcnw",
                                "password": "EpER_6cr-YQjIDqlee4-6yVSG1KpB2zL4VTy1tROoE_f6YNYxCl_llU-h43QqBrfI9JwkKx-XT5RUXx2AnNOOw"
                                }',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        echo $response;
    }

    public function mobileMoneyRequest(){

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://demo.campay.net/api/collect/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>'{"amount":"2","from":"237675321739","description":"Test","external_reference": ""}',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Token eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCIsInVpZCI6MjIxN30.eyJpYXQiOjE2OTA2MjM2ODgsIm5iZiI6MTY5MDYyMzY4OCwiZXhwIjoxNjkwNjI3Mjg4fQ.buVJrXG2oI-UKUfmrTDro4Q7HguqKWAsyKe6T481nNM',
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
echo $response;

    }

    public function mobileMoneyStatus(){


        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://demo.campay.net/api/transaction/ebf4cd4f-1809-4b30-9117-11bcc9c38387',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Token eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCIsInVpZCI6MjIxN30.eyJpYXQiOjE2ODY4MzM0MzEsIm5iZiI6MTY4NjgzMzQzMSwiZXhwIjoxNjg2ODM3MDMxfQ.0XBGJhHUgUFuIaNbBVIZhSMmzDx7OZIpt88Hh84ciC4',
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        echo $response;

    }

    public function manageBooking(){
        $pendingBooking = Booking::join('booking_products', 'booking_products.booking_id', '=', 'bookings.id')
                        ->select('product_id', 'qty', 'booking_id')
                        ->whereDate('booking_products.start', '<=', date('y-m-d H:i:s'))
                        ->whereDate('booking_products.end', '>=', date('y-m-d H:i:s'))
                        ->where('bookings.is_frontend', 1)
                        ->where('bookings.payment_status', 4)
                        ->where('bookings.booking_status', 2)
                        ->get();

        $completeBooking = Booking::join('booking_products', 'booking_products.booking_id', '=', 'bookings.id')
                        ->select('product_id', 'qty', 'booking_id')
                        ->whereDate('booking_products.start', '<=', date('y-m-d H:i:s'))
                        ->whereDate('booking_products.end', '<=', date('y-m-d H:i:s'))
                        ->where('bookings.is_frontend', 1)
                        ->where('bookings.payment_status', 4)
                        ->where('bookings.booking_status', 1)
                        ->get();

        foreach ($pendingBooking as $product_sale_data) {
            $lims_product_data = Product::find($product_sale_data->product_id);

            if ($lims_product_data->type == 'standard') {
                $old_product_qty = $product_sale_data->qty;
                $lims_product_warehouse_data = Product_Warehouse::FindProductWithoutVariant($product_sale_data->product_id, 1)
                    ->first();
                $lims_product_data->qty -= $old_product_qty;
                $lims_product_warehouse_data->qty -= $old_product_qty;
                $this->stockDurationSave($lims_product_data->id, $lims_product_data->qty);
                $lims_product_data->save();
                $lims_product_warehouse_data->save();
            }

            Booking::where('id', $product_sale_data->booking_id)->update(['booking_status' => 1]);
        }

        foreach ($completeBooking as $product_sale_data) {
            $lims_product_data = Product::find($product_sale_data->product_id);

            if ($lims_product_data->type == 'standard') {
                $old_product_qty = $product_sale_data->qty;
                $lims_product_warehouse_data = Product_Warehouse::FindProductWithoutVariant($product_sale_data->product_id, 1)
                    ->first();
                $lims_product_data->qty += $old_product_qty;
                $lims_product_warehouse_data->qty += $old_product_qty;
                $this->stockDurationSave($lims_product_data->id, $lims_product_data->qty);
                $lims_product_data->save();
                $lims_product_warehouse_data->save();
            }

            Booking::where('id', $product_sale_data->booking_id)->update(['booking_status' => 3]);
        }

    }

    public function index()
    {
        $this->manageBooking();
        $role = Role::find(Auth::user()->role_id);
        $role->revokePermissionTo('search_all_products');
        if($role->hasPermissionTo('one_time_otp')) {
            if (Auth::user()->otp_verify == 0) {
                return redirect()->route('check.otp');
            }
        }

        if(Auth::user()->role_id == 7) {
            return redirect()->route('asset.dashboard');
        }
        if(Auth::user()->role_id == 5) {
            $customer = Customer::select('id', 'points')->where('user_id', Auth::id())->first();
            $points = $customer->points;
            $lims_sale_data = Sale::with('warehouse')->where('customer_id', $customer->id)->orderBy('created_at', 'desc')->get();
            $lims_payment_data = DB::table('payments')
                           ->join('sales', 'payments.sale_id', '=', 'sales.id')
                           ->where('customer_id', $customer->id)
                           ->select('payments.*', 'sales.reference_no as sale_reference')
                           ->orderBy('payments.created_at', 'desc')
                           ->get();
            $lims_quotation_data = Quotation::with('biller', 'customer', 'supplier', 'user')->orderBy('id', 'desc')->where('customer_id', $customer->id)->orderBy('created_at', 'desc')->get();

            $lims_return_data = Returns::with('warehouse', 'customer', 'biller')->where('customer_id', $customer->id)->orderBy('created_at', 'desc')->get();
            return view('customer_index', compact('lims_sale_data', 'lims_payment_data', 'lims_quotation_data', 'lims_return_data', 'points'));
        }

        $start_date = date("Y").'-'.date("m").'-'.'01';
        $end_date = date("Y").'-'.date("m").'-'.date('t', mktime(0, 0, 0, date("m"), 1, date("Y")));
        $yearly_sale_amount = [];

        $general_setting = DB::table('general_settings')->latest()->first();
        if(Auth::user()->role_id > 2 && $general_setting->staff_access == 'own') {
            $product_sale_data = Sale::join('product_sales', 'sales.id','=', 'product_sales.sale_id')
                ->select(DB::raw('product_sales.product_id, product_sales.product_batch_id, sum(product_sales.qty) as sold_qty, sum(product_sales.total) as sold_amount'))
                ->where('sales.user_id', Auth::id())
                ->whereDate('product_sales.created_at', '>=' , $start_date)
                ->whereDate('product_sales.created_at', '<=' , $end_date)
                ->groupBy('product_sales.product_id', 'product_sales.product_batch_id')
                ->get();

            $product_revenue = 0;
            $product_cost = 0;
            $profit = 0;
            foreach ($product_sale_data as $key => $product_sale) {
                if($product_sale->product_batch_id)
                    $product_purchase_data = ProductPurchase::where([
                        ['product_id', $product_sale->product_id],
                        ['product_batch_id', $product_sale->product_batch_id]
                    ])->get();
                else
                    $product_purchase_data = ProductPurchase::where('product_id', $product_sale->product_id)->get();

                $purchased_qty = 0;
                $purchased_amount = 0;
                $sold_qty = $product_sale->sold_qty;
                $product_revenue += $product_sale->sold_amount;
                foreach ($product_purchase_data as $key => $product_purchase) {
                    $purchased_qty += $product_purchase->qty;
                    $purchased_amount += $product_purchase->total;
                    if($purchased_qty >= $sold_qty) {
                        $qty_diff = $purchased_qty - $sold_qty;
                        $unit_cost = $product_purchase->total / $product_purchase->qty;
                        $purchased_amount -= ($qty_diff * $unit_cost);
                        break;
                    }
                }

                $product_cost += $purchased_amount;
            }

            $revenue = Sale::whereDate('created_at', '>=' , $start_date)->where('user_id', Auth::id())->whereDate('created_at', '<=' , $end_date)->sum('grand_total');
            $return = Returns::whereDate('created_at', '>=' , $start_date)->where('user_id', Auth::id())->whereDate('created_at', '<=' , $end_date)->sum('grand_total');
            $purchase_return = ReturnPurchase::whereDate('created_at', '>=' , $start_date)->where('user_id', Auth::id())->whereDate('created_at', '<=' , $end_date)->sum('grand_total');
            $revenue = $revenue - $return;
            $purchase = Purchase::whereDate('created_at', '>=' , $start_date)->where('user_id', Auth::id())->whereDate('created_at', '<=' , $end_date)->sum('grand_total');
            $profit = $revenue + $purchase_return - $product_cost;
            $expense = Expense::whereDate('created_at', '>=' , $start_date)->where('user_id', Auth::id())->whereDate('created_at', '<=' , $end_date)->sum('amount');
            $recent_sale = Sale::orderBy('id', 'desc')->where('user_id', Auth::id())->take(5)->get();
            $recent_purchase = Purchase::orderBy('id', 'desc')->where('user_id', Auth::id())->take(5)->get();
            $recent_quotation = Quotation::orderBy('id', 'desc')->where('user_id', Auth::id())->take(5)->get();
            $recent_payment = Payment::orderBy('id', 'desc')->where('user_id', Auth::id())->take(5)->get();
        }
        else {
            $product_sale_data = Product_Sale::select(DB::raw('product_id, product_batch_id, sum(qty) as sold_qty, sum(total) as sold_amount'))->whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->groupBy('product_id', 'product_batch_id')->get();

            $product_revenue = 0;
            $product_cost = 0;
            $profit = 0;
            foreach ($product_sale_data as $key => $product_sale) {
                if($product_sale->product_batch_id)
                    $product_purchase_data = ProductPurchase::where([
                        ['product_id', $product_sale->product_id],
                        ['product_batch_id', $product_sale->product_batch_id]
                    ])->get();
                else
                    $product_purchase_data = ProductPurchase::where('product_id', $product_sale->product_id)->get();

                $purchased_qty = 0;
                $purchased_amount = 0;
                $sold_qty = $product_sale->sold_qty;
                $product_revenue += $product_sale->sold_amount;
                foreach ($product_purchase_data as $key => $product_purchase) {
                    $purchased_qty += $product_purchase->qty;
                    $purchased_amount += $product_purchase->total;
                    if($purchased_qty >= $sold_qty) {
                        $qty_diff = $purchased_qty - $sold_qty;
                        $unit_cost = $product_purchase->total / $product_purchase->qty;
                        $purchased_amount -= ($qty_diff * $unit_cost);
                        break;
                    }
                }

                $product_cost += $purchased_amount;
            }

            $revenue = Sale::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('grand_total');
            $return = Returns::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('grand_total');
            $purchase_return = ReturnPurchase::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('grand_total');
            $revenue = $revenue - $return;
            $purchase = Purchase::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('grand_total');
            $profit = $revenue + $purchase_return - $product_cost;
            $expense = Expense::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount');
            $recent_sale = Sale::orderBy('id', 'desc')->take(5)->get();
            $recent_purchase = Purchase::orderBy('id', 'desc')->take(5)->get();
            $recent_quotation = Quotation::orderBy('id', 'desc')->take(5)->get();
            $recent_payment = Payment::orderBy('id', 'desc')->take(5)->get();
        }

        $best_selling_qty = Product_Sale::select(DB::raw('product_id, sum(qty) as sold_qty'))->whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->groupBy('product_id')->orderBy('sold_qty', 'desc')->take(5)->get();

        $yearly_best_selling_qty = Product_Sale::select(DB::raw('product_id, sum(qty) as sold_qty'))->whereDate('created_at', '>=' , date("Y").'-01-01')->whereDate('created_at', '<=' , date("Y").'-12-31')->groupBy('product_id')->orderBy('sold_qty', 'desc')->take(5)->get();

        $yearly_best_selling_price = Product_Sale::select(DB::raw('product_id, sum(total) as total_price'))->whereDate('created_at', '>=' , date("Y").'-01-01')->whereDate('created_at', '<=' , date("Y").'-12-31')->groupBy('product_id')->orderBy('total_price', 'desc')->take(5)->get();

        //cash flow of last 6 months
        $start = strtotime(date('Y-m-01', strtotime('-6 month', strtotime(date('Y-m-d') ))));
        $end = strtotime(date('Y-m-'.date('t', mktime(0, 0, 0, date("m"), 1, date("Y")))));

        while($start < $end)
        {
            $start_date = date("Y-m", $start).'-'.'01';
            $end_date = date("Y-m", $start).'-'.date('t', mktime(0, 0, 0, date("m", $start), 1, date("Y", $start)));

            if(Auth::user()->role_id > 2 && $general_setting->staff_access == 'own') {
                $recieved_amount = DB::table('payments')->whereNotNull('sale_id')->whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('user_id', Auth::id())->sum('amount');
                $sent_amount = DB::table('payments')->whereNotNull('purchase_id')->whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('user_id', Auth::id())->sum('amount');
                $return_amount = Returns::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('user_id', Auth::id())->sum('grand_total');
                $purchase_return_amount = ReturnPurchase::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('user_id', Auth::id())->sum('grand_total');
                $expense_amount = Expense::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('user_id', Auth::id())->sum('amount');
                $payroll_amount = Payroll::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('user_id', Auth::id())->sum('amount');
            }
            else {
                $recieved_amount = DB::table('payments')->whereNotNull('sale_id')->whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount');
                $sent_amount = DB::table('payments')->whereNotNull('purchase_id')->whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount');
                $return_amount = Returns::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('grand_total');
                $purchase_return_amount = ReturnPurchase::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('grand_total');
                $expense_amount = Expense::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount');
                $payroll_amount = Payroll::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount');
            }
            $sent_amount = $sent_amount + $return_amount + $expense_amount + $payroll_amount;

            $payment_recieved[] = number_format((float)($recieved_amount + $purchase_return_amount), 2, '.', '');
            $payment_sent[] = number_format((float)$sent_amount, 2, '.', '');
            $month[] = date("F", strtotime($start_date));
            $start = strtotime("+1 month", $start);
        }
        // yearly report
        $start = strtotime(date("Y") .'-01-01');
        $end = strtotime(date("Y") .'-12-31');
        while($start < $end)
        {
            $start_date = date("Y").'-'.date('m', $start).'-'.'01';
            $end_date = date("Y").'-'.date('m', $start).'-'.date('t', mktime(0, 0, 0, date("m", $start), 1, date("Y", $start)));
            if(Auth::user()->role_id > 2 && $general_setting->staff_access == 'own') {
                $sale_amount = Sale::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('user_id', Auth::id())->sum('grand_total');
                $purchase_amount = Purchase::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('user_id', Auth::id())->sum('grand_total');
            }
            else{
                $sale_amount = Sale::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('grand_total');
                $purchase_amount = Purchase::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('grand_total');
            }
            $yearly_sale_amount[] = number_format((float)$sale_amount, 2, '.', '');
            $yearly_purchase_amount[] = number_format((float)$purchase_amount, 2, '.', '');
            $start = strtotime("+1 month", $start);
        }
        return view('index', compact('revenue', 'purchase', 'expense', 'return', 'purchase_return', 'profit', 'payment_recieved', 'payment_sent', 'month', 'yearly_sale_amount', 'yearly_purchase_amount', 'recent_sale', 'recent_purchase', 'recent_quotation', 'recent_payment', 'best_selling_qty', 'yearly_best_selling_qty', 'yearly_best_selling_price'));
    }

    public function dashboardFilter($start_date, $end_date)
    {
        $general_setting = DB::table('general_settings')->latest()->first();
        if(Auth::user()->role_id > 2 && $general_setting->staff_access == 'own') {
            $product_sale_data = Sale::join('product_sales', 'sales.id','=', 'product_sales.sale_id')
                ->select(DB::raw('product_sales.product_id, product_sales.product_batch_id, sum(product_sales.qty) as sold_qty, sum(product_sales.total) as sold_amount'))
                ->where('sales.user_id', Auth::id())
                ->whereDate('product_sales.created_at', '>=' , $start_date)
                ->whereDate('product_sales.created_at', '<=' , $end_date)
                ->groupBy('product_sales.product_id', 'product_sales.product_batch_id')
                ->get();

            $product_revenue = 0;
            $product_cost = 0;
            $profit = 0;
            foreach ($product_sale_data as $key => $product_sale) {
                if($product_sale->product_batch_id)
                    $product_purchase_data = ProductPurchase::where([
                        ['product_id', $product_sale->product_id],
                        ['product_batch_id', $product_sale->product_batch_id]
                    ])->get();
                else
                    $product_purchase_data = ProductPurchase::where('product_id', $product_sale->product_id)->get();

                $purchased_qty = 0;
                $purchased_amount = 0;
                $sold_qty = $product_sale->sold_qty;
                $product_revenue += $product_sale->sold_amount;
                foreach ($product_purchase_data as $key => $product_purchase) {
                    $purchased_qty += $product_purchase->qty;
                    $purchased_amount += $product_purchase->total;
                    if($purchased_qty >= $sold_qty) {
                        $qty_diff = $purchased_qty - $sold_qty;
                        $unit_cost = $product_purchase->total / $product_purchase->qty;
                        $purchased_amount -= ($qty_diff * $unit_cost);
                        break;
                    }
                }

                $product_cost += $purchased_amount;
            }

            $revenue = Sale::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('user_id', Auth::id())->sum('grand_total');
            $return = Returns::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('user_id', Auth::id())->sum('grand_total');
            $purchase_return = ReturnPurchase::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('user_id', Auth::id())->sum('grand_total');
            $revenue -= $return;
            $purchase = Purchase::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('user_id', Auth::id())->sum('grand_total');
            $profit = $revenue + $purchase_return - $product_cost;

            $data[0] = $revenue;
            $data[1] = $return;
            $data[2] = $profit;
            $data[3] = $purchase_return;
        }
        else{
            $product_sale_data = Product_Sale::select(DB::raw('product_id, product_batch_id, sum(qty) as sold_qty, sum(total) as sold_amount'))->whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->groupBy('product_id', 'product_batch_id')->get();

            $product_revenue = 0;
            $product_cost = 0;
            $profit = 0;
            foreach ($product_sale_data as $key => $product_sale) {
                if($product_sale->product_batch_id)
                    $product_purchase_data = ProductPurchase::where([
                        ['product_id', $product_sale->product_id],
                        ['product_batch_id', $product_sale->product_batch_id]
                    ])->get();
                else
                    $product_purchase_data = ProductPurchase::where('product_id', $product_sale->product_id)->get();

                $purchased_qty = 0;
                $purchased_amount = 0;
                $sold_qty = $product_sale->sold_qty;
                $product_revenue += $product_sale->sold_amount;
                foreach ($product_purchase_data as $key => $product_purchase) {
                    $purchased_qty += $product_purchase->qty;
                    $purchased_amount += $product_purchase->total;
                    if($purchased_qty >= $sold_qty) {
                        $qty_diff = $purchased_qty - $sold_qty;
                        $unit_cost = $product_purchase->total / $product_purchase->qty;
                        $purchased_amount -= ($qty_diff * $unit_cost);
                        break;
                    }
                }

                $product_cost += $purchased_amount;
            }

            $revenue = Sale::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('grand_total');
            $return = Returns::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('grand_total');
            $purchase_return = ReturnPurchase::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('grand_total');
            $revenue -= $return;
            $purchase = Purchase::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('grand_total');
            $profit = $revenue + $purchase_return - $product_cost;

            $data[0] = $revenue;
            $data[1] = $return;
            $data[2] = $profit;
            $data[3] = $purchase_return;
        }

        return $data;
    }

    public function myTransaction($year, $month)
    {
        $start = 1;
        $number_of_day = date('t', mktime(0, 0, 0, $month, 1, $year));
        while($start <= $number_of_day)
        {
            if($start < 10)
                $date = $year.'-'.$month.'-0'.$start;
            else
                $date = $year.'-'.$month.'-'.$start;
            $sale_generated[$start] = Sale::whereDate('created_at', $date)->where('user_id', Auth::id())->count();
            $sale_grand_total[$start] = Sale::whereDate('created_at', $date)->where('user_id', Auth::id())->sum('grand_total');
            $purchase_generated[$start] = Purchase::whereDate('created_at', $date)->where('user_id', Auth::id())->count();
            $purchase_grand_total[$start] = Purchase::whereDate('created_at', $date)->where('user_id', Auth::id())->sum('grand_total');
            $quotation_generated[$start] = Quotation::whereDate('created_at', $date)->where('user_id', Auth::id())->count();
            $quotation_grand_total[$start] = Quotation::whereDate('created_at', $date)->where('user_id', Auth::id())->sum('grand_total');
            $start++;
        }
        $start_day = date('w', strtotime($year.'-'.$month.'-01')) + 1;
        $prev_year = date('Y', strtotime('-1 month', strtotime($year.'-'.$month.'-01')));
        $prev_month = date('m', strtotime('-1 month', strtotime($year.'-'.$month.'-01')));
        $next_year = date('Y', strtotime('+1 month', strtotime($year.'-'.$month.'-01')));
        $next_month = date('m', strtotime('+1 month', strtotime($year.'-'.$month.'-01')));
        return view('user.my_transaction', compact('start_day', 'year', 'month', 'number_of_day', 'prev_year', 'prev_month', 'next_year', 'next_month', 'sale_generated', 'sale_grand_total','purchase_generated', 'purchase_grand_total','quotation_generated', 'quotation_grand_total'));
    }
}

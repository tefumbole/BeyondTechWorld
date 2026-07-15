<?php

namespace App\Http\Controllers;

use App\Booking;
use App\BookingProduct;
use App\StockDuration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redirect;
use App\Customer;
use App\CustomerGroup;
use App\Warehouse;
use App\Biller;
use App\Brand;
use App\Category;
use App\Product;
use App\Unit;
use App\Tax;
use App\Sale;
use App\Delivery;
use App\PosSetting;
use App\Product_Sale;
use App\Product_Warehouse;
use App\Payment;
use App\Account;
use App\Coupon;
use App\GiftCard;
use App\PaymentWithCheque;
use App\PaymentWithGiftCard;
use App\PaymentWithCreditCard;
use App\PaymentWithPaypal;
use App\User;
use App\Variant;
use App\ProductVariant;
use App\CashRegister;
use App\Returns;
use App\Expense;
use App\ProductPurchase;
use App\ProductBatch;
use App\Purchase;
use App\RewardPointSetting;
use DB;
use Illuminate\Support\Facades\Storage;
use PDF;
use App\GeneralSetting;
use Illuminate\Support\Facades\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Stripe\Stripe;
use NumberToWords\NumberToWords;
use Auth;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Mail\UserNotification;
use Illuminate\Support\Facades\Mail;
use Srmklive\PayPal\Services\ExpressCheckout;
use Srmklive\PayPal\Services\AdaptivePayments;
use GeniusTS\HijriDate\Date;
use DateTime;
use Illuminate\Support\Facades\Validator;
use function GuzzleHttp\Promise\rejection_for;

class BookingController extends Controller
{
    public function sendWhatsapp(Request $request)
    {
        $data = $request->all();
        return $this->genPDFInvoice($data['booking_id']);
    }

    public function genPDFInvoice($id)
    {
        $role = Role::find(Auth::user()->role_id);
        $permissions = Role::findByName($role->name)->permissions;

        foreach ($permissions as $permission) {
            $all_permission[] = $permission->name;
        }
        $lims_sale_data = Booking::find($id);
        $lims_product_sale_data = BookingProduct::where('booking_id', $id)->get();
        $lims_biller_data = Biller::find($lims_sale_data->biller_id);
        $lims_warehouse_data = Warehouse::find($lims_sale_data->warehouse_id);
        $lims_customer_data = Customer::find($lims_sale_data->customer_id);
        $lims_payment_data = Payment::where('booking_id', $id)->get();
        $lims_payment_debit_data = Payment::where('debit_booking_id', $id)->get();
        $lims_account_data = null;
        $lims_account_data_debit = null;
        $lims_account_data_cradit = null;

        if(isset($lims_payment_data[0])) {
            $lims_account_data_cradit = Account::with('departments')->where('id', $lims_payment_data[0]->account_id)->first();
        }
        if(isset($lims_payment_debit_data[0])) {
            $lims_account_data_debit = Account::with('departments')->where('id', $lims_payment_debit_data[0]->account_id)->first();
        }

        $setting = GeneralSetting::first();
        $header = $setting->email_header;
        $footer = $setting->email_footer;
        $water_mark = $setting->email_water_mark;

        $numberToWords = new NumberToWords();
        if(\App::getLocale() == 'ar' || \App::getLocale() == 'hi' || \App::getLocale() == 'vi' || \App::getLocale() == 'en-gb')
            $numberTransformer = $numberToWords->getNumberTransformer('en');
        else
            $numberTransformer = $numberToWords->getNumberTransformer(\App::getLocale());
        $numberInWords = $numberTransformer->toWords($lims_sale_data->grand_total);

        $data = [
            'header' => $header,
            'footer' => $footer,
            'water_mark' => $water_mark,
            'all_permission' => $all_permission,
            'lims_account_data_cradit' => $lims_account_data_cradit,
            'lims_account_data_debit' => $lims_account_data_debit,
            'lims_sale_data' => $lims_sale_data,
            'lims_product_sale_data' => $lims_product_sale_data,
            'lims_biller_data' => $lims_biller_data,
            'lims_warehouse_data' => $lims_warehouse_data,
            'lims_customer_data' => $lims_customer_data,
            'lims_payment_data' => $lims_payment_data,
            'numberInWords' => $numberInWords
        ];
//        return view('pdf.booking_pdf', $data);
        $pdf = PDF::loadView('pdf.booking_pdf', $data);

        $content = $pdf->download()->getOriginalContent();

        Storage::put('public/booking/booking_invoice.pdf',$content);
        $path = storage_path('app/public/booking/booking_invoice.pdf');

        $message = 'Booking notification sent successfully';
        try{
            $this->wpPDFMessage($path, $lims_customer_data, 'booking_invoice.pdf');
        }
        catch(\Exception $e){
            $message = 'Booking notification not sent: ' . $e->getMessage();
        }
        return back()->with('message', $message);
    }

    public function __construct() {

        $this->middleware(function ($request, $next) {
            $role = Role::find(\Illuminate\Support\Facades\Auth::user()->role_id);
            $permissions = Role::findByName($role->name)->permissions;

            foreach ($permissions as $permission) {
                $all_permission[] = $permission->name;
            }
            View::share ( 'all_permission', $all_permission);

            return $next($request);
        });
    }
    public function index(Request $request)
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('booking_index')) {
            $permissions = Role::findByName($role->name)->permissions;
            foreach ($permissions as $permission)
                $all_permission[] = $permission->name;
            if(empty($all_permission))
                $all_permission[] = 'dummy text';

            if($request->input('warehouse_id'))
                $warehouse_id = $request->input('warehouse_id');
            else
                $warehouse_id = 0;

            if($request->input('starting_date')) {
                $starting_date = $request->input('starting_date');
                $ending_date = $request->input('ending_date');
            }
            else {
                $starting_date = date("Y-m-d", strtotime(date('Y-m-d', strtotime('-1 year', strtotime(date('Y-m-d') )))));
                $ending_date = date("Y-m-d");
            }

            $lims_gift_card_list = GiftCard::where("is_active", true)->get();
            $lims_pos_setting_data = PosSetting::latest()->first();
            $lims_reward_point_setting_data = RewardPointSetting::latest()->first();
            $lims_warehouse_list = Warehouse::where('is_active', true)->get();
            $lims_account_list = Account::with('departments')->where('is_active', true)->get();

            return view('booking.index',compact('starting_date', 'ending_date', 'warehouse_id', 'lims_gift_card_list', 'lims_pos_setting_data', 'lims_reward_point_setting_data', 'lims_account_list', 'lims_warehouse_list', 'all_permission'));
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    public function onlineIndex(Request $request)
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('sales-index')) {
            $permissions = Role::findByName($role->name)->permissions;
            foreach ($permissions as $permission)
                $all_permission[] = $permission->name;
            if(empty($all_permission))
                $all_permission[] = 'dummy text';

            if($request->input('warehouse_id'))
                $warehouse_id = $request->input('warehouse_id');
            else
                $warehouse_id = 0;

            if($request->input('starting_date')) {
                $starting_date = $request->input('starting_date');
                $ending_date = $request->input('ending_date');
            }
            else {
                $starting_date = date("Y-m-d", strtotime(date('Y-m-d', strtotime('-1 year', strtotime(date('Y-m-d') )))));
                $ending_date = date("Y-m-d");
            }

            $lims_gift_card_list = GiftCard::where("is_active", true)->get();
            $lims_pos_setting_data = PosSetting::latest()->first();
            $lims_reward_point_setting_data = RewardPointSetting::latest()->first();
            $lims_warehouse_list = Warehouse::where('is_active', true)->get();
            $lims_account_list = Account::with('departments')->where('is_active', true)->get();

            return view('booking.online-index',compact('starting_date', 'ending_date', 'warehouse_id', 'lims_gift_card_list', 'lims_pos_setting_data', 'lims_reward_point_setting_data', 'lims_account_list', 'lims_warehouse_list', 'all_permission'));
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }


    public function saleData(Request $request)
    {
        $columns = array(
            1 => 'created_at',
            2 => 'reference_no',
            7 => 'grand_total',
            8 => 'paid_amount',
        );

        $warehouse_id = $request->input('warehouse_id');

        if(Auth::user()->role_id > 2 && config('staff_access') == 'own')
            $totalData = Booking::where('user_id', Auth::id())
                ->where('is_frontend', false)
                ->whereDate('created_at', '>=' ,$request->input('starting_date'))
                ->whereDate('created_at', '<=' ,$request->input('ending_date'))
                ->count();
        elseif($warehouse_id != 0)
            $totalData = Booking::where('warehouse_id', $warehouse_id)->where('is_frontend', false)->whereDate('created_at', '>=' ,$request->input('starting_date'))->whereDate('created_at', '<=' ,$request->input('ending_date'))->count();
        else
            $totalData = Booking::whereDate('created_at', '>=' ,$request->input('starting_date'))->whereDate('created_at', '<=' ,$request->input('ending_date'))->where('is_frontend', false)->count();

        $totalFiltered = $totalData;
        if($request->input('length') != -1)
            $limit = $request->input('length');
        else
            $limit = $totalData;
        $start = $request->input('start');
        $order = 'sales.'.$columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');
        if(empty($request->input('search.value'))) {
            if(Auth::user()->role_id > 2 && config('staff_access') == 'own') {
                $sales = Booking::with('biller', 'customer', 'warehouse', 'user', 'contract')
                    ->where('is_frontend', false)
                    ->where('user_id', Auth::id())
                    ->whereDate('created_at', '>=', $request->input('starting_date'))
                    ->whereDate('created_at', '<=', $request->input('ending_date'))
                    ->offset($start)
                    ->limit($limit)
                    ->orderBy('created_at', $dir)
                    ->get();
            } elseif($warehouse_id != 0) {

                $sales = Booking::with('biller', 'customer', 'warehouse', 'user', 'contract')
                    ->where('is_frontend', false)
                    ->where('warehouse_id', $warehouse_id)
                    ->whereDate('created_at', '>=' ,$request->input('starting_date'))
                    ->whereDate('created_at', '<=' ,$request->input('ending_date'))
                    ->offset($start)
                    ->limit($limit)
                    ->orderBy('created_at', $dir)
                    ->get();

            } else {
                $sales = Booking::with('biller', 'customer', 'warehouse', 'user', 'contract')
                    ->where('is_frontend', false)
                    ->whereDate('created_at', '>=' ,$request->input('starting_date'))
                    ->whereDate('created_at', '<=' ,$request->input('ending_date'))
                    ->offset($start)
                    ->limit($limit)
                    ->orderBy('created_at', $dir)
                    ->get();
            }
        }
        else
        {
            $search = $request->input('search.value');
            if(Auth::user()->role_id > 2 && config('staff_access') == 'own') {
                $sales =  Booking::select('bookings.*')
                    ->where('is_frontend', false)
                    ->with('biller', 'customer', 'warehouse', 'user', 'contract')
                    ->join('customers', 'bookings.customer_id', '=', 'customers.id')
                    ->join('billers', 'bookings.biller_id', '=', 'billers.id')
                    ->whereDate('bookings.created_at', '=' , date('Y-m-d', strtotime(str_replace('/', '-', $search))))
                    ->where('bookings.user_id', Auth::id())
                    ->orwhere([
                        ['bookings.reference_no', 'LIKE', "%{$search}%"],
                        ['bookings.user_id', Auth::id()]
                    ])
                    ->orwhere([
                        ['customers.name', 'LIKE', "%{$search}%"],
                        ['bookings.user_id', Auth::id()]
                    ])
                    ->orwhere([
                        ['customers.phone_number', 'LIKE', "%{$search}%"],
                        ['bookings.user_id', Auth::id()]
                    ])
                    ->orwhere([
                        ['billers.name', 'LIKE', "%{$search}%"],
                        ['bookings.user_id', Auth::id()]
                    ])
                    ->offset($start)
                    ->limit($limit)
                    ->orderBy('created_at', $dir)->get();

                $totalFiltered = Booking::
                join('customers', 'bookings.customer_id', '=', 'customers.id')
                    ->join('billers', 'bookings.biller_id', '=', 'billers.id')
                    ->whereDate('bookings.created_at', '=' , date('Y-m-d', strtotime(str_replace('/', '-', $search))))
                    ->where('bookings.user_id', Auth::id())
                    ->where('bookings.is_frontend', false)
                    ->orwhere([
                        ['bookings.reference_no', 'LIKE', "%{$search}%"],
                        ['bookings.user_id', Auth::id()]
                    ])
                    ->orwhere([
                        ['customers.name', 'LIKE', "%{$search}%"],
                        ['bookings.user_id', Auth::id()]
                    ])
                    ->orwhere([
                        ['customers.phone_number', 'LIKE', "%{$search}%"],
                        ['bookings.user_id', Auth::id()]
                    ])
                    ->orwhere([
                        ['billers.name', 'LIKE', "%{$search}%"],
                        ['sales.user_id', Auth::id()]
                    ])
                    ->count();
            }
            else {
                $sales =  Booking::select('bookings.*')
                    ->with('biller', 'customer', 'warehouse', 'user', 'contract')
                    ->join('customers', 'bookings.customer_id', '=', 'customers.id')
                    ->join('billers', 'bookings.biller_id', '=', 'billers.id')
                    ->whereDate('bookings.created_at', '=' , date('Y-m-d', strtotime(str_replace('/', '-', $search))))
                    ->where('bookings.user_id', Auth::id())
                    ->where('bookings.is_frontend', false)
                    ->orwhere('bookings.reference_no', 'LIKE', "%{$search}%")
                    ->orwhere('customers.name', 'LIKE', "%{$search}%")
                    ->orwhere('customers.phone_number', 'LIKE', "%{$search}%")
                    ->orwhere('billers.name', 'LIKE', "%{$search}%")
                    ->offset($start)
                    ->limit($limit)
                    ->orderBy('created_at', $dir)
                    ->get();
                $totalFiltered = Booking::
                join('customers', 'bookings.customer_id', '=', 'customers.id')
                    ->join('billers', 'bookings.biller_id', '=', 'billers.id')
                    ->whereDate('bookings.created_at', '=' , date('Y-m-d', strtotime(str_replace('/', '-', $search))))
                    ->where('bookings.user_id', Auth::id())
                    ->where('bookings.is_frontend', false)
                    ->orwhere('bookings.reference_no', 'LIKE', "%{$search}%")
                    ->orwhere('customers.name', 'LIKE', "%{$search}%")
                    ->orwhere('customers.phone_number', 'LIKE', "%{$search}%")
                    ->orwhere('billers.name', 'LIKE', "%{$search}%")
                    ->count();
            }
        }
        $data = array();
        if(!empty($sales))
        {
            foreach ($sales as $key=>$sale) {
                $nestedData['id'] = $sale->id;
                $nestedData['key'] = $key;
                $nestedData['date'] = date(config('date_format'), strtotime($sale->created_at->toDateString()));
                $nestedData['reference_no'] = $sale->reference_no;
                $nestedData['biller'] = $sale->biller->name;
                $nestedData['customer'] = $sale->customer->name . '<input type="hidden" class="deposit" value="' . ($sale->customer->deposit - $sale->customer->expense) . '" />' . '<input type="hidden" class="points" value="' . $sale->customer->points . '" />';

                if ($sale->booking_status == 1) {
                    $nestedData['booking_status'] = '<div class="badge badge-success">' . trans('file.Completed') . '</div>';
                    $booking_status = trans('file.Completed');
                } elseif ($sale->booking_status == 2) {
                    $nestedData['booking_status'] = '<div class="badge badge-danger">' . trans('file.Pending') . '</div>';
                    $booking_status = trans('file.Pending');
                } elseif ($sale->booking_status == 3) {
                    $nestedData['booking_status'] = '<div class="badge badge-primary">' . trans('file.Return') . '</div>';
                    $booking_status = trans('file.Pending');
                } elseif ($sale->booking_status == 4) {
                    $nestedData['booking_status'] = '<div class="badge badge-info">Partial Return</div>';
                    $booking_status = 'Partial Return';
                } else {
                    $nestedData['booking_status'] = '<div class="badge badge-warning">' . trans('file.Draft') . '</div>';
                    $booking_status = trans('file.Draft');
                }

                if ($sale->payment_status == 1)
                    $nestedData['payment_status'] = '<div class="badge badge-danger">' . trans('file.Pending') . '</div>';
                elseif ($sale->payment_status == 2)
                    $nestedData['payment_status'] = '<div class="badge badge-danger">' . trans('file.Due') . '</div>';
                elseif ($sale->payment_status == 3)
                    $nestedData['payment_status'] = '<div class="badge badge-warning">' . trans('file.Partial') . '</div>';
                else
                    $nestedData['payment_status'] = '<div class="badge badge-success">' . trans('file.Paid') . '</div>';

                $nestedData['grand_total'] = number_format($sale->grand_total, 2);
                $nestedData['paid_amount'] = number_format($sale->paid_amount, 2);
                $nestedData['due'] = number_format($sale->grand_total - $sale->paid_amount, 2);
                $nestedData['options'] = '<div class="btn-group">
                            <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">' . trans("file.action") . '
                              <span class="caret"></span>
                              <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu">
                                <li><a href="' . route('booking.invoice', $sale->id) . '" class="btn btn-link"><i class="fa fa-copy"></i> ' . trans('file.Generate Invoice') . '</a></li>
                                <li>
                                    <button type="button" class="btn btn-link view"><i class="fa fa-eye"></i> ' . trans('file.View') . '</button>
                                </li>';
                if(in_array("booking_edit", $request['all_permission'])) {
                    if ($sale->booking_status != 3) {
                        $nestedData['options'] .= '<li>
                                        <a href="' . route('booking.edit', $sale->id) . '" class="btn btn-link"><i class="dripicons-document-edit"></i> ' . trans('file.edit') . '</a>
                                        </li>';
                    }
                }
                if(in_array("booking_create", $request['all_permission'])) {
                    $nestedData['options'] .= '<li>
                                        <a href="' . route('booking.clone', $sale->id) . '" class="btn btn-link"><i class="dripicons-copy"></i> Clone Booking</a>
                                        </li>';
                }
                if ($sale->contract && !$sale->contract->signed_at) {
                    $nestedData['options'] .= '<li>
                        <form action="' . route('booking.contract.resend', $sale->contract->id) . '" method="POST" class="d-inline">
                            ' . csrf_field() . '
                            <button type="submit" class="btn btn-link" onclick="return confirm(\'Resend signature link via WhatsApp?\');"><i class="fa fa-whatsapp"></i> Resend for Signature</button>
                        </form>
                    </li>';
                }
                $nestedData['options'] .= '<li>
                    <a href="' . route('booking.goods-received.generate', $sale->id) . '" class="btn btn-link" onclick="return confirm(\'Generate goods delivery note for this booking?\');"><i class="fa fa-truck"></i> Generate Goods Delivery Note</a>
                </li>';
                if(in_array("booking_index", $request['all_permission'])) {
                    $nestedData['options'] .= '<li>
                        <button type="button" class="schedule-reminder btn btn-link" data-id="' . $sale->id . '" data-ref="' . e($sale->reference_no) . '" data-toggle="modal" data-target="#schedule-reminder"><i class="fa fa-bell"></i> Schedule Reminder</button>
                    </li>';
                }
                if(in_array("booking_return", $request['all_permission'])) {
                    if ($sale->booking_status == 1 || $sale->booking_status == 4) {
                        $nestedData['options'] .= '<li>
                                                    <a href="' . route('booking.return', $sale->id) . '" class="btn btn-link"><i class="dripicons-return"></i> ' . trans('file.Return') . '</a>
                                                    </li>';
                    }
                }
                $nestedData['options'] .=
                    '<li>
                        <button type="button" class="add-payment btn btn-link" data-id = "'.$sale->id.'" data-toggle="modal" data-target="#add-payment"><i class="fa fa-plus"></i> '.trans('file.Add Payment').'</button>
                    </li>
                    <li>
                        <button type="button" class="get-payment btn btn-link" data-id = "'.$sale->id.'"><i class="fa fa-money"></i> '.trans('file.View Payment').'</button>
                    </li>';
                if(in_array("booking_delete", $request['all_permission']))
                    $nestedData['options'] .= \Form::open(["route" => ["booking.destroy", $sale->id], "method" => "DELETE"] ).'
                            <li>
                              <button type="submit" class="btn btn-link" onclick="return confirmDelete()"><i class="dripicons-trash"></i> '.trans("file.delete").'</button>
                            </li>'.\Form::close().'
                        </ul>
                    </div>';
                // data for Booking Details by one click
                $coupon = Coupon::find($sale->coupon_id);
                if($coupon)
                    $coupon_code = $coupon->code;
                else
                    $coupon_code = null;

                $nestedData['sale'] = array( '[ "'.date(config('date_format'), strtotime($sale->created_at->toDateString())).'"', ' "'.$sale->reference_no.'"', ' "'.$booking_status.'"', ' "'.$sale->biller->name.'"', ' "'.$sale->biller->company_name.'"', ' "'.$sale->biller->email.'"', ' "'.$sale->biller->phone_number.'"', ' "'.$sale->biller->address.'"', ' "'.$sale->biller->city.'"', ' "'.$sale->customer->name.'"', ' "'.$sale->customer->phone_number.'"', ' "'.$sale->customer->address.'"', ' "'.$sale->customer->city.'"', ' "'.$sale->id.'"', ' "'.$sale->total_tax.'"', ' "'.$sale->total_discount.'"', ' "'.$sale->total_price.'"', ' "'.$sale->order_tax.'"', ' "'.$sale->order_tax_rate.'"', ' "'.$sale->order_discount.'"', ' "'.$sale->shipping_cost.'"', ' "'.$sale->grand_total.'"', ' "'.$sale->paid_amount.'"', ' "'.preg_replace('/[\n\r]/', "<br>", $sale->booking_note).'"', ' "'.preg_replace('/[\n\r]/', "<br>", $sale->staff_note).'"', ' "'.$sale->user->name.'"', ' "'.$sale->user->email.'"', ' "'.$sale->warehouse->name.'"', ' "'.$coupon_code.'"', ' "'.$sale->coupon_discount.'"]'
                );
                $data[] = $nestedData;
            }
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data"            => $data
        );

        echo json_encode($json_data);
    }

    public function saleDataOnline(Request $request)
    {
        $columns = array(
            1 => 'created_at',
            2 => 'reference_no',
            7 => 'grand_total',
            8 => 'paid_amount',
        );

        $warehouse_id = $request->input('warehouse_id');

        if(Auth::user()->role_id > 2 && config('staff_access') == 'own')
            $totalData = Booking::where('user_id', Auth::id())
                ->where('is_frontend', true)
                ->whereDate('created_at', '>=' ,$request->input('starting_date'))
                ->whereDate('created_at', '<=' ,$request->input('ending_date'))
                ->count();
        elseif($warehouse_id != 0)
            $totalData = Booking::where('warehouse_id', $warehouse_id)->where('is_frontend', true)->whereDate('created_at', '>=' ,$request->input('starting_date'))->whereDate('created_at', '<=' ,$request->input('ending_date'))->count();
        else
            $totalData = Booking::whereDate('created_at', '>=' ,$request->input('starting_date'))->whereDate('created_at', '<=' ,$request->input('ending_date'))->where('is_frontend', true)->count();

        $totalFiltered = $totalData;
        if($request->input('length') != -1)
            $limit = $request->input('length');
        else
            $limit = $totalData;
        $start = $request->input('start');
        $order = 'sales.'.$columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');
        if(empty($request->input('search.value'))) {
            if(Auth::user()->role_id > 2 && config('staff_access') == 'own') {
                $sales = Booking::with('biller', 'customer', 'warehouse', 'user', 'contract')
                    ->where('user_id', Auth::id())
                    ->where('is_frontend', true)
                    ->whereDate('created_at', '>=', $request->input('starting_date'))
                    ->whereDate('created_at', '<=', $request->input('ending_date'))
                    ->offset($start)
                    ->limit($limit)
                    ->orderBy('created_at', $dir)
                    ->get();
            } elseif($warehouse_id != 0) {

                $sales = Booking::with('biller', 'customer', 'warehouse', 'user', 'contract')
                    ->where('warehouse_id', $warehouse_id)
                    ->where('is_frontend', true)
                    ->whereDate('created_at', '>=' ,$request->input('starting_date'))
                    ->whereDate('created_at', '<=' ,$request->input('ending_date'))
                    ->offset($start)
                    ->limit($limit)
                    ->orderBy('created_at', $dir)
                    ->get();

            } else {
                $sales = Booking::with('biller', 'customer', 'warehouse', 'user', 'contract')
                    ->where('is_frontend', true)
                    ->whereDate('created_at', '>=' ,$request->input('starting_date'))
                    ->whereDate('created_at', '<=' ,$request->input('ending_date'))
                    ->offset($start)
                    ->limit($limit)
                    ->orderBy('created_at', $dir)
                    ->get();
            }
        }
        else
        {
            $search = $request->input('search.value');
            if(Auth::user()->role_id > 2 && config('staff_access') == 'own') {
                $sales =  Booking::select('bookings.*')
                    ->where('is_frontend', true)
                    ->with('biller', 'customer', 'warehouse', 'user', 'contract')
                    ->join('customers', 'bookings.customer_id', '=', 'customers.id')
                    ->join('billers', 'bookings.biller_id', '=', 'billers.id')
                    ->whereDate('bookings.created_at', '=' , date('Y-m-d', strtotime(str_replace('/', '-', $search))))
                    ->where('bookings.user_id', Auth::id())
                    ->orwhere([
                        ['bookings.reference_no', 'LIKE', "%{$search}%"],
                        ['bookings.user_id', Auth::id()]
                    ])
                    ->orwhere([
                        ['customers.name', 'LIKE', "%{$search}%"],
                        ['bookings.user_id', Auth::id()]
                    ])
                    ->orwhere([
                        ['customers.phone_number', 'LIKE', "%{$search}%"],
                        ['bookings.user_id', Auth::id()]
                    ])
                    ->orwhere([
                        ['billers.name', 'LIKE', "%{$search}%"],
                        ['bookings.user_id', Auth::id()]
                    ])
                    ->offset($start)
                    ->limit($limit)
                    ->orderBy('created_at', $dir)->get();

                $totalFiltered = Booking::
                join('customers', 'bookings.customer_id', '=', 'customers.id')
                    ->join('billers', 'bookings.biller_id', '=', 'billers.id')
                    ->whereDate('bookings.created_at', '=' , date('Y-m-d', strtotime(str_replace('/', '-', $search))))
                    ->where('bookings.user_id', Auth::id())
                    ->where('bookings.is_frontend', true)
                    ->orwhere([
                        ['bookings.reference_no', 'LIKE', "%{$search}%"],
                        ['bookings.user_id', Auth::id()]
                    ])
                    ->orwhere([
                        ['customers.name', 'LIKE', "%{$search}%"],
                        ['bookings.user_id', Auth::id()]
                    ])
                    ->orwhere([
                        ['customers.phone_number', 'LIKE', "%{$search}%"],
                        ['bookings.user_id', Auth::id()]
                    ])
                    ->orwhere([
                        ['billers.name', 'LIKE', "%{$search}%"],
                        ['sales.user_id', Auth::id()]
                    ])
                    ->count();
            }
            else {
                $sales =  Booking::select('bookings.*')
                    ->where('bookings.is_frontend', true)
                    ->with('biller', 'customer', 'warehouse', 'user', 'contract')
                    ->join('customers', 'bookings.customer_id', '=', 'customers.id')
                    ->join('billers', 'bookings.biller_id', '=', 'billers.id')
                    ->whereDate('bookings.created_at', '=' , date('Y-m-d', strtotime(str_replace('/', '-', $search))))
                    ->where('bookings.user_id', Auth::id())
                    ->orwhere('bookings.reference_no', 'LIKE', "%{$search}%")
                    ->orwhere('customers.name', 'LIKE', "%{$search}%")
                    ->orwhere('customers.phone_number', 'LIKE', "%{$search}%")
                    ->orwhere('billers.name', 'LIKE', "%{$search}%")
                    ->offset($start)
                    ->limit($limit)
                    ->orderBy('created_at', $dir)
                    ->get();
                $totalFiltered = Booking::
                join('customers', 'bookings.customer_id', '=', 'customers.id')
                    ->join('billers', 'bookings.biller_id', '=', 'billers.id')
                    ->whereDate('bookings.created_at', '=' , date('Y-m-d', strtotime(str_replace('/', '-', $search))))
                    ->where('bookings.user_id', Auth::id())
                    ->where('bookings.is_frontend', true)
                    ->orwhere('bookings.reference_no', 'LIKE', "%{$search}%")
                    ->orwhere('customers.name', 'LIKE', "%{$search}%")
                    ->orwhere('customers.phone_number', 'LIKE', "%{$search}%")
                    ->orwhere('billers.name', 'LIKE', "%{$search}%")
                    ->count();
            }
        }
        $data = array();
        if(!empty($sales))
        {
            foreach ($sales as $key=>$sale) {
                $nestedData['id'] = $sale->id;
                $nestedData['key'] = $key;
                $nestedData['date'] = date(config('date_format'), strtotime($sale->created_at->toDateString()));
                $nestedData['reference_no'] = $sale->reference_no;
                $nestedData['biller'] = $sale->biller->name;
                $nestedData['customer'] = $sale->customer->name . '<input type="hidden" class="deposit" value="' . ($sale->customer->deposit - $sale->customer->expense) . '" />' . '<input type="hidden" class="points" value="' . $sale->customer->points . '" />';
                if ($sale->payment_method == 'COD') {
                    $nestedData['paying_method'] = '<div class="badge badge-warning">' . $sale->payment_method . '</div>';
                } else {
                    $nestedData['paying_method'] = '<div class="badge badge-success">' . $sale->payment_method . '</div>';
                }

                if ($sale->booking_status == 1) {
                    $nestedData['booking_status'] = '<div class="badge badge-success">' . trans('file.Completed') . '</div>';
                    $booking_status = trans('file.Completed');
                } elseif ($sale->booking_status == 2) {
                    $nestedData['booking_status'] = '<div class="badge badge-danger">' . trans('file.Pending') . '</div>';
                    $booking_status = trans('file.Pending');
                } elseif ($sale->booking_status == 3) {
                    $nestedData['booking_status'] = '<div class="badge badge-primary"> Returned </div>';
                    $booking_status = trans('file.Pending');
                } elseif ($sale->booking_status == 4) {
                    $nestedData['booking_status'] = '<div class="badge badge-info">Partial Return</div>';
                    $booking_status = 'Partial Return';
                } else {
                    $nestedData['booking_status'] = '<div class="badge badge-warning">' . trans('file.Pending') . '</div>';
                    $booking_status = trans('file.Draft');
                }

                if ($sale->payment_status == 1)
                    $nestedData['payment_status'] = '<div class="badge badge-danger">' . trans('file.Pending') . '</div>';
                elseif ($sale->payment_status == 2)
                    $nestedData['payment_status'] = '<div class="badge badge-danger">' . trans('file.Due') . '</div>';
                elseif ($sale->payment_status == 3)
                    $nestedData['payment_status'] = '<div class="badge badge-warning">' . trans('file.Partial') . '</div>';
                else
                    if ($sale->payment_method == 'COD') {
                        $nestedData['payment_status'] = '<div class="badge badge-success">paid on delivery</div>';
                    } else {
                        $nestedData['payment_status'] = '<div class="badge badge-success">' . trans('file.Paid') . '</div>';
                    }

                $nestedData['grand_total'] = number_format($sale->grand_total, 2);
                $nestedData['paid_amount'] = number_format($sale->paid_amount, 2);
                $nestedData['due'] = number_format($sale->grand_total - $sale->paid_amount, 2);
                $nestedData['options'] = '<div class="btn-group">
                            <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">' . trans("file.action") . '
                              <span class="caret"></span>
                              <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu">
                                <li><a href="' . route('booking.invoice', $sale->id) . '" class="btn btn-link"><i class="fa fa-copy"></i> ' . trans('file.Generate Invoice') . '</a></li>
                                <li>
                                    <button type="button" class="btn btn-link view"><i class="fa fa-eye"></i> ' . trans('file.View') . '</button>
                                </li>';
                if(in_array("booking_edit", $request['all_permission'])) {
                    if ($sale->booking_status != 3) {
                        $nestedData['options'] .= '<li>
                                        <a href="' . route('booking.edit', $sale->id) . '" class="btn btn-link"><i class="dripicons-document-edit"></i> ' . trans('file.edit') . '</a>
                                        </li>';
                    }
                }
                if(in_array("booking_delete", $request['all_permission']))
                    $nestedData['options'] .= \Form::open(["route" => ["booking.destroy", $sale->id], "method" => "DELETE"] ).'
                            <li>
                              <button type="submit" class="btn btn-link" onclick="return confirmDelete()"><i class="dripicons-trash"></i> '.trans("file.delete").'</button>
                            </li>'.\Form::close().'
                        </ul>
                    </div>';
                // data for Booking Details by one click
                $coupon = Coupon::find($sale->coupon_id);
                if($coupon)
                    $coupon_code = $coupon->code;
                else
                    $coupon_code = null;

                $nestedData['sale'] = array( '[ "'.date(config('date_format'), strtotime($sale->created_at->toDateString())).'"', ' "'.$sale->reference_no.'"', ' "'.$booking_status.'"', ' "'.@$sale->biller->name.'"', ' "'.@$sale->biller->company_name.'"', ' "'.@$sale->biller->email.'"', ' "'.@$sale->biller->phone_number.'"', ' "'.@$sale->biller->address.'"', ' "'.@$sale->biller->city.'"', ' "'.@$sale->customer->name.'"', ' "'.$sale->customer->phone_number.'"', ' "'.$sale->customer->address.'"', ' "'.$sale->customer->city.'"', ' "'.$sale->id.'"', ' "'.$sale->total_tax.'"', ' "'.$sale->total_discount.'"', ' "'.$sale->total_price.'"', ' "'.$sale->order_tax.'"', ' "'.$sale->order_tax_rate.'"', ' "'.$sale->order_discount.'"', ' "'.$sale->shipping_cost.'"', ' "'.$sale->grand_total.'"', ' "'.$sale->paid_amount.'"', ' "'.preg_replace('/[\n\r]/', "<br>", $sale->booking_note).'"', ' "'.preg_replace('/[\n\r]/', "<br>", $sale->staff_note).'"', ' "'.@$sale->user->name.'"', ' "'.@$sale->user->email.'"', ' "'.$sale->warehouse->name.'"', ' "'.$coupon_code.'"', ' "'.$sale->coupon_discount.'"]'
                );
                $data[] = $nestedData;
            }
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data"            => $data
        );

        echo json_encode($json_data);
    }

    public function create()
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('booking_create')) {
            $lims_customer_list = Customer::orderBy('name', 'asc')->get();
            if(Auth::user()->role_id > 2) {
                $lims_warehouse_list = Warehouse::where([
                    ['is_active', true],
                    ['id', Auth::user()->warehouse_id]
                ])->get();
                $lims_biller_list = Biller::where([
                    ['is_active', true],
                    ['id', Auth::user()->biller_id]
                ])->get();
            }
            else {
                $lims_warehouse_list = Warehouse::where('is_active', true)->get();
                $lims_biller_list = Biller::where('is_active', true)->get();
            }
            $permissions = $role->permissions;
            foreach ($permissions as $permission) {
                $all_permission[] = $permission->name;
            }

            $lims_tax_list = Tax::where('is_active', true)->get();
            $lims_pos_setting_data = PosSetting::latest()->first();
            $lims_reward_point_setting_data = RewardPointSetting::latest()->first();
            $lims_customer_group_all = CustomerGroup::where('is_active', true)->get();
            $lims_category_list = Category::where('is_active', true)->get();
            $cloneBooking = null;
            $cloneLines = [];

            return view('booking.create',compact('all_permission', 'lims_customer_list', 'lims_warehouse_list', 'lims_biller_list', 'lims_pos_setting_data', 'lims_tax_list', 'lims_reward_point_setting_data', 'lims_customer_group_all', 'lims_category_list', 'cloneBooking', 'cloneLines'));
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    public function cloneBooking($id)
    {
        $role = Role::find(Auth::user()->role_id);
        if (!$role->hasPermissionTo('booking_create')) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $cloneBooking = Booking::with(['bookingProduct.product'])->findOrFail($id);
        $cloneLines = [];

        foreach ($cloneBooking->bookingProduct as $bookingProduct) {
            $product = $bookingProduct->product;
            if (!$product) {
                continue;
            }

            $code = $product->code;
            if ($bookingProduct->variant_id) {
                $variant = ProductVariant::select('item_code')
                    ->FindExactProduct($bookingProduct->product_id, $bookingProduct->variant_id)
                    ->first();
                if ($variant) {
                    $code = $variant->item_code;
                }
            }

            $cloneLines[] = [
                'code' => $code,
                'qty' => $bookingProduct->qty,
                'booking_method' => (string) $bookingProduct->booking_method,
                'number_duration' => $bookingProduct->number_duration,
                'start' => $bookingProduct->start ? date('Y-m-d H:i', strtotime($bookingProduct->start)) : '',
                'end' => $bookingProduct->end ? date('Y-m-d H:i', strtotime($bookingProduct->end)) : '',
                'net_unit_price' => $bookingProduct->net_unit_price,
            ];
        }

        $lims_customer_list = Customer::orderBy('name', 'asc')->get();
        if (Auth::user()->role_id > 2) {
            $lims_warehouse_list = Warehouse::where([
                ['is_active', true],
                ['id', Auth::user()->warehouse_id]
            ])->get();
            $lims_biller_list = Biller::where([
                ['is_active', true],
                ['id', Auth::user()->biller_id]
            ])->get();
        } else {
            $lims_warehouse_list = Warehouse::where('is_active', true)->get();
            $lims_biller_list = Biller::where('is_active', true)->get();
        }

        $permissions = $role->permissions;
        foreach ($permissions as $permission) {
            $all_permission[] = $permission->name;
        }

        $lims_tax_list = Tax::where('is_active', true)->get();
        $lims_pos_setting_data = PosSetting::latest()->first();
        $lims_reward_point_setting_data = RewardPointSetting::latest()->first();
        $lims_customer_group_all = CustomerGroup::where('is_active', true)->get();
        $lims_category_list = Category::where('is_active', true)->get();

        return view('booking.create', compact(
            'all_permission',
            'lims_customer_list',
            'lims_warehouse_list',
            'lims_biller_list',
            'lims_pos_setting_data',
            'lims_tax_list',
            'lims_reward_point_setting_data',
            'lims_customer_group_all',
            'lims_category_list',
            'cloneBooking',
            'cloneLines'
        ));
    }

    public function bookedproducts(){
        $bookings = DB::select("SELECT booking_id, product_id, is_notified, DATE_FORMAT(end, '%Y-%m-%d') as end FROM booking_products WHERE is_return = false AND is_notified = false AND DATE_FORMAT(end, '%Y-%m-%d') = ?", [Date('Y-m-d')]);

        foreach ($bookings as $booking) {
            $this->sendMailFromCommand($booking->booking_id, $booking->product_id);
            BookingProduct::where('booking_id', $booking->booking_id)->where('product_id', $booking->product_id)->update(['is_notified' => true]);
        }

        $start_date = date('y-m-01');
        $end_date = date('y-m-d');
        $warehouse_id = 0;
        $lims_products_list = Product::where('is_active', true)->get();
        $lims_warehouse_list = Warehouse::where('is_active', true)->get();
        $products = BookingProduct::with('product', 'booking')->where('is_return', false)->orderByDesc('id')->get();
        return view('booking.products', compact('products', 'start_date', 'end_date', 'warehouse_id', 'lims_warehouse_list', 'lims_products_list'));
    }

    public function bookedproductsReport(Request $request) {

        $lims_products_list = Product::where('is_active', true)->get();
        $lims_warehouse_list = Warehouse::where('is_active', true)->get();
        $request_data = $request->all();
        $start_date = $request_data['start_date'];
        $end_date = $request_data['end_date'];
        $status = $request_data['status'];
        $warehouse_id = $request_data['warehouse_id'];
        $products_id = $request_data['products_id'] ?? null;

        if ($end_date && $start_date) {
            $interval = strtotime($end_date) - strtotime($start_date);
            $diff_date = ($interval / 3600 / 24) + 1;
            $yesterday = date('Y-m-d', strtotime($start_date));
            $tomorrow = date('Y-m-d', strtotime($end_date . "+1 days"));

            $data = BookingProduct::where(function($query) use ($yesterday, $tomorrow){
                $query->whereBetween('start', [$yesterday,$tomorrow])
                    ->orWhereBetween('end', [$yesterday,$tomorrow]);
            });

            if ($warehouse_id != 0) {
                $data = $data->where('warehouse_id', $warehouse_id);
            }

            if ($products_id && $products_id[0] != 0) {
                $data = $data->whereIn('product_id', $products_id);
            }
            if ($status != '') {
                $data = $data->where('is_return', $status);
            }

            $products = $data->orderByDesc('id')->get();
            return view('booking.products', compact('status', 'products', 'start_date', 'end_date', 'warehouse_id', 'lims_warehouse_list', 'lims_products_list', 'products_id'));
        }
        return redirect()->back();
    }

    public function store(Request $request)
    {
        $role = Role::find(Auth::user()->role_id);
        if (!$role || !$role->hasPermissionTo('booking_create')) {
            abort(403, 'You are not allowed to create bookings.');
        }

        $data = $request->all();

        $ccCustomerIds = $request->input('cc_customer', []);
        $data['cc_customer_ids'] = !empty($ccCustomerIds) ? implode(',', array_unique((array) $ccCustomerIds)) : null;
        unset($data['cc_customer']);

        if (!empty($data['send_for_signature'])) {
            $contractType = $request->input('contract_type');
            if (!in_array($contractType, ['equipment', 'accommodation', 'software_license'], true)) {
                return redirect()->back()->withInput()->with(
                    'not_permitted',
                    'Please select Equipment Rental, Accommodation, or Licenses Software Subscription before sending for signature.'
                );
            }
            $customer = Customer::find($data['customer_id'] ?? null);
            if (!$customer || empty(trim((string) $customer->phone_number))) {
                return redirect()->back()->withInput()->with(
                    'not_permitted',
                    'Customer phone number is required before sending the rental agreement for signature.'
                );
            }
        }

        if(isset($request->reference_no)) {
            $this->validate($request, [
                'reference_no' => [
                    'max:191', 'required', 'unique:bookings'
                ],
            ]);
        }
        //return dd($data);
        $ccCustomerIds = $request->input('cc_customer', []);
        $data['cc_customer_ids'] = !empty($ccCustomerIds) ? implode(',', array_unique($ccCustomerIds)) : null;
        unset($data['cc_customer']);

        $data['user_id'] = Auth::id();
        $cash_register_data = CashRegister::where([
            ['user_id', $data['user_id']],
            ['warehouse_id', $data['warehouse_id']],
            ['status', true]
        ])->first();

        if($cash_register_data)
            $data['cash_register_id'] = $cash_register_data->id;

        if($data['pos']) {
            if(!isset($data['reference_no']))
                $data['reference_no'] = 'pobr-' . date("Ymd") . '-'. date("his");

            $balance = $data['grand_total'] - $data['paid_amount'];
            if($balance > 0 || $balance < 0)
                $data['payment_status'] = 2;
            else
                $data['payment_status'] = 4;

        }
        else {
            if(!isset($data['reference_no']))
                $data['reference_no'] = 'br-' . date("Ymd") . '-'. date("his");
        }

        if($data['coupon_active']) {
            $lims_coupon_data = Coupon::find($data['coupon_id']);
            $lims_coupon_data->used += 1;
            $lims_coupon_data->save();
        }

        $lims_sale_data = Booking::create($data);

        $lims_customer_data = Customer::find($data['customer_id']);
        $lims_reward_point_setting_data = RewardPointSetting::latest()->first();
        //checking if customer gets some points or not
        if($lims_reward_point_setting_data->is_active &&  $data['grand_total'] >= $lims_reward_point_setting_data->minimum_amount) {
            $point = (int)($data['grand_total'] / $lims_reward_point_setting_data->per_point_amount);
            $lims_customer_data->points += $point;
            $lims_customer_data->save();
        }

        //collecting male data
        $mail_data['email'] = $lims_customer_data->email;
        $mail_data['reference_no'] = $lims_sale_data->reference_no;
        $mail_data['booking_status'] = $lims_sale_data->booking_status;
        $mail_data['payment_status'] = $lims_sale_data->payment_status;
        $mail_data['total_qty'] = $lims_sale_data->total_qty;
        $mail_data['total_price'] = $lims_sale_data->total_price;
        $mail_data['order_tax'] = $lims_sale_data->order_tax;
        $mail_data['order_tax_rate'] = $lims_sale_data->order_tax_rate;
        $mail_data['order_discount'] = $lims_sale_data->order_discount;
        $mail_data['shipping_cost'] = $lims_sale_data->shipping_cost;
        $mail_data['grand_total'] = $lims_sale_data->grand_total;
        $mail_data['paid_amount'] = $lims_sale_data->paid_amount;

        $product_id = $data['product_id'];
        $product_batch_id = $data['product_batch_id'];
        $product_code = $data['product_code'];
        $qty = $data['qty'];
        $sale_unit = $data['sale_unit'];
        $net_unit_price = $data['net_unit_price'];
        $discount = $data['discount'];
        $tax_rate = $data['tax_rate'];
        $tax = $data['tax'];
        $total = $data['subtotal'];
        $product_sale = [];

        foreach ($product_id as $i => $id) {
            $product_sale['multi_product_batch_id'] = null;
            $product_sale['multi_product_batch_qty'] = null;
            $lims_product_data = Product::where('id', $id)->first();
            $product_sale['variant_id'] = null;
            $product_sale['product_batch_id'] = null;
            if($lims_product_data->type == 'combo' && $data['booking_status'] == 1){
                $product_list = explode(",", $lims_product_data->product_list);
                $qty_list = explode(",", $lims_product_data->qty_list);
                $price_list = explode(",", $lims_product_data->price_list);

                foreach ($product_list as $key=>$child_id) {
                    $child_data = Product::find($child_id);
                    $child_warehouse_data = Product_Warehouse::where([
                        ['product_id', $child_id],
                        ['warehouse_id', $data['warehouse_id'] ],
                    ])->first();

                    $child_data->qty -= $qty[$i] * $qty_list[$key];
                    $child_warehouse_data->qty -= $qty[$i] * $qty_list[$key];

                    $child_data->save();
                    $child_warehouse_data->save();
                }
            }

            if($sale_unit[$i] != 'n/a') {
                $lims_sale_unit_data  = Unit::where('unit_name', $sale_unit[$i])->first();
                $sale_unit_id = $lims_sale_unit_data->id;
                if($lims_product_data->is_variant) {
                    $lims_product_variant_data = ProductVariant::select('id', 'variant_id', 'qty')->FindExactProductWithCode($id, $product_code[$i])->first();
                    $product_sale['variant_id'] = $lims_product_variant_data->variant_id;
                }

                if($data['booking_status'] == 1) {
                    if($lims_sale_unit_data->operator == '*')
                        $quantity = $qty[$i] * $lims_sale_unit_data->operation_value;
                    elseif($lims_sale_unit_data->operator == '/')
                        $quantity = $qty[$i] / $lims_sale_unit_data->operation_value;
                    //deduct quantity
                    $lims_product_data->qty = $lims_product_data->qty - $quantity;
                    $this->stockDurationSave($lims_product_data->id, $lims_product_data->qty);
                    $lims_product_data->save();
                    //deduct product variant quantity if exist
                    $multi_qty = 1;
                    if($lims_product_data->is_variant) {
                        $lims_product_variant_data->qty -= $quantity;
                        $lims_product_variant_data->save();
                        $lims_product_warehouse_data = Product_Warehouse::FindProductWithVariant($id, $lims_product_variant_data->variant_id, $data['warehouse_id'])->first();
                    }
                    elseif($product_batch_id[$i]) {
                        $lims_product_warehouse_data = Product_Warehouse::where([
                            ['product_batch_id', $product_batch_id[$i] ],
                            ['warehouse_id', $data['warehouse_id'] ]
                        ])->first();
                        $lims_product_batch_data = ProductBatch::find($product_batch_id[$i]);

                        if ($lims_product_batch_data->qty < $quantity) {
                            $lims_product_batch_data_multi = ProductBatch::where('product_id', $lims_product_batch_data->product_id)->where('qty', '>', 0)->orderBy('expired_date')->get();
                            $multi_qty = $quantity;
                            $multi_product_batch_id = [];
                            $multi_product_batch_qty = [];

                            foreach ($lims_product_batch_data_multi as $item) {
                                $lims_product_batch_data = ProductBatch::find($item->id);
                                $lims_product_warehouse_data = Product_Warehouse::where('product_batch_id', $item->id)->first();
                                $product_sale['product_batch_id'] = $lims_product_batch_data->id;

                                if ($lims_product_batch_data->qty <= $multi_qty) {
                                    //deduct product batch quantity
                                    $multi_product_batch_id[] = $lims_product_batch_data->id;
                                    $multi_product_batch_qty[] = $lims_product_batch_data->qty;
                                    $multi_qty -= $lims_product_batch_data->qty;
                                    $lims_product_batch_data->qty = 0;
                                    $lims_product_batch_data->save();
                                    $lims_product_warehouse_data->qty = 0;
                                    $lims_product_warehouse_data->save();
                                } else {
                                    //deduct product batch quantity
                                    $lims_product_batch_data->qty -= $multi_qty;
                                    $multi_product_batch_id[] = $lims_product_batch_data->id;
                                    $multi_product_batch_qty[] = $multi_qty;
                                    $lims_product_batch_data->save();
                                    $lims_product_warehouse_data->qty -= $multi_qty;
                                    $lims_product_warehouse_data->save();
                                    break;
                                }
                                if ($multi_qty == 0) {
                                    break;
                                }
                            }
                            $product_sale['multi_product_batch_id'] = json_encode($multi_product_batch_id);
                            $product_sale['multi_product_batch_qty'] = json_encode($multi_product_batch_qty);
                        } else {
                            $product_sale['product_batch_id'] = $lims_product_batch_data->id;
                            //deduct product batch quantity
                            $lims_product_batch_data->qty -= $quantity;
                            $lims_product_batch_data->save();
                        }

                    }
                    else {
                        $lims_product_warehouse_data = Product_Warehouse::FindProductWithoutVariant($id, $data['warehouse_id'])->first();
                    }

                    if ($multi_qty == 1) {
                        //deduct quantity from warehouse
                        $lims_product_warehouse_data->qty -= $quantity;
                        $lims_product_warehouse_data->save();
                    }
                }
            }
            else
                $sale_unit_id = 0;
            if($product_sale['variant_id']){
                $variant_data = Variant::select('name')->find($product_sale['variant_id']);
                $mail_data['products'][$i] = $lims_product_data->name . ' ['. $variant_data->name .']';
            }
            else
                $mail_data['products'][$i] = $lims_product_data->name;
            if($lims_product_data->type == 'digital')
                $mail_data['file'][$i] = url('/public/product/files').'/'.$lims_product_data->file;
            else
                $mail_data['file'][$i] = '';
            if($sale_unit_id)
                $mail_data['unit'][$i] = $lims_sale_unit_data->unit_code;
            else
                $mail_data['unit'][$i] = '';

            $product_sale['booking_id'] = $lims_sale_data->id ;
            $product_sale['category_id'] = $lims_product_data->category_id;
            $product_sale['warehouse_id'] = (int)$data['warehouse_id'];
            $product_sale['product_id'] = $id;
            $product_sale['qty'] = $mail_data['qty'][$i] = $qty[$i];
            $product_sale['sale_unit_id'] = $sale_unit_id;
            $product_sale['net_unit_price'] = $net_unit_price[$i];
            $product_sale['discount'] = $discount[$i];
            $product_sale['tax_rate'] = $tax_rate[$i];
            $product_sale['number_duration'] = $mail_data['number'][$i] =$request->number[$i];
            $product_sale['start'] = $mail_data['start'][$i] =$request->start[$i];
            $product_sale['end'] = $mail_data['end'][$i] = $request->end[$i];
            $product_sale['booking_method'] = $mail_data['booking_method'][$i] = $request->booking_method[$i];
            $product_sale['tax'] = $tax[$i];
            $product_sale['total'] = $mail_data['total'][$i] = $total[$i];
            $this->stockDurationSave($lims_product_data->id, $lims_product_data->qty);
            BookingProduct::create($product_sale);

        }
        if($data['booking_status'] == 3)
            $message = 'Booking successfully added to draft';
        else
            $message = ' Booking created successfully';
        if (empty($data['send_for_signature'])) {
            try {
                Mail::send( 'mail.booking_details', $mail_data, function( $message ) use ($mail_data)
                {
                    $message->to( $mail_data['email'] )->subject( 'Booking Details' );
                });
            }
            catch(\Exception $e){
                $message = ' Booking created successfully. Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
            }
        }

        if($data['payment_status'] == 3 || $data['payment_status'] == 4 || ($data['payment_status'] == 2 && $data['pos'] && $data['paid_amount'] > 0)) {

            $lims_payment_data = new Payment();
            $lims_payment_data->user_id = Auth::id();

            if($data['paid_by_id'] == 1)
                $paying_method = 'Cash';
            elseif ($data['paid_by_id'] == 2) {
                $paying_method = 'Gift Card';
            }
//            elseif ($data['paid_by_id'] == 3)
//                $paying_method = 'Credit Card';
            elseif ($data['paid_by_id'] == 3) {
                $paying_method = 'JE method';
                $lims_payment_data_debit = new Payment();

                $lims_payment_data_debit->user_id = Auth::id();
                if($cash_register_data) {
                    $lims_payment_data_debit->cash_register_id = $cash_register_data->id;
                }
                if($data['credit'] == null ) {
                    $lims_account_data = Account::where('is_default_debit', true)->first();
                }else{
                    $lims_account_data = Account::where('id', $data['debit'])->first();
                }
                $lims_payment_data_debit->account_id = $lims_account_data->id;
                $lims_payment_data_debit->debit_booking_id = $lims_sale_data->id;
                $data['payment_reference'] = 'spr-'.date("Ymd").'-'.date("his");
                $lims_payment_data_debit->payment_reference = $data['payment_reference'];
                $lims_payment_data_debit->amount = $data['paid_amount'];
                $lims_payment_data_debit->change = $data['paying_amount'] - $data['paid_amount'];
                $lims_payment_data_debit->paying_method = $paying_method;
                $lims_payment_data_debit->payment_note = $data['payment_note'];
                $lims_payment_data_debit->save();
            }
            elseif ($data['paid_by_id'] == 4)
                $paying_method = 'Cheque';
            elseif ($data['paid_by_id'] == 5)
                $paying_method = 'Paypal';
            elseif($data['paid_by_id'] == 6)
                $paying_method = 'Deposit';
            elseif($data['paid_by_id'] == 7) {
                $paying_method = 'Points';
                $lims_payment_data->used_points = $data['used_points'];
            }

            if($cash_register_data) {
                $lims_payment_data->cash_register_id = $cash_register_data->id;
            }
            if( isset($data['credit']) && $data['credit'] != null ) {
                $lims_account_data = Account::where('id', $data['credit'])->first();
            }else{
                $lims_account_data = Account::where('is_default', true)->first();
            }
            $lims_payment_data->account_id = $lims_account_data->id;
            $lims_payment_data->booking_id = $lims_sale_data->id;
            $data['payment_reference'] = 'spr-'.date("Ymd").'-'.date("his");
            $lims_payment_data->payment_reference = $data['payment_reference'];
            $lims_payment_data->amount = $data['paid_amount'];
            $lims_payment_data->change = $data['paying_amount'] - $data['paid_amount'];
            $lims_payment_data->paying_method = $paying_method;
            $lims_payment_data->payment_note = $data['payment_note'];
            $lims_payment_data->save();

            $lims_payment_data = Payment::latest()->first();
            $data['payment_id'] = $lims_payment_data->id;

            if($paying_method == 'Credit Card'){
                $lims_pos_setting_data = PosSetting::latest()->first();
                Stripe::setApiKey($lims_pos_setting_data->stripe_secret_key);
                $token = $data['stripeToken'];
                $grand_total = $data['grand_total'];

                $lims_payment_with_credit_card_data = PaymentWithCreditCard::where('customer_id', $data['customer_id'])->first();

                if(!$lims_payment_with_credit_card_data) {
                    // Create a Customer:
                    $customer = \Stripe\Customer::create([
                        'source' => $token
                    ]);

                    // Charge the Customer instead of the card:
                    $charge = \Stripe\Charge::create([
                        'amount' => $grand_total * 100,
                        'currency' => 'usd',
                        'customer' => $customer->id
                    ]);
                    $data['customer_stripe_id'] = $customer->id;
                }
                else {
                    $customer_id =
                        $lims_payment_with_credit_card_data->customer_stripe_id;

                    $charge = \Stripe\Charge::create([
                        'amount' => $grand_total * 100,
                        'currency' => 'usd',
                        'customer' => $customer_id, // Previously stored, then retrieved
                    ]);
                    $data['customer_stripe_id'] = $customer_id;
                }
                $data['charge_id'] = $charge->id;
                PaymentWithCreditCard::create($data);
            }
            elseif ($paying_method == 'Gift Card') {
                $lims_gift_card_data = GiftCard::find($data['gift_card_id']);
                $lims_gift_card_data->expense += $data['paid_amount'];
                $lims_gift_card_data->save();
                PaymentWithGiftCard::create($data);
            }
            elseif ($paying_method == 'Cheque') {
                PaymentWithCheque::create($data);
            }
            elseif ($paying_method == 'Paypal') {
                $provider = new ExpressCheckout;
                $paypal_data = [];
                $paypal_data['items'] = [];
                foreach ($data['product_id'] as $key => $product_id) {
                    $lims_product_data = Product::find($product_id);
                    $paypal_data['items'][] = [
                        'name' => $lims_product_data->name,
                        'price' => ($data['subtotal'][$key]/$data['qty'][$key]),
                        'qty' => $data['qty'][$key]
                    ];
                }
                $paypal_data['items'][] = [
                    'name' => 'Order Tax',
                    'price' => $data['order_tax'],
                    'qty' => 1
                ];
                $paypal_data['items'][] = [
                    'name' => 'Order Discount',
                    'price' => $data['order_discount'] * (-1),
                    'qty' => 1
                ];
                $paypal_data['items'][] = [
                    'name' => 'Shipping Cost',
                    'price' => $data['shipping_cost'],
                    'qty' => 1
                ];
                if($data['grand_total'] != $data['paid_amount']){
                    $paypal_data['items'][] = [
                        'name' => 'Due',
                        'price' => ($data['grand_total'] - $data['paid_amount']) * (-1),
                        'qty' => 1
                    ];
                }
                //return $paypal_data;
                $paypal_data['invoice_id'] = $lims_sale_data->reference_no;
                $paypal_data['invoice_description'] = "Reference # {$paypal_data['invoice_id']} Invoice";
                $paypal_data['return_url'] = url('/sale/paypalSuccess');
                $paypal_data['cancel_url'] = url('/sale/create');

                $total = 0;
                foreach($paypal_data['items'] as $item) {
                    $total += $item['price']*$item['qty'];
                }

                $paypal_data['total'] = $total;
                $response = $provider->setExpressCheckout($paypal_data);
                // This will redirect user to PayPal
                return redirect($response['paypal_link']);
            }
            elseif($paying_method == 'Deposit'){
                $lims_customer_data->expense += $data['paid_amount'];
                $lims_customer_data->save();
            }
            elseif($paying_method == 'Points'){
                $lims_customer_data->points -= $data['used_points'];
                $lims_customer_data->save();
            }
        }

        $biller = Biller::find($request->biller_id);

        if($data['paid_by_id'] == 1)
            $paying_method = 'Cash';
        elseif ($data['paid_by_id'] == 2)
            $paying_method = 'Gift Card';
        elseif ($data['paid_by_id'] == 3)
            $paying_method = 'JE Method';
        elseif ($data['paid_by_id'] == 4)
            $paying_method = 'Cheque';
        elseif ($data['paid_by_id'] == 5)
            $paying_method = 'Paypal';
        elseif($data['paid_by_id'] == 6)
            $paying_method = 'Deposit';
        elseif($data['paid_by_id'] == 7)
            $paying_method = 'Points';


        if (!empty($data['send_for_signature'])) {
            $lims_sale_data->load('customer');
            $contractType = $request->input('contract_type');
            $contract = RentalContractController::createForBooking($lims_sale_data, $contractType);
            if ($contractType === 'accommodation') {
                $contractLabel = 'Accommodation agreement';
            } elseif ($contractType === 'software_license') {
                $contractLabel = 'Software license subscription agreement';
            } else {
                $contractLabel = 'Equipment rental agreement';
            }
            try {
                RentalContractController::sendSignatureLink($lims_sale_data, $contract);
                $message = 'Booking saved. ' . $contractLabel . ' sent for signature via WhatsApp. Receipt will be generated after the client signs.';
            } catch (\Exception $e) {
                $message = 'Booking saved, but the signature link could not be sent: ' . $e->getMessage();
            }

            $this->sendBookingCcNotifications($lims_sale_data, $mail_data, $request->booking_note ?? '', $lims_customer_data->name);

            return redirect()->route('booking.awaiting-signature')->with('message', $message);
        }

        $message = $this->sendWhatsappMsg($lims_customer_data, $lims_sale_data, $mail_data, $biller, $paying_method, $request->booking_note, $net_unit_price);

        $this->sendBookingCcNotifications($lims_sale_data, $mail_data, $request->booking_note ?? '', $lims_customer_data->name);

        return redirect('bookings/gen_invoice/' . $lims_sale_data->id)->with('message', $message);
    }

    public function deliverPostSignatureReceipt($bookingId)
    {
        $lims_sale_data = Booking::with(['bookingProduct.product', 'customer', 'biller'])->findOrFail($bookingId);
        $lims_customer_data = $lims_sale_data->customer;
        $biller = $lims_sale_data->biller;

        $mail_data = [
            'email' => $lims_customer_data->email,
            'reference_no' => $lims_sale_data->reference_no,
            'grand_total' => $lims_sale_data->grand_total,
            'products' => [],
            'qty' => [],
            'total' => [],
            'start' => [],
            'end' => [],
        ];

        $net_unit_price = [];
        foreach ($lims_sale_data->bookingProduct as $index => $line) {
            $productName = $line->product ? $line->product->name : 'Product';
            $mail_data['products'][$index] = $productName;
            $mail_data['qty'][$index] = $line->qty;
            $mail_data['total'][$index] = $line->total;
            $mail_data['start'][$index] = $line->start;
            $mail_data['end'][$index] = $line->end;
            $net_unit_price[$index] = $line->net_unit_price;
        }

        $payment = Payment::where('booking_id', $lims_sale_data->id)->latest()->first();
        $paying_method = $payment ? $payment->paying_method : 'Pending';

        try {
            $this->sendWhatsappMsg(
                $lims_customer_data,
                $lims_sale_data,
                $mail_data,
                $biller,
                $paying_method,
                $lims_sale_data->booking_note,
                $net_unit_price,
                false
            );
        } catch (\Exception $e) {
            // Continue to PDF attempt.
        }

        try {
            $pdfPath = $this->buildBookingPdfFile($bookingId);
            $relative = 'booking_receipts/booking_invoice_' . $lims_sale_data->reference_no . '.pdf';
            $this->sendWhatsAppDocumentToCustomer($lims_customer_data, $pdfPath, 'booking_receipt.pdf', url($relative));
        } catch (\Exception $e) {
            // WhatsApp receipt optional if settings missing.
        }
    }

    public function buildBookingPdfFile($id)
    {
        $lims_sale_data = Booking::findOrFail($id);
        $lims_product_sale_data = BookingProduct::where('booking_id', $id)->get();
        $lims_biller_data = Biller::find($lims_sale_data->biller_id);
        $lims_warehouse_data = Warehouse::find($lims_sale_data->warehouse_id);
        $lims_customer_data = Customer::find($lims_sale_data->customer_id);
        $lims_payment_data = Payment::where('booking_id', $id)->get();
        $lims_payment_debit_data = Payment::where('debit_booking_id', $id)->get();
        $lims_account_data_cradit = null;
        $lims_account_data_debit = null;

        if (isset($lims_payment_data[0])) {
            $lims_account_data_cradit = Account::with('departments')->where('id', $lims_payment_data[0]->account_id)->first();
        }
        if (isset($lims_payment_debit_data[0])) {
            $lims_account_data_debit = Account::with('departments')->where('id', $lims_payment_debit_data[0]->account_id)->first();
        }

        $setting = GeneralSetting::first();
        $numberToWords = new NumberToWords();
        if (\App::getLocale() == 'ar' || \App::getLocale() == 'hi' || \App::getLocale() == 'vi' || \App::getLocale() == 'en-gb') {
            $numberTransformer = $numberToWords->getNumberTransformer('en');
        } else {
            $numberTransformer = $numberToWords->getNumberTransformer(\App::getLocale());
        }
        $numberInWords = $numberTransformer->toWords($lims_sale_data->grand_total);

        $data = [
            'header' => $setting->email_header,
            'footer' => $setting->email_footer,
            'water_mark' => $setting->email_water_mark,
            'all_permission' => [],
            'lims_account_data_cradit' => $lims_account_data_cradit,
            'lims_account_data_debit' => $lims_account_data_debit,
            'lims_sale_data' => $lims_sale_data,
            'lims_product_sale_data' => $lims_product_sale_data,
            'lims_biller_data' => $lims_biller_data,
            'lims_warehouse_data' => $lims_warehouse_data,
            'lims_customer_data' => $lims_customer_data,
            'lims_payment_data' => $lims_payment_data,
            'numberInWords' => $numberInWords,
        ];

        $directory = public_path('booking_receipts');
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $relativePath = 'booking_receipts/booking_invoice_' . $lims_sale_data->reference_no . '.pdf';
        $pdf = PDF::loadView('pdf.booking_pdf', $data);
        $pdf->save(public_path($relativePath));

        return public_path($relativePath);
    }


    public function sendWhatsappMsg($lims_customer_data, $lims_sale_data, $mail_data, $biller, $paying_method, $booking_note, $net_unit_price, $includeQr = true){
        $lines = [];
        foreach ($mail_data['products'] as $key => $product) {
            $lines[] = [
                'name' => $product,
                'qty' => $mail_data['qty'][$key],
                'total' => number_format($mail_data['total'][$key], 2),
                'start' => $mail_data['start'][$key],
                'end' => $mail_data['end'][$key],
            ];
        }

        $msg = \App\Support\WhatsAppMessage::bookingConfirmation(
            $lims_customer_data->name,
            $lims_sale_data->reference_no,
            (string) $lims_sale_data->created_at,
            $lines,
            number_format($mail_data['grand_total'], 2),
            $paying_method,
            $biller->name,
            $biller->address,
            $biller->phone_number,
            (string) \App\Support\BookingNoteFormatter::forPlainText($booking_note)
        );

        $message = 'Booking created successfully';
        try{
            $this->wpMessage($lims_customer_data->phone_number, $msg);
        }
        catch(\Exception $e){
            $message = 'Booking created successfully. Please setup your whatsapp setting.';
        }
        // send QR code
        if (!$includeQr) {
            return $message;
        }

        $path = public_path('public/images/bookings/qr/');
        if (!File::exists($path)) {
            File::makeDirectory($path, 0755, true);
        }
        $filename = 'qr_code_' . $lims_sale_data->reference_no . '.png';
        $url = url("bookings/scan/$lims_sale_data->reference_no");
        QrCode::format('png')->size(300)->generate($url, $path . $filename);
        try {
            $this->wpAttachMessage($path.$filename, $lims_customer_data->phone_number, $filename);
        } catch (\Exception $e) {
        }
        // Delete the QR code file after sending
        if (file_exists($path.$filename)) {
            unlink($path.$filename);
        }

        return $message;
    }

    public function sendBookingCcNotifications(Booking $booking, array $mail_data, $bookingNote, $customerName)
    {
        if (empty($booking->cc_customer_ids)) {
            return;
        }

        $ccIds = array_filter(explode(',', $booking->cc_customer_ids));
        if (empty($ccIds) || empty($mail_data['products'])) {
            return;
        }

        $lines = [];
        foreach ($mail_data['products'] as $key => $product) {
            $lines[] = [
                'name' => $product,
                'qty' => $mail_data['qty'][$key],
                'start' => $mail_data['start'][$key],
                'end' => $mail_data['end'][$key],
            ];
        }

        foreach ($ccIds as $customerId) {
            $ccCustomer = Customer::find($customerId);
            if (!$ccCustomer || empty(trim((string) $ccCustomer->phone_number))) {
                continue;
            }

            try {
                $msg = \App\Support\WhatsAppMessage::bookingQuotationCc(
                    $ccCustomer->name,
                    $booking->reference_no,
                    $lines,
                    $customerName,
                    (string) $bookingNote
                );
                $this->sendWhatsAppToCustomer($ccCustomer, $msg);
            } catch (\Exception $e) {
            }
        }
    }

    public function deductQty($lims_product_sale_data ,$lims_sale_data, $product_variant_id = null, $product_id = null){

        foreach ($lims_product_sale_data as  $key => $product_sale_data) {
            $old_product_id[] = $product_sale_data->product_id;
            $old_product_variant_id[] = null;
            $lims_product_data = Product::find($product_sale_data->product_id);

            if( ($lims_sale_data->booking_status == 1) && ($lims_product_data->type == 'combo') ) {
                $product_list = explode(",", $lims_product_data->product_list);
                $qty_list = explode(",", $lims_product_data->qty_list);

                foreach ($product_list as $index=>$child_id) {
                    $child_data = Product::find($child_id);
                    $child_warehouse_data = Product_Warehouse::where([
                        ['product_id', $child_id],
                        ['warehouse_id', $lims_sale_data->warehouse_id ],
                    ])->first();

                    $child_data->qty += $product_sale_data->qty * $qty_list[$index];
                    $child_warehouse_data->qty += $product_sale_data->qty * $qty_list[$index];

                    $child_data->save();
                    $child_warehouse_data->save();
                }
            }
            elseif( ($lims_sale_data->booking_status == 1) && ($product_sale_data->sale_unit_id != 0)) {
                $old_product_qty = $product_sale_data->qty;
                $lims_sale_unit_data = Unit::find($product_sale_data->sale_unit_id);
                if ($lims_sale_unit_data->operator == '*')
                    $old_product_qty = $old_product_qty * $lims_sale_unit_data->operation_value;
                else
                    $old_product_qty = $old_product_qty / $lims_sale_unit_data->operation_value;
                if($product_sale_data->variant_id) {
                    $lims_product_variant_data = ProductVariant::select('id', 'qty')->FindExactProduct($product_sale_data->product_id, $product_sale_data->variant_id)->first();
                    $lims_product_warehouse_data = Product_Warehouse::FindProductWithVariant($product_sale_data->product_id, $product_sale_data->variant_id, $lims_sale_data->warehouse_id)
                        ->first();
                    $old_product_variant_id[$key] = $lims_product_variant_data->id;
                    $lims_product_variant_data->qty += $old_product_qty;
                    $lims_product_variant_data->save();
                }
                elseif($product_sale_data->product_batch_id) {
                    $lims_product_warehouse_data = Product_Warehouse::where([
                        ['product_id', $product_sale_data->product_id],
                        ['product_batch_id', $product_sale_data->product_batch_id],
                        ['warehouse_id', $lims_sale_data->warehouse_id]
                    ])->first();

                    $product_batch_data = ProductBatch::find($product_sale_data->product_batch_id);
                    $product_batch_data->qty += $old_product_qty;
                    $product_batch_data->save();
                }
                else
                    $lims_product_warehouse_data = Product_Warehouse::FindProductWithoutVariant($product_sale_data->product_id, $lims_sale_data->warehouse_id)
                        ->first();
                $lims_product_data->qty += $old_product_qty;
                $lims_product_warehouse_data->qty += $old_product_qty;
                $this->stockDurationSave($lims_product_data->id, $lims_product_data->qty);
                $lims_product_data->save();
                $lims_product_warehouse_data->save();
            }

        }
    }
    public function sendMail(Request $request)
    {
        $data = $request->all();
        $lims_sale_data = Booking::find($data['booking_id']);
        $lims_product_sale_data = BookingProduct::where('booking_id', $data['booking_id'])->get();
        $lims_customer_data = Customer::find($lims_sale_data->customer_id);
        if($lims_customer_data->email) {
            //collecting male data
            $mail_data['email'] = $lims_customer_data->email;
            $mail_data['reference_no'] = $lims_sale_data->reference_no;
            $mail_data['booking_status'] = $lims_sale_data->booking_status;
            $mail_data['payment_status'] = $lims_sale_data->payment_status;
            $mail_data['total_qty'] = $lims_sale_data->total_qty;
            $mail_data['total_price'] = $lims_sale_data->total_price;
            $mail_data['order_tax'] = $lims_sale_data->order_tax;
            $mail_data['order_tax_rate'] = $lims_sale_data->order_tax_rate;
            $mail_data['order_discount'] = $lims_sale_data->order_discount;
            $mail_data['shipping_cost'] = $lims_sale_data->shipping_cost;
            $mail_data['grand_total'] = $lims_sale_data->grand_total;
            $mail_data['paid_amount'] = $lims_sale_data->paid_amount;

            foreach ($lims_product_sale_data as $key => $product_sale_data) {
                $lims_product_data = Product::find($product_sale_data->product_id);
                if($product_sale_data->variant_id) {
                    $variant_data = Variant::select('name')->find($product_sale_data->variant_id);
                    $mail_data['products'][$key] = $lims_product_data->name . ' [' . $variant_data->name . ']';
                }
                else
                    $mail_data['products'][$key] = $lims_product_data->name;
                if($lims_product_data->type == 'digital')
                    $mail_data['file'][$key] = url('/public/product/files').'/'.$lims_product_data->file;
                else
                    $mail_data['file'][$key] = '';
                if($product_sale_data->sale_unit_id){
                    $lims_unit_data = Unit::find($product_sale_data->sale_unit_id);
                    $mail_data['unit'][$key] = $lims_unit_data->unit_code;
                }
                else
                    $mail_data['unit'][$key] = '';

                $mail_data['qty'][$key] = $product_sale_data->qty;
                $mail_data['total'][$key] = $product_sale_data->qty;
                $mail_data['start'][$key] = $product_sale_data->start;
                $mail_data['end'][$key] = $product_sale_data->end;
            }

            try{
                Mail::send( 'mail.booking_details', $mail_data, function( $message ) use ($mail_data)
                {
                    $message->to( $mail_data['email'] )->subject( 'Booking Details' );
                });
                $message = 'Mail sent successfully';
            }
            catch(\Exception $e){
                $message = 'Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
            }
        }
        else
            $message = 'Customer doesnt have email!';

        return redirect()->back()->with('message', $message);
    }

    public function sendMailFromCommand($booking_id, $prduct_id)
    {
        $lims_sale_data = Booking::find($booking_id);
        $lims_product_sale_data = BookingProduct::where('booking_id', $booking_id)->where('product_id', $prduct_id)->get();
        $lims_customer_data = Customer::find($lims_sale_data->customer_id);
        if($lims_customer_data->email) {
            //collecting male data
            $mail_data['email'] = $lims_customer_data->email;
            $mail_data['reference_no'] = $lims_sale_data->reference_no;
            $mail_data['booking_status'] = $lims_sale_data->booking_status;
            $mail_data['payment_status'] = $lims_sale_data->payment_status;
            $mail_data['total_qty'] = $lims_sale_data->total_qty;
            $mail_data['total_price'] = $lims_sale_data->total_price;
            $mail_data['order_tax'] = $lims_sale_data->order_tax;
            $mail_data['order_tax_rate'] = $lims_sale_data->order_tax_rate;
            $mail_data['order_discount'] = $lims_sale_data->order_discount;
            $mail_data['shipping_cost'] = $lims_sale_data->shipping_cost;
            $mail_data['grand_total'] = $lims_sale_data->grand_total;
            $mail_data['paid_amount'] = $lims_sale_data->paid_amount;

            foreach ($lims_product_sale_data as $key => $product_sale_data) {
                $lims_product_data = Product::find($product_sale_data->product_id);
                if($product_sale_data->variant_id) {
                    $variant_data = Variant::select('name')->find($product_sale_data->variant_id);
                    $mail_data['products'][$key] = $lims_product_data->name . ' [' . $variant_data->name . ']';
                }
                else
                    $mail_data['products'][$key] = $lims_product_data->name;
                if($lims_product_data->type == 'digital')
                    $mail_data['file'][$key] = url('/public/product/files').'/'.$lims_product_data->file;
                else
                    $mail_data['file'][$key] = '';
                if($product_sale_data->sale_unit_id){
                    $lims_unit_data = Unit::find($product_sale_data->sale_unit_id);
                    $mail_data['unit'][$key] = $lims_unit_data->unit_code;
                }
                else
                    $mail_data['unit'][$key] = '';

                $mail_data['qty'][$key] = $product_sale_data->qty;
                $mail_data['total'][$key] = $product_sale_data->qty;
                $mail_data['start'][$key] = $product_sale_data->start;
                $mail_data['end'][$key] = $product_sale_data->end;
            }

            try{
                Mail::send( 'mail.booking_details', $mail_data, function( $message ) use ($mail_data)
                {
                    $message->to( $mail_data['email'] )->subject( 'Booking Details' );
                });
                $message = 'Mail sent successfully';
            }
            catch(\Exception $e){
                $message = 'Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
            }
        }
        else

        return true;
    }

    public function paypalSuccess(Request $request)
    {
        $lims_sale_data = Booking::latest()->first();
        $lims_payment_data = Payment::latest()->first();
        $lims_product_sale_data = BookingProduct::where('booking_id', $lims_sale_data->id)->get();
        $provider = new ExpressCheckout;
        $token = $request->token;
        $payerID = $request->PayerID;
        $paypal_data['items'] = [];
        foreach ($lims_product_sale_data as $key => $product_sale_data) {
            $lims_product_data = Product::find($product_sale_data->product_id);
            $paypal_data['items'][] = [
                'name' => $lims_product_data->name,
                'price' => ($product_sale_data->total/$product_sale_data->qty),
                'qty' => $product_sale_data->qty
            ];
        }
        $paypal_data['items'][] = [
            'name' => 'order tax',
            'price' => $lims_sale_data->order_tax,
            'qty' => 1
        ];
        $paypal_data['items'][] = [
            'name' => 'order discount',
            'price' => $lims_sale_data->order_discount * (-1),
            'qty' => 1
        ];
        $paypal_data['items'][] = [
            'name' => 'shipping cost',
            'price' => $lims_sale_data->shipping_cost,
            'qty' => 1
        ];
        if($lims_sale_data->grand_total != $lims_sale_data->paid_amount){
            $paypal_data['items'][] = [
                'name' => 'Due',
                'price' => ($lims_sale_data->grand_total - $lims_sale_data->paid_amount) * (-1),
                'qty' => 1
            ];
        }

        $paypal_data['invoice_id'] = $lims_payment_data->payment_reference;
        $paypal_data['invoice_description'] = "Reference: {$paypal_data['invoice_id']}";
        $paypal_data['return_url'] = url('/sale/paypalSuccess');
        $paypal_data['cancel_url'] = url('/sale/create');

        $total = 0;
        foreach($paypal_data['items'] as $item) {
            $total += $item['price']*$item['qty'];
        }

        $paypal_data['total'] = $lims_sale_data->paid_amount;
        $response = $provider->getExpressCheckoutDetails($token);
        $response = $provider->doExpressCheckoutPayment($paypal_data, $token, $payerID);
        $data['payment_id'] = $lims_payment_data->id;
        $data['transaction_id'] = $response['PAYMENTINFO_0_TRANSACTIONID'];
        PaymentWithPaypal::create($data);
        return redirect('sales')->with('message', 'Sales created successfully');
    }

    public function paypalPaymentSuccess(Request $request, $id)
    {
        $lims_payment_data = Payment::find($id);
        $provider = new ExpressCheckout;
        $token = $request->token;
        $payerID = $request->PayerID;
        $paypal_data['items'] = [];
        $paypal_data['items'][] = [
            'name' => 'Paid Amount',
            'price' => $lims_payment_data->amount,
            'qty' => 1
        ];
        $paypal_data['invoice_id'] = $lims_payment_data->payment_reference;
        $paypal_data['invoice_description'] = "Reference: {$paypal_data['invoice_id']}";
        $paypal_data['return_url'] = url('/sale/paypalPaymentSuccess');
        $paypal_data['cancel_url'] = url('/sale');

        $total = 0;
        foreach($paypal_data['items'] as $item) {
            $total += $item['price']*$item['qty'];
        }

        $paypal_data['total'] = $total;
        $response = $provider->getExpressCheckoutDetails($token);
        $response = $provider->doExpressCheckoutPayment($paypal_data, $token, $payerID);
        $data['payment_id'] = $lims_payment_data->id;
        $data['transaction_id'] = $response['PAYMENTINFO_0_TRANSACTIONID'];
        PaymentWithPaypal::create($data);
        return redirect('sales')->with('message', 'Payment created successfully');
    }

    public function getProduct($id)
    {
        $lims_product_warehouse_data = Product::join('product_warehouse', 'products.id', '=', 'product_warehouse.product_id')
            ->where([
                ['products.is_active', true],
                ['product_warehouse.warehouse_id', $id],
//                ['product_warehouse.qty', '>', 0]
            ])
            ->whereNull('product_warehouse.variant_id')
            ->whereNull('product_warehouse.product_batch_id')
            ->select('product_warehouse.*')
            ->get();

        config()->set('database.connections.mysql.strict', false);
        \DB::reconnect(); //important as the existing connection if any would be in strict mode

        $lims_product_with_batch_warehouse_data = Product::join('product_warehouse', 'products.id', '=', 'product_warehouse.product_id')
            ->where([
                ['products.is_active', true],
                ['product_warehouse.warehouse_id', $id],
//                ['product_warehouse.qty', '>', 0]
            ])
            ->whereNull('product_warehouse.variant_id')
            ->whereNotNull('product_warehouse.product_batch_id')
            ->select('product_warehouse.*')
            ->groupBy('product_warehouse.product_id')
            ->get();
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('multiple_batch')) {
            foreach ($lims_product_with_batch_warehouse_data as $item) {
                $product_batch = ProductBatch::where('product_id', $item->product_id)->where('qty', '>', 0)->orderBy('expired_date')->first();
                $product_batch_qty = ProductBatch::where('product_id', $item->product_id)->sum('qty');
                if(isset($product_batch)) {
                    $item['product_batch_id'] = $product_batch->id;
                    $item['qty'] = $product_batch_qty;
                    $item['expired_date'] = $product_batch->expired_date;
                    $item['created_at'] = $product_batch->created_at;
                    $item['updated_at'] = $product_batch->updated_at;
                }
            }
        }

        //now changing back the strict ON
        config()->set('database.connections.mysql.strict', true);
        \DB::reconnect();

        $lims_product_with_variant_warehouse_data = Product::join('product_warehouse', 'products.id', '=', 'product_warehouse.product_id')
            ->where([
                ['products.is_active', true],
                ['product_warehouse.warehouse_id', $id],
//                ['product_warehouse.qty', '>', 0]
            ])->whereNotNull('product_warehouse.variant_id')->select('product_warehouse.*')->get();

        $product_code = [];
        $product_name = [];
        $product_qty = [];
        $product_price = [];
        $product_data = [];
        //product without variant
        foreach ($lims_product_warehouse_data as $product_warehouse)
        {
            $product_qty[] = $product_warehouse->qty;
            $product_price[] = $product_warehouse->price;
            $lims_product_data = Product::find($product_warehouse->product_id);
            $product_code[] =  $lims_product_data->code;
            $product_name[] = htmlspecialchars($lims_product_data->name);
            $product_type[] = $lims_product_data->type;
            $product_id[] = $lims_product_data->id;
            $product_list[] = $lims_product_data->product_list;
            $qty_list[] = $lims_product_data->qty_list;
            $batch_no[] = null;
            $product_batch_id[] = null;
            $product_expired_date[] = null;
        }
        //product with batches
        foreach ($lims_product_with_batch_warehouse_data as $product_warehouse)
        {
            $product_qty[] = $product_warehouse->qty;
            $product_price[] = $product_warehouse->price;
            $lims_product_data = Product::find($product_warehouse->product_id);
            $product_code[] =  $lims_product_data->code;
            $product_name[] = htmlspecialchars($lims_product_data->name);
            $product_type[] = $lims_product_data->type;
            $product_id[] = $lims_product_data->id;
            $product_list[] = $lims_product_data->product_list;
            $qty_list[] = $lims_product_data->qty_list;
            $product_batch_data = ProductBatch::select('id', 'batch_no', 'expired_date')->find($product_warehouse->product_batch_id);
            $batch_no[] = $product_batch_data->batch_no;
            $product_batch_id[] = $product_batch_data->id;
            $product_expired_date[] = $product_batch_data->expired_date;
        }
        //product with variant
        foreach ($lims_product_with_variant_warehouse_data as $product_warehouse)
        {
            $product_qty[] = $product_warehouse->qty;
            $lims_product_data = Product::find($product_warehouse->product_id);
            $lims_product_variant_data = ProductVariant::select('item_code')->FindExactProduct($product_warehouse->product_id, $product_warehouse->variant_id)->first();
            $product_code[] =  $lims_product_variant_data->item_code;
            $product_name[] = htmlspecialchars($lims_product_data->name);
            $product_type[] = $lims_product_data->type;
            $product_id[] = $lims_product_data->id;
            $product_list[] = $lims_product_data->product_list;
            $qty_list[] = $lims_product_data->qty_list;
            $batch_no[] = null;
            $product_batch_id[] = null;
            $product_expired_date[] = null;
        }
        //retrieve product with type of digital and combo
        $lims_product_data = Product::whereNotIn('type', ['standard'])->where('is_active', true)->get();
        foreach ($lims_product_data as $product)
        {
            $product_qty[] = $product->qty;
            $product_code[] =  $product->code;
            $product_name[] = $product->name;
            $product_type[] = $product->type;
            $product_id[] = $product->id;
            $product_list[] = $product->product_list;
            $qty_list[] = $product->qty_list;
            $batch_no[] = null;
            $product_batch_id[] = null;
            $product_expired_date[] = null;
        }
        $product_data = [$product_code, $product_name, $product_qty, $product_type, $product_id, $product_list, $qty_list, $product_price, $batch_no, $product_batch_id, $product_expired_date];
        return $product_data;
    }

    public function posSale()
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('booking_add')){
            $permissions = Role::findByName($role->name)->permissions;
            foreach ($permissions as $permission)
                $all_permission[] = $permission->name;
            if(empty($all_permission))
                $all_permission[] = 'dummy text';

            $lims_customer_list = Customer::where('is_active', true)->get();
            $lims_account_list = Account::where('is_active', true)->get();
            $lims_customer_group_all = CustomerGroup::where('is_active', true)->get();
            $lims_warehouse_list = Warehouse::where('is_active', true)->get();
            $lims_biller_list = Biller::where('is_active', true)->get();
            $lims_reward_point_setting_data = RewardPointSetting::latest()->first();
            $lims_tax_list = Tax::where('is_active', true)->get();
            $lims_product_list = Product::select('id', 'name', 'code', 'image')->ActiveFeatured()->whereNull('is_variant')->get();
            foreach ($lims_product_list as $key => $product) {
                $images = explode(",", $product->image);
                $product->base_image = $images[0];
            }
            $lims_product_list_with_variant = Product::select('id', 'name', 'code', 'image')->ActiveFeatured()->whereNotNull('is_variant')->get();

            foreach ($lims_product_list_with_variant as $product) {
                $images = explode(",", $product->image);
                $product->base_image = $images[0];
                $lims_product_variant_data = $product->variant()->orderBy('position')->get();
                $main_name = $product->name;
                $temp_arr = [];
                foreach ($lims_product_variant_data as $key => $variant) {
                    $product->name = $main_name.' ['.$variant->name.']';
                    $product->code = $variant->pivot['item_code'];
                    $lims_product_list[] = clone($product);
                }
            }

            $product_number = count($lims_product_list);
            $lims_pos_setting_data = PosSetting::latest()->first();
            $lims_brand_list = Brand::where('is_active',true)->get();
            $lims_category_list = Category::where('is_active',true)->get();

            if(Auth::user()->role_id > 2 && config('staff_access') == 'own') {
                $recent_sale = Booking::where([
                    ['booking_status', 1],
                    ['user_id', Auth::id()]
                ])->orderBy('id', 'desc')->take(10)->get();
                $recent_draft = Booking::where([
                    ['booking_status', 3],
                    ['user_id', Auth::id()]
                ])->orderBy('id', 'desc')->take(10)->get();
            }
            else {
                $recent_sale = Booking::where('booking_status', 1)->orderBy('id', 'desc')->take(10)->get();
                $recent_draft = Booking::where('booking_status', 3)->orderBy('id', 'desc')->take(10)->get();
            }

            $lims_account_default = Account::where('is_default', true)->first();
            $lims_account_default_debit = Account::where('is_default_debit', true)->first();
            $lims_coupon_list = Coupon::where('is_active',true)->get();
            $flag = 0;

            return view('sale.pos', compact('all_permission', 'lims_customer_list', 'lims_customer_group_all', 'lims_warehouse_list', 'lims_reward_point_setting_data', 'lims_product_list', 'product_number', 'lims_tax_list', 'lims_biller_list', 'lims_pos_setting_data', 'lims_brand_list', 'lims_category_list', 'recent_sale', 'recent_draft', 'lims_coupon_list', 'flag', 'lims_account_list', 'lims_account_default', 'lims_account_default_debit'));
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    public function getProductByFilter($category_id, $brand_id)
    {
        $data = [];
        if(($category_id != 0) && ($brand_id != 0)){
            $lims_product_list = DB::table('products')
                ->join('categories', 'products.category_id', '=', 'categories.id')
                ->where([
                    ['products.is_active', true],
                    ['products.category_id', $category_id],
                    ['brand_id', $brand_id]
                ])->orWhere([
                    ['categories.parent_id', $category_id],
                    ['products.is_active', true],
                    ['brand_id', $brand_id]
                ])->select('products.name', 'products.code', 'products.image')->get();
        }
        elseif(($category_id != 0) && ($brand_id == 0)){
            $lims_product_list = DB::table('products')
                ->join('categories', 'products.category_id', '=', 'categories.id')
                ->where([
                    ['products.is_active', true],
                    ['products.category_id', $category_id],
                ])->orWhere([
                    ['categories.parent_id', $category_id],
                    ['products.is_active', true]
                ])->select('products.id', 'products.name', 'products.code', 'products.image', 'products.is_variant')->get();
        }
        elseif(($category_id == 0) && ($brand_id != 0)){
            $lims_product_list = Product::where([
                ['brand_id', $brand_id],
                ['is_active', true]
            ])
                ->select('products.id', 'products.name', 'products.code', 'products.image', 'products.is_variant')
                ->get();
        }
        else
            $lims_product_list = Product::where('is_active', true)->get();

        $index = 0;
        foreach ($lims_product_list as $product) {
            if($product->is_variant) {
                $lims_product_data = Product::select('id')->find($product->id);
                $lims_product_variant_data = $lims_product_data->variant()->orderBy('position')->get();
                foreach ($lims_product_variant_data as $key => $variant) {
                    $data['name'][$index] = $product->name.' ['.$variant->name.']';
                    $data['code'][$index] = $variant->pivot['item_code'];
                    $images = explode(",", $product->image);
                    $data['image'][$index] = $images[0];
                    $index++;
                }
            }
            else {
                $data['name'][$index] = $product->name;
                $data['code'][$index] = $product->code;
                $images = explode(",", $product->image);
                $data['image'][$index] = $images[0];
                $index++;
            }
        }
        return $data;
    }

    public function getFeatured()
    {
        $data = [];
        $lims_product_list = Product::where([
            ['is_active', true],
            ['featured', true]
        ])->select('products.id', 'products.name', 'products.code', 'products.image', 'products.is_variant')->get();

        $index = 0;
        foreach ($lims_product_list as $product) {
            if($product->is_variant) {
                $lims_product_data = Product::select('id')->find($product->id);
                $lims_product_variant_data = $lims_product_data->variant()->orderBy('position')->get();
                foreach ($lims_product_variant_data as $key => $variant) {
                    $data['name'][$index] = $product->name.' ['.$variant->name.']';
                    $data['code'][$index] = $variant->pivot['item_code'];
                    $images = explode(",", $product->image);
                    $data['image'][$index] = $images[0];
                    $index++;
                }
            }
            else {
                $data['name'][$index] = $product->name;
                $data['code'][$index] = $product->code;
                $images = explode(",", $product->image);
                $data['image'][$index] = $images[0];
                $index++;
            }
        }
        return $data;
    }

    public function getCustomerGroup($id)
    {
        $lims_customer_data = Customer::find($id);
        $lims_customer_group_data = CustomerGroup::find($lims_customer_data->customer_group_id);
        return $lims_customer_group_data->percentage;
    }

    public function getBatchProduct($id) {
        $products = ProductBatch::where('product_id', $id)->select('batch_no', 'qty', 'expired_date')->where('qty', '>', 0)->get()->toArray();
        return $products;
    }

    public function limsProductSearch(Request $request)
    {
        $todayDate = date('Y-m-d');
        $product_code = explode("(", $request['data']);
        $product_code[0] = rtrim($product_code[0], " ");
        $product_variant_id = null;
        $qty = 0;
        $lims_product_data = Product::where([
            ['code', $product_code[0]],
            ['is_active', true]
        ])->first();
        if(!$lims_product_data) {
            $lims_product_data = Product::join('product_variants', 'products.id', 'product_variants.product_id')
                ->select('products.*', 'product_variants.id as product_variant_id', 'product_variants.item_code', 'product_variants.additional_price')
                ->where([
                    ['product_variants.item_code', $product_code[0]],
                    ['products.is_active', true]
                ])->first();
            $product_variant_id = $lims_product_data->product_variant_id;
        }

        $qty = Product_Warehouse::where('product_id', $lims_product_data->id)->select('qty')->first();
        $product[] = $lims_product_data->name;
        if($lims_product_data->is_variant){
            $product[] = $lims_product_data->item_code;
            $lims_product_data->price += $lims_product_data->additional_price;
        }
        else
            $product[] = $lims_product_data->code;

        if($lims_product_data->promotion && $todayDate <= $lims_product_data->last_date){
            $product[] = $lims_product_data->promotion_price;
        }
        else
            $product[] = $lims_product_data->price;

        if($lims_product_data->tax_id) {
            $lims_tax_data = Tax::find($lims_product_data->tax_id);
            $product[] = $lims_tax_data->rate;
            $product[] = $lims_tax_data->name;
        }
        else{
            $product[] = 0;
            $product[] = 'No Tax';
        }
        $product[] = $lims_product_data->tax_method;
        if($lims_product_data->type == 'standard'){
            $units = Unit::where("base_unit", $lims_product_data->unit_id)
                ->orWhere('id', $lims_product_data->unit_id)
                ->get();
            $unit_name = array();
            $unit_operator = array();
            $unit_operation_value = array();
            foreach ($units as $unit) {
                if($lims_product_data->sale_unit_id == $unit->id) {
                    array_unshift($unit_name, $unit->unit_name);
                    array_unshift($unit_operator, $unit->operator);
                    array_unshift($unit_operation_value, $unit->operation_value);
                }
                else {
                    $unit_name[]  = $unit->unit_name;
                    $unit_operator[] = $unit->operator;
                    $unit_operation_value[] = $unit->operation_value;
                }
            }
            $product[] = implode(",",$unit_name) . ',';
            $product[] = implode(",",$unit_operator) . ',';
            $product[] = implode(",",$unit_operation_value) . ',';
        }
        else{
            $product[] = 'n/a'. ',';
            $product[] = 'n/a'. ',';
            $product[] = 'n/a'. ',';
        }
        $product[] = $lims_product_data->id;
        $product[] = $product_variant_id;
        $product[] = $lims_product_data->promotion;
        $product[] = $lims_product_data->is_batch;
        $product[] = $lims_product_data->rent_price_per_hour;
        $product[] = $lims_product_data->rent_price_per_day;
        $product[] = $lims_product_data->rent_price_per_month;
        $product[] = $qty->qty ?? '';
        return $product;

    }

    public function getGiftCard()
    {
        $gift_card = GiftCard::where("is_active", true)->whereDate('expired_date', '>=', date("Y-m-d"))->get(['id', 'card_no', 'amount', 'expense']);
        return json_encode($gift_card);
    }

    public function productSaleData($id)
    {
        $lims_product_sale_data = BookingProduct::where('booking_id', $id)->get();
        foreach ($lims_product_sale_data as $key => $product_sale_data) {
            $product = Product::find($product_sale_data->product_id);
            if($product_sale_data->variant_id) {
                $lims_product_variant_data = ProductVariant::select('item_code')->FindExactProduct($product_sale_data->product_id, $product_sale_data->variant_id)->first();
                $product->code = $lims_product_variant_data->item_code;
            }
            $unit_data = Unit::find($product_sale_data->sale_unit_id);
            if($unit_data){
                $unit = $unit_data->unit_code;
            }
            else
                $unit = '';
            if($product_sale_data->product_batch_id) {
                $product_batch_data = ProductBatch::select('batch_no')->find($product_sale_data->product_batch_id);
                $product_sale[7][$key] = $product_batch_data->batch_no;
            }
            else
                $product_sale[7][$key] = 'N/A';
            $product_sale[0][$key] = $product->name . ' [' . $product->code . ']';
            $product_sale[1][$key] = $product_sale_data->qty;
            $product_sale[2][$key] = $unit;
            $product_sale[3][$key] = $product_sale_data->tax;
            $product_sale[4][$key] = $product_sale_data->tax_rate;
            $product_sale[5][$key] = $product_sale_data->discount;
            $product_sale[6][$key] = $product_sale_data->total;
            $product_sale[7][$key] = $product_sale_data->start;
            $product_sale[8][$key] = $product_sale_data->end;
        }
        return $product_sale;
    }

    public function getProductPriceByDuration(Request $request)
    {
        $method = $request->method;
        $product = Product::find($request->id);
        $price = $product->price;

        if($method == 0) {
            $price = $product->rent_price_per_hour;
        }
        elseif($method == 1) {
            $price = $product->rent_price_per_day;
        }
        elseif($method == 2) {
            $price = $product->rent_price_per_month;
        }
        return $price;
    }

    public function getProductQtyByDuration(Request $request) {
        $start = $request->start;
        $end = $request->end;
        $qty = $request->qty;
        $product_quantity = $request->product_quantity;
        $product = Product::find($request->id);

        $data = BookingProduct::whereHas('booking', function ($query) {
            return $query->where('booking_status', 1);
             })->where('end', '<' ,$start)
            ->where('is_return', 0)
            ->where('product_id', $request->id)
            ->select('qty')
            ->get();
        foreach ($data as $prod_qty) {
            $product_quantity += $prod_qty->qty;
        }

        $data = BookingProduct::whereHas('booking', function ($query) {
            return $query->where('booking_status', 1);
            })->where('start', '>' ,$end)
            ->where('is_return', 0)
            ->where('product_id', $request->id)
            ->select('qty')
            ->get();
        foreach ($data as $prod_qty) {
            $product_quantity += $prod_qty->qty;
        }

        return $product_quantity;
    }

    public function createSale($id)
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('booking_edit')){
            $lims_biller_list = Biller::where('is_active', true)->get();
            $lims_customer_list = Customer::where('is_active', true)->get();
            $lims_customer_group_all = CustomerGroup::where('is_active', true)->get();
            $lims_warehouse_list = Warehouse::where('is_active', true)->get();
            $lims_tax_list = Tax::where('is_active', true)->get();
            $lims_sale_data = Booking::find($id);
            $lims_product_sale_data = BookingProduct::where('booking_id', $id)->get();
            $lims_product_list = Product::where([
                ['featured', 1],
                ['is_active', true]
            ])->get();
            foreach ($lims_product_list as $key => $product) {
                $images = explode(",", $product->image);
                $product->base_image = $images[0];
            }
            $product_number = count($lims_product_list);
            $lims_pos_setting_data = PosSetting::latest()->first();
            $lims_brand_list = Brand::where('is_active',true)->get();
            $lims_category_list = Category::where('is_active',true)->get();
            $lims_coupon_list = Coupon::where('is_active',true)->get();

            return view('sale.create_sale',compact('lims_biller_list', 'lims_customer_list', 'lims_warehouse_list', 'lims_tax_list', 'lims_sale_data','lims_product_sale_data', 'lims_pos_setting_data', 'lims_brand_list', 'lims_category_list', 'lims_coupon_list', 'lims_product_list', 'product_number', 'lims_customer_group_all'));
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    public function return($id)
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('booking_edit')){
            $lims_customer_list = Customer::where('is_active', true)->get();
            $lims_warehouse_list = Warehouse::where('is_active', true)->get();
            $lims_biller_list = Biller::where('is_active', true)->get();
            $lims_tax_list = Tax::where('is_active', true)->get();
            $lims_sale_data = Booking::with('biller', 'customer', 'warehouse', 'user', 'contract')->find($id);
            $lims_product_sale_data = BookingProduct::where('booking_id', $id)->get();
            return view('booking.return',compact('lims_customer_list', 'lims_warehouse_list', 'lims_biller_list', 'lims_tax_list', 'lims_sale_data','lims_product_sale_data'));
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }


    public function returnData($id, Request $request)
    {
        $data = $request->except('document');

        $booking_status = 3;
        $flag = true;

        foreach($data['is_return'] as $product) {
            if($product == 0) {
                $flag =  false;
            }
        }
        if($flag == false) {
            $booking_status = $data['booking_status'];
        }

        $lims_customer_data = Customer::find($data['customer_id']);
        $lims_return_data = Booking::find($id);
        //collecting mail data
        $mail_data['email'] = $lims_customer_data->email;
        $mail_data['reference_no'] = $lims_return_data->reference_no;
        $mail_data['total_qty'] = $lims_return_data->total_qty;
        $mail_data['total_price'] = $lims_return_data->total_price;
        $mail_data['order_tax'] = $lims_return_data->order_tax;
        $mail_data['order_tax_rate'] = $lims_return_data->order_tax_rate;
        $mail_data['grand_total'] = $lims_return_data->grand_total;

        if(!isset($data['product_id'])){
            $lims_return_data->update(['booking_status' => $booking_status]);
            $message = 'Return created successfully';
            return redirect('/bookings/index')->with('message', $message);
        }

        $product_id = $data['product_id'];
        $product_batch_id = $data['product_batch_id'];
        $product_code = $data['product_code'];
        $qty = $data['qty'];
        $sale_unit = $data['sale_unit'];
        $net_unit_price = $data['net_unit_price'];
        $discount = $data['discount'];
        $tax_rate = $data['tax_rate'];
        $tax = $data['tax'];
        $total = $data['subtotal'];

        foreach ($product_id as $key => $pro_id) {
            if($booking_status != 3 && $data['is_return'][$key] == 0) {
                continue;
            }

            $lims_product_data = Product::find($pro_id);
            $lims_product_booking_data = BookingProduct::where('product_id', $pro_id)->where('booking_id', $id)->first();
            $lims_product_booking_data->update(['is_return' => 1 ]);
            $variant_id = null;
            if($sale_unit[$key] != 'n/a') {
                $sale_unit_ex = explode(',', $sale_unit[$key]);
                $lims_sale_unit_data  = Unit::where('unit_name', $sale_unit_ex[0])->first();
                $sale_unit_id = $lims_sale_unit_data->id;
                if($lims_sale_unit_data->operator == '*')
                    $quantity = $qty[$key] * $lims_sale_unit_data->operation_value;
                elseif($lims_sale_unit_data->operator == '/')
                    $quantity = $qty[$key] / $lims_sale_unit_data->operation_value;

                if($lims_product_data->is_variant) {
                    $lims_product_variant_data = ProductVariant::
                    select('id', 'variant_id', 'qty')
                        ->FindExactProductWithCode($pro_id, $product_code[$key])
                        ->first();
                    $lims_product_warehouse_data = Product_Warehouse::FindProductWithVariant($pro_id, $lims_product_variant_data->variant_id, $data['warehouse_id'])->first();
                    $lims_product_variant_data->qty += $quantity;
                    $lims_product_variant_data->save();
                    $variant_data = Variant::find($lims_product_variant_data->variant_id);
                    $variant_id = $variant_data->id;
                }
                elseif($product_batch_id[$key]) {
                    $lims_product_warehouse_data = Product_Warehouse::where([
                        ['product_batch_id', $product_batch_id[$key] ],
                        ['warehouse_id', $data['warehouse_id'] ]
                    ])->first();
                    $lims_product_batch_data = ProductBatch::find($product_batch_id[$key]);
                    //increase product batch quantity
                    $lims_product_batch_data->qty += $quantity;
                    $lims_product_batch_data->save();
                }
                else
                    $lims_product_warehouse_data = Product_Warehouse::FindProductWithoutVariant($pro_id, $data['warehouse_id'])->first();

                $lims_product_data->qty +=  $quantity;
                $lims_product_warehouse_data->qty += $quantity;

                $lims_product_data->save();
                $lims_product_warehouse_data->save();
            }
            else {
                if($lims_product_data->type == 'combo'){
                    $product_list = explode(",", $lims_product_data->product_list);
                    $qty_list = explode(",", $lims_product_data->qty_list);
                    $price_list = explode(",", $lims_product_data->price_list);

                    foreach ($product_list as $index=>$child_id) {
                        $child_data = Product::find($child_id);
                        $child_warehouse_data = Product_Warehouse::where([
                            ['product_id', $child_id],
                            ['warehouse_id', $data['warehouse_id'] ],
                        ])->first();

                        $child_data->qty += $qty[$key] * $qty_list[$index];
                        $child_warehouse_data->qty += $qty[$key] * $qty_list[$index];

                        $child_data->save();
                        $child_warehouse_data->save();
                    }
                }
                $sale_unit_id = 0;
            }
            if($lims_product_data->is_variant)
                $mail_data['products'][$key] = $lims_product_data->name . ' [' . $variant_data->name . ']';
            else
                $mail_data['products'][$key] = $lims_product_data->name;

            if($sale_unit_id)
                $mail_data['unit'][$key] = $lims_sale_unit_data->unit_code;
            else
                $mail_data['unit'][$key] = '';

            $mail_data['qty'][$key] = $qty[$key];
            $mail_data['total'][$key] = $total[$key];

            $mail_data['start'][$key] = $lims_product_batch_data->start ?? 'NAN';
            $mail_data['end'][$key] = $lims_product_batch_data->end ?? 'NAN';

        }
        $lims_return_data->update(['booking_status' => $booking_status]);
        $message = 'Return created successfully';
        if($mail_data['email']){
            try{
                Mail::send( 'mail.return_details', $mail_data, function( $message ) use ($mail_data)
                {
                    $message->to( $mail_data['email'] )->subject( 'Return Details' );
                });
            }
            catch(\Exception $e){
                $message = 'Return created successfully. Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
            }
        }
        return redirect('/bookings/index')->with('message', $message);
    }

    public function edit($id)
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('booking_edit')){
            $lims_customer_list = Customer::where('is_active', true)->get();
            $lims_warehouse_list = Warehouse::where('is_active', true)->get();
            $lims_biller_list = Biller::where('is_active', true)->get();
            $lims_tax_list = Tax::where('is_active', true)->get();
            $lims_sale_data = Booking::find($id);
            $lims_product_sale_data = BookingProduct::where('booking_id', $id)->get();
            return view('booking.edit',compact('lims_customer_list', 'lims_warehouse_list', 'lims_biller_list', 'lims_tax_list', 'lims_sale_data','lims_product_sale_data'));
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    public function update(Request $request, $id)
    {
        $role = Role::find(Auth::user()->role_id);
        if (!$role || !$role->hasPermissionTo('booking_edit')) {
            abort(403, 'You are not allowed to edit bookings.');
        }

        $data = $request->except('document');

        $ccCustomerIds = $request->input('cc_customer', []);
        $data['cc_customer_ids'] = !empty($ccCustomerIds) ? implode(',', array_unique((array) $ccCustomerIds)) : null;
        unset($data['cc_customer']);

        $balance = $data['grand_total'] - $data['paid_amount'];
        if($balance < 0 || $balance > 0)
            $data['payment_status'] = 2;
        else
            $data['payment_status'] = 4;
        $lims_sale_data = Booking::find($id);
        $lims_product_sale_data = BookingProduct::where('booking_id', $id)->get();
        $product_id = $data['product_id'];
        $product_batch_id = $data['product_batch_id'];
        $product_code = $data['product_code'];
        $product_variant_id = $data['product_variant_id'];
        $qty = $data['qty'];
        $sale_unit = $data['sale_unit'];
        $net_unit_price = $data['net_unit_price'];
        $discount = $data['discount'];
        $tax_rate = $data['tax_rate'];
        $tax = $data['tax'];
        $total = $data['subtotal'];
        $old_product_id = [];
        $product_sale = [];
        foreach ($lims_product_sale_data as  $key => $product_sale_data) {
            $old_product_id[] = $product_sale_data->product_id;
            $old_product_variant_id[] = null;
            $lims_product_data = Product::find($product_sale_data->product_id);

            if( ($lims_sale_data->booking_status == 1) && ($lims_product_data->type == 'combo') ) {
                $product_list = explode(",", $lims_product_data->product_list);
                $qty_list = explode(",", $lims_product_data->qty_list);

                foreach ($product_list as $index=>$child_id) {
                    $child_data = Product::find($child_id);
                    $child_warehouse_data = Product_Warehouse::where([
                        ['product_id', $child_id],
                        ['warehouse_id', $lims_sale_data->warehouse_id ],
                    ])->first();

                    $child_data->qty += $product_sale_data->qty * $qty_list[$index];
                    $child_warehouse_data->qty += $product_sale_data->qty * $qty_list[$index];

                    $child_data->save();
                    $child_warehouse_data->save();
                }
            }
            elseif( ($lims_sale_data->booking_status == 1) && ($product_sale_data->sale_unit_id != 0)) {
                $old_product_qty = $product_sale_data->qty;
                $lims_sale_unit_data = Unit::find($product_sale_data->sale_unit_id);
                if ($lims_sale_unit_data->operator == '*')
                    $old_product_qty = $old_product_qty * $lims_sale_unit_data->operation_value;
                else
                    $old_product_qty = $old_product_qty / $lims_sale_unit_data->operation_value;
                if($product_sale_data->variant_id) {
                    $lims_product_variant_data = ProductVariant::select('id', 'qty')->FindExactProduct($product_sale_data->product_id, $product_sale_data->variant_id)->first();
                    $lims_product_warehouse_data = Product_Warehouse::FindProductWithVariant($product_sale_data->product_id, $product_sale_data->variant_id, $lims_sale_data->warehouse_id)
                        ->first();
                    $old_product_variant_id[$key] = $lims_product_variant_data->id;
                    $lims_product_variant_data->qty += $old_product_qty;
                    $lims_product_variant_data->save();
                }
                elseif($product_sale_data->product_batch_id) {
                    $lims_product_warehouse_data = Product_Warehouse::where([
                        ['product_id', $product_sale_data->product_id],
                        ['product_batch_id', $product_sale_data->product_batch_id],
                        ['warehouse_id', $lims_sale_data->warehouse_id]
                    ])->first();

                    $product_batch_data = ProductBatch::find($product_sale_data->product_batch_id);
                    $product_batch_data->qty += $old_product_qty;
                    $product_batch_data->save();
                }
                else
                    $lims_product_warehouse_data = Product_Warehouse::FindProductWithoutVariant($product_sale_data->product_id, $lims_sale_data->warehouse_id)
                        ->first();
                $lims_product_data->qty += $old_product_qty;
                $lims_product_warehouse_data->qty += $old_product_qty;
                $this->stockDurationSave($lims_product_data->id, $lims_product_data->qty);
                $lims_product_data->save();
                $lims_product_warehouse_data->save();
            }
            if($product_sale_data->variant_id && !(in_array($old_product_variant_id[$key], $product_variant_id)) ){
                $product_sale_data->delete();
            }
            elseif( !(in_array($old_product_id[$key], $product_id)) )
                $product_sale_data->delete();
        }
        foreach ($product_id as $key => $pro_id) {
            $lims_product_data = Product::find($pro_id);
            $product_sale['variant_id'] = null;
            if($lims_product_data->type == 'combo' && $data['booking_status'] == 1){
                $product_list = explode(",", $lims_product_data->product_list);
                $qty_list = explode(",", $lims_product_data->qty_list);

                foreach ($product_list as $index=>$child_id) {
                    $child_data = Product::find($child_id);
                    $child_warehouse_data = Product_Warehouse::where([
                        ['product_id', $child_id],
                        ['warehouse_id', $data['warehouse_id'] ],
                    ])->first();

                    $child_data->qty -= $qty[$key] * $qty_list[$index];
                    $child_warehouse_data->qty -= $qty[$key] * $qty_list[$index];

                    $child_data->save();
                    $child_warehouse_data->save();
                }
            }
            if($sale_unit[$key] != 'n/a') {
                $lims_sale_unit_data = Unit::where('unit_name', $sale_unit[$key])->first();
                $sale_unit_id = $lims_sale_unit_data->id;
                if($data['booking_status'] == 1) {
                    $new_product_qty = $qty[$key];
                    if ($lims_sale_unit_data->operator == '*') {
                        $new_product_qty = $new_product_qty * $lims_sale_unit_data->operation_value;
                    } else {
                        $new_product_qty = $new_product_qty / $lims_sale_unit_data->operation_value;
                    }
                    if($lims_product_data->is_variant) {
                        $lims_product_variant_data = ProductVariant::select('id', 'variant_id', 'qty')->FindExactProductWithCode($pro_id, $product_code[$key])->first();
                        $lims_product_warehouse_data = Product_Warehouse::FindProductWithVariant($pro_id, $lims_product_variant_data->variant_id, $data['warehouse_id'])
                            ->first();

                        $product_sale['variant_id'] = $lims_product_variant_data->variant_id;
                        $lims_product_variant_data->qty -= $new_product_qty;
                        $lims_product_variant_data->save();
                    }
                    elseif($product_batch_id[$key]) {
                        $lims_product_warehouse_data = Product_Warehouse::where([
                            ['product_id', $pro_id],
                            ['product_batch_id', $product_batch_id[$key] ],
                            ['warehouse_id', $data['warehouse_id'] ]
                        ])->first();

                        $product_batch_data = ProductBatch::find($product_batch_id[$key]);
                        $product_batch_data->qty -= $new_product_qty;
                        $product_batch_data->save();
                    }
                    else {
                        $lims_product_warehouse_data = Product_Warehouse::FindProductWithoutVariant($pro_id, $data['warehouse_id'])
                            ->first();
                    }
                    $lims_product_data->qty -= $new_product_qty;
                    $lims_product_warehouse_data->qty -= $new_product_qty;
                    $this->stockDurationSave($lims_product_data->id, $lims_product_data->qty);
                    $lims_product_data->save();
                    $lims_product_warehouse_data->save();
                }
            }
            else
                $sale_unit_id = 0;

            //collecting mail data
            $mail_data['start'][$key] = $lims_product_data->start;
            $mail_data['end'][$key] = $lims_product_data->end;
            if($product_sale['variant_id']) {
                $variant_data = Variant::select('name')->find($product_sale['variant_id']);
                $mail_data['products'][$key] = $lims_product_data->name . ' [' . $variant_data->name . ']';
            }
            else
                $mail_data['products'][$key] = $lims_product_data->name;

            if($lims_product_data->type == 'digital')
                $mail_data['file'][$key] = url('/public/product/files').'/'.$lims_product_data->file;
            else
                $mail_data['file'][$key] = '';
            if($sale_unit_id)
                $mail_data['unit'][$key] = $lims_sale_unit_data->unit_code;
            else
                $mail_data['unit'][$key] = '';

            $product_sale['booking_id'] = $id ;
            $product_sale['product_id'] = $pro_id;
            $product_sale['product_batch_id'] = $product_batch_id[$key];
            $product_sale['qty'] = $mail_data['qty'][$key] = $qty[$key];
            $product_sale['sale_unit_id'] = $sale_unit_id;
            $product_sale['net_unit_price'] = $net_unit_price[$key];
            $product_sale['discount'] = $discount[$key];
            $product_sale['tax_rate'] = $tax_rate[$key];
            $product_sale['number_duration'] = $mail_data['number'][$key] =$request->number[$key];
            $product_sale['start'] = $mail_data['start'][$key] = $request->start[$key];
            $product_sale['end'] = $mail_data['end'][$key] = $request->end[$key];
            $product_sale['booking_method'] = $mail_data['booking_method'][$key] = $request->booking_method[$key];
            $product_sale['tax'] = $tax[$key];
            $product_sale['total'] = $mail_data['total'][$key] = $total[$key];

            if($product_sale['variant_id'] && in_array($product_variant_id[$key], $old_product_variant_id)) {
                BookingProduct::where([
                    ['product_id', $pro_id],
                    ['variant_id', $product_sale['variant_id']],
                    ['booking_id', $id]
                ])->update($product_sale);
            }
            elseif( $product_sale['variant_id'] === null && (in_array($pro_id, $old_product_id)) ) {
                BookingProduct::where([
                    ['booking_id', $id],
                    ['product_id', $pro_id]
                ])->update($product_sale);
            }
            else
                BookingProduct::create($product_sale);
        }
        $lims_sale_data->update($data);
        $lims_sale_data->refresh();
        $lims_customer_data = Customer::find($data['customer_id']);
        $message = 'Booking updated successfully';
        //collecting mail data
        if($lims_customer_data->email){
            $mail_data['email'] = $lims_customer_data->email;
            $mail_data['reference_no'] = $lims_sale_data->reference_no;
            $mail_data['booking_status'] = $lims_sale_data->booking_status;
            $mail_data['payment_status'] = $lims_sale_data->payment_status;
            $mail_data['total_qty'] = $lims_sale_data->total_qty;
            $mail_data['total_price'] = $lims_sale_data->total_price;
            $mail_data['order_tax'] = $lims_sale_data->order_tax;
            $mail_data['order_tax_rate'] = $lims_sale_data->order_tax_rate;
            $mail_data['order_discount'] = $lims_sale_data->order_discount;
            $mail_data['shipping_cost'] = $lims_sale_data->shipping_cost;
            $mail_data['grand_total'] = $lims_sale_data->grand_total;
            $mail_data['paid_amount'] = $lims_sale_data->paid_amount;
            if($mail_data['email']){
                try{
                    Mail::send( 'mail.booking_details', $mail_data, function( $message ) use ($mail_data)
                    {
                        $message->to( $mail_data['email'] )->subject( 'Booking Details' );
                    });
                }
                catch(\Exception $e){
                    $message = 'Booking updated successfully. Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
                }
            }
        }

        try {
            $this->sendBookingCcNotifications(
                $lims_sale_data,
                $mail_data,
                $request->booking_note ?? '',
                $lims_customer_data->name ?? ''
            );
        } catch (\Exception $e) {
        }

        if ($lims_sale_data->is_frontend == 1) {
            return redirect('/online/bookings/index')->with('message', $message);
        }

        return redirect()->route('booking.edit', $id)->with('message', $message);
    }

    public function printLastReciept()
    {
        $sale = Booking::where('booking_status', 1)->latest()->first();
        return redirect()->route('sale.invoice', $sale->id);
    }

    public function genInvoice($id)
    {
        $role = Role::find(Auth::user()->role_id);
        $permissions = Role::findByName($role->name)->permissions;

        foreach ($permissions as $permission) {
            $all_permission[] = $permission->name;
        }
        $lims_sale_data = Booking::with('contract')->find($id);

        if ($lims_sale_data && $lims_sale_data->contract) {
            $contract = $lims_sale_data->contract;
            if (!$contract->signed_at) {
                return redirect()->route('booking.awaiting-signature')
                    ->with('not_permitted', 'Receipt is available after the client signs the rental agreement.');
            }
            if ($contract->isPendingReview()) {
                return redirect()->route('booking.pending-review')
                    ->with('not_permitted', 'Receipt is available after admin review and countersignature.');
            }
            if (!$contract->isApproved()) {
                return redirect()->route('booking.pending-review')
                    ->with('not_permitted', 'Receipt is available after the contract is fully approved.');
            }
        }

        $lims_product_sale_data = BookingProduct::where('booking_id', $id)->get();
        $lims_biller_data = Biller::find($lims_sale_data->biller_id);
        $lims_warehouse_data = Warehouse::find($lims_sale_data->warehouse_id);
        $lims_customer_data = Customer::find($lims_sale_data->customer_id);
        $lims_payment_data = Payment::where('booking_id', $id)->get();
        $lims_payment_debit_data = Payment::where('debit_booking_id', $id)->get();
        $lims_account_data = null;
        $lims_account_data_debit = null;
        $lims_account_data_cradit = null;

        if(isset($lims_payment_data[0])) {
            $lims_account_data_cradit = Account::with('departments')->where('id', $lims_payment_data[0]->account_id)->first();
        }
        if(isset($lims_payment_debit_data[0])) {
            $lims_account_data_debit = Account::with('departments')->where('id', $lims_payment_debit_data[0]->account_id)->first();
        }

        $setting = GeneralSetting::first();
        $header = $setting->email_header;
        $footer = $setting->email_footer;
        $water_mark = $setting->email_water_mark;

        $numberToWords = new NumberToWords();
        if(\App::getLocale() == 'ar' || \App::getLocale() == 'hi' || \App::getLocale() == 'vi' || \App::getLocale() == 'en-gb')
            $numberTransformer = $numberToWords->getNumberTransformer('en');
        else
            $numberTransformer = $numberToWords->getNumberTransformer(\App::getLocale());
        $numberInWords = $numberTransformer->toWords($lims_sale_data->grand_total);

        return view('booking.invoice', compact('header', 'footer', 'water_mark', 'all_permission', 'lims_account_data_cradit', 'lims_account_data_debit', 'lims_sale_data', 'lims_product_sale_data', 'lims_biller_data', 'lims_warehouse_data', 'lims_customer_data', 'lims_payment_data', 'numberInWords'));
    }

    public function addPayment(Request $request)
    {
        $data = $request->all();
        if (!$data['amount'])
            $data['amount'] = 0.00;

        $lims_sale_data = Booking::find($data['booking_id']);
        $lims_customer_data = Customer::find($lims_sale_data->customer_id);
        $lims_sale_data->paid_amount += $data['amount'];
        $balance = $lims_sale_data->grand_total - $lims_sale_data->paid_amount;
        if ($balance > 0 || $balance < 0) {

            $lims_sale_data->payment_status = 2;
        }
        elseif ($balance == 0) {
            $lims_sale_data->payment_status = 4;
//            $lims_sale_data->booking_status = 1;
        }

        if ($data['paid_by_id'] == 1)
            $paying_method = 'Cash';
        elseif ($data['paid_by_id'] == 2)
            $paying_method = 'Gift Card';
//        elseif ($data['paid_by_id'] == 3)
//            $paying_method = 'Credit Card';
        elseif ($data['paid_by_id'] == 3) {
            $paying_method = 'JE Method';
            $lims_payment_data = new Payment();
            $lims_payment_data->user_id = Auth::id();
            $lims_payment_data->debit_booking_id = $lims_sale_data->id;
            $lims_payment_data->account_id = $data['account_id_debit'];
            $data['payment_reference'] = 'spr-' . date("Ymd") . '-' . date("his");
            $lims_payment_data->payment_reference = $data['payment_reference'];
            $lims_payment_data->amount = $data['amount'];
            $lims_payment_data->change = $data['paying_amount'] - $data['amount'];
            $lims_payment_data->paying_method = $paying_method;
            $lims_payment_data->payment_note = $data['payment_note'];
            $lims_payment_data->save();
        }
        elseif($data['paid_by_id'] == 4)
            $paying_method = 'Cheque';
        elseif($data['paid_by_id'] == 5)
            $paying_method = 'Paypal';
        elseif($data['paid_by_id'] == 6)
            $paying_method = 'Deposit';
        elseif($data['paid_by_id'] == 7)
            $paying_method = 'Points';


        $cash_register_data = CashRegister::where([
            ['user_id', Auth::id()],
            ['warehouse_id', $lims_sale_data->warehouse_id],
            ['status', true]
        ])->first();

        $lims_payment_data = new Payment();
        $lims_payment_data->user_id = Auth::id();
        $lims_payment_data->booking_id = $lims_sale_data->id;
        if($cash_register_data)
            $lims_payment_data->cash_register_id = $cash_register_data->id;
        $lims_payment_data->account_id = $data['account_id'];
        $data['payment_reference'] = 'spr-' . date("Ymd") . '-'. date("his");
        $lims_payment_data->payment_reference = $data['payment_reference'];
        $lims_payment_data->amount = $data['amount'];
        $lims_payment_data->change = $data['paying_amount'] - $data['amount'];
        $lims_payment_data->paying_method = $paying_method;
        $lims_payment_data->payment_note = $data['payment_note'];
        $lims_payment_data->save();
        $lims_sale_data->save();

        $lims_payment_data = Payment::latest()->first();
        $data['payment_id'] = $lims_payment_data->id;

        if($paying_method == 'Gift Card'){
            $lims_gift_card_data = GiftCard::find($data['gift_card_id']);
            $lims_gift_card_data->expense += $data['amount'];
            $lims_gift_card_data->save();
            PaymentWithGiftCard::create($data);
        }
        elseif($paying_method == 'Credit Cards'){
            $lims_pos_setting_data = PosSetting::latest()->first();
            Stripe::setApiKey($lims_pos_setting_data->stripe_secret_key);
            $token = $data['stripeToken'];
            $amount = $data['amount'];

            $lims_payment_with_credit_card_data = PaymentWithCreditCard::where('customer_id', $lims_sale_data->customer_id)->first();

            if(!$lims_payment_with_credit_card_data) {
                // Create a Customer:
                $customer = \Stripe\Customer::create([
                    'source' => $token
                ]);

                // Charge the Customer instead of the card:
                $charge = \Stripe\Charge::create([
                    'amount' => $amount * 100,
                    'currency' => 'usd',
                    'customer' => $customer->id,
                ]);
                $data['customer_stripe_id'] = $customer->id;
            }
            else {
                $customer_id =
                    $lims_payment_with_credit_card_data->customer_stripe_id;

                $charge = \Stripe\Charge::create([
                    'amount' => $amount * 100,
                    'currency' => 'usd',
                    'customer' => $customer_id, // Previously stored, then retrieved
                ]);
                $data['customer_stripe_id'] = $customer_id;
            }
            $data['customer_id'] = $lims_sale_data->customer_id;
            $data['charge_id'] = $charge->id;
            PaymentWithCreditCard::create($data);
        }
        elseif ($paying_method == 'Cheque') {
            PaymentWithCheque::create($data);
        }
        elseif ($paying_method == 'Paypal') {
            $provider = new ExpressCheckout;
            $paypal_data['items'] = [];
            $paypal_data['items'][] = [
                'name' => 'Paid Amount',
                'price' => $data['amount'],
                'qty' => 1
            ];
            $paypal_data['invoice_id'] = $lims_payment_data->payment_reference;
            $paypal_data['invoice_description'] = "Reference: {$paypal_data['invoice_id']}";
            $paypal_data['return_url'] = url('/sale/paypalPaymentSuccess/'.$lims_payment_data->id);
            $paypal_data['cancel_url'] = url('/sale');

            $total = 0;
            foreach($paypal_data['items'] as $item) {
                $total += $item['price']*$item['qty'];
            }

            $paypal_data['total'] = $total;
            $response = $provider->setExpressCheckout($paypal_data);
            return redirect($response['paypal_link']);
        }
        elseif ($paying_method == 'Deposit') {
            $lims_customer_data->expense += $data['amount'];
            $lims_customer_data->save();
        }
        elseif ($paying_method == 'Points') {
            $lims_reward_point_setting_data = RewardPointSetting::latest()->first();
            $used_points = ceil($data['amount'] / $lims_reward_point_setting_data->per_point_amount);

            $lims_payment_data->used_points = $used_points;
            $lims_payment_data->save();

            $lims_customer_data->points -= $used_points;
            $lims_customer_data->save();
        }
        $message = 'Payment created successfully';
        if($lims_customer_data->email){
            $mail_data['email'] = $lims_customer_data->email;
            $mail_data['sale_reference'] = $lims_sale_data->reference_no;
            $mail_data['payment_reference'] = $lims_payment_data->payment_reference;
            $mail_data['payment_method'] = $lims_payment_data->paying_method;
            $mail_data['grand_total'] = $lims_sale_data->grand_total;
            $mail_data['paid_amount'] = $lims_payment_data->amount;
            try{
                Mail::send( 'mail.payment_details', $mail_data, function( $message ) use ($mail_data)
                {
                    $message->to( $mail_data['email'] )->subject( 'Payment Details' );
                });
            }
            catch(\Exception $e){
                $message = 'Payment created successfully. Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
            }

        }
        return redirect('/bookings/index')->with('message', $message);
    }

    public function getPayment($id)
    {
        $lims_payment_list = Payment::where('booking_id', $id)->get();
        $date = [];
        $payment_reference = [];
        $paid_amount = [];
        $paying_method = [];
        $payment_id = [];
        $payment_note = [];
        $gift_card_id = [];
        $cheque_no = [];
        $change = [];
        $paying_amount = [];
        $account_name = [];
        $account_id = [];

        foreach ($lims_payment_list as $payment) {
            $date[] = date(config('date_format'), strtotime($payment->created_at->toDateString())) . ' '. $payment->created_at->toTimeString();
            $payment_reference[] = $payment->payment_reference;
            $paid_amount[] = $payment->amount;
            $change[] = $payment->change;
            $paying_method[] = $payment->paying_method;
            $paying_amount[] = $payment->amount + $payment->change;
            if($payment->paying_method == 'Gift Card'){
                $lims_payment_gift_card_data = PaymentWithGiftCard::where('payment_id',$payment->id)->first();
                $gift_card_id[] = $lims_payment_gift_card_data->gift_card_id;
            }
            elseif($payment->paying_method == 'Cheque'){
                $lims_payment_cheque_data = PaymentWithCheque::where('payment_id',$payment->id)->first();
                $cheque_no[] = $lims_payment_cheque_data->cheque_no;
            }
            else{
                $cheque_no[] = $gift_card_id[] = null;
            }
            $payment_id[] = $payment->id;
            $payment_note[] = $payment->payment_note;
            $lims_account_data = Account::find($payment->account_id);
            $account_name[] = $lims_account_data->name;
            $account_id[] = $lims_account_data->id;
        }
        $payments[] = $date;
        $payments[] = $payment_reference;
        $payments[] = $paid_amount;
        $payments[] = $paying_method;
        $payments[] = $payment_id;
        $payments[] = $payment_note;
        $payments[] = $cheque_no;
        $payments[] = $gift_card_id;
        $payments[] = $change;
        $payments[] = $paying_amount;
        $payments[] = $account_name;
        $payments[] = $account_id;

        return $payments;
    }

    public function updatePayment(Request $request)
    {
        $data = $request->all();
//        return $data;
        $lims_payment_data = Payment::find($data['payment_id']);
        $lims_sale_data = Booking::find($lims_payment_data->booking_id);
        $lims_customer_data = Customer::find($lims_sale_data->customer_id);
        //updating sale table
        $amount_dif = $lims_payment_data->amount - $data['edit_amount'];
        $lims_sale_data->paid_amount = $lims_sale_data->paid_amount - $amount_dif;
        $balance = $lims_sale_data->grand_total - $lims_sale_data->paid_amount;
        if($balance > 0 || $balance < 0)
            $lims_sale_data->payment_status = 2;
        elseif ($balance == 0)
            $lims_sale_data->payment_status = 4;
        $lims_sale_data->save();

        if($lims_payment_data->paying_method == 'Deposit') {
            $lims_customer_data->expense -= $lims_payment_data->amount;
            $lims_customer_data->save();
        }
        elseif($lims_payment_data->paying_method == 'Points') {
            $lims_customer_data->points += $lims_payment_data->used_points;
            $lims_customer_data->save();
            $lims_payment_data->used_points = 0;
        }
        if ($data['edit_paid_by_id'] != 3) {
            $payments = Payment::where('debit_booking_id', $lims_payment_data->booking_id)->get();
            if(isset($payments)) {
                foreach ($payments as $payment){
                    $payment->delete();
                }
            }
        }
        if($data['edit_paid_by_id'] == 1)
            $lims_payment_data->paying_method = 'Cash';
        elseif ($data['edit_paid_by_id'] == 2){
            if($lims_payment_data->paying_method == 'Gift Card'){
                $lims_payment_gift_card_data = PaymentWithGiftCard::where('payment_id', $data['payment_id'])->first();

                $lims_gift_card_data = GiftCard::find($lims_payment_gift_card_data->gift_card_id);
                $lims_gift_card_data->expense -= $lims_payment_data->amount;
                $lims_gift_card_data->save();

                $lims_gift_card_data = GiftCard::find($data['gift_card_id']);
                $lims_gift_card_data->expense += $data['edit_amount'];
                $lims_gift_card_data->save();

                $lims_payment_gift_card_data->gift_card_id = $data['gift_card_id'];
                $lims_payment_gift_card_data->save();
            }
            else{
                $lims_payment_data->paying_method = 'Gift Card';
                $lims_gift_card_data = GiftCard::find($data['gift_card_id']);
                $lims_gift_card_data->expense += $data['edit_amount'];
                $lims_gift_card_data->save();
                PaymentWithGiftCard::create($data);
            }
        }
        elseif ($data['edit_paid_by_id'] == 3) {
            $paying_method = 'JE Method';
            $lims_payment_data->paying_method = $paying_method;
            $lims_payment_data->amount = $data['edit_amount'];
            $lims_payment_data->change = $data['edit_paying_amount'] - $data['edit_amount'];
            $lims_payment_data->payment_note = $data['edit_payment_note'];
            $lims_payment_data_JE = new Payment();
            $lims_payment_data_JE->user_id = Auth::id();
            $lims_payment_data_JE->debit_booking_id = $lims_sale_data->id;
            $lims_payment_data_JE->account_id = $data['account_id'];
            $data['payment_reference'] = 'spr-' . date("Ymd") . '-' . date("his");
            $lims_payment_data_JE->payment_reference = $data['payment_reference'];
            $lims_payment_data_JE->amount = $data['edit_amount'];
            $lims_payment_data_JE->change = $data['edit_paying_amount'] - $data['edit_amount'];
            $lims_payment_data_JE->paying_method = $paying_method;
            $lims_payment_data_JE->payment_note = $data['edit_payment_note'];
            $lims_payment_data_JE->save();
        }
        elseif($data['edit_paid_by_id'] == 4){
            if($lims_payment_data->paying_method == 'Cheque'){
                $lims_payment_cheque_data = PaymentWithCheque::where('payment_id', $data['payment_id'])->first();
                $lims_payment_cheque_data->cheque_no = $data['edit_cheque_no'];
                $lims_payment_cheque_data->save();
            }
            else{
                $lims_payment_data->paying_method = 'Cheque';
                $data['cheque_no'] = $data['edit_cheque_no'];
                PaymentWithCheque::create($data);
            }
        }
        elseif($data['edit_paid_by_id'] == 5){
            //updating payment data
            $lims_payment_data->amount = $data['edit_amount'];
            $lims_payment_data->paying_method = 'Paypal';
            $lims_payment_data->payment_note = $data['edit_payment_note'];
            $lims_payment_data->save();

            $provider = new ExpressCheckout;
            $paypal_data['items'] = [];
            $paypal_data['items'][] = [
                'name' => 'Paid Amount',
                'price' => $data['edit_amount'],
                'qty' => 1
            ];
            $paypal_data['invoice_id'] = $lims_payment_data->payment_reference;
            $paypal_data['invoice_description'] = "Reference: {$paypal_data['invoice_id']}";
            $paypal_data['return_url'] = url('/sale/paypalPaymentSuccess/'.$lims_payment_data->id);
            $paypal_data['cancel_url'] = url('/sale');

            $total = 0;
            foreach($paypal_data['items'] as $item) {
                $total += $item['price']*$item['qty'];
            }

            $paypal_data['total'] = $total;
            $response = $provider->setExpressCheckout($paypal_data);
            return redirect($response['paypal_link']);
        }
        elseif($data['edit_paid_by_id'] == 6){
            $lims_payment_data->paying_method = 'Deposit';
            $lims_customer_data->expense += $data['edit_amount'];
            $lims_customer_data->save();
        }
        elseif($data['edit_paid_by_id'] == 7) {
            $lims_payment_data->paying_method = 'Points';
            $lims_reward_point_setting_data = RewardPointSetting::latest()->first();
            $used_points = ceil($data['edit_amount'] / $lims_reward_point_setting_data->per_point_amount);
            $lims_payment_data->used_points = $used_points;
            $lims_customer_data->points -= $used_points;
            $lims_customer_data->save();
        }
        //updating payment data
        $lims_payment_data->account_id = $data['account_id'];
        $lims_payment_data->amount = $data['edit_amount'];
        $lims_payment_data->change = $data['edit_paying_amount'] - $data['edit_amount'];
        $lims_payment_data->payment_note = $data['edit_payment_note'];
        $lims_payment_data->save();
        $message = 'Payment updated successfully';
        //collecting male data
        if($lims_customer_data->email){
            $mail_data['email'] = $lims_customer_data->email;
            $mail_data['sale_reference'] = $lims_sale_data->reference_no;
            $mail_data['payment_reference'] = $lims_payment_data->payment_reference;
            $mail_data['payment_method'] = $lims_payment_data->paying_method;
            $mail_data['grand_total'] = $lims_sale_data->grand_total;
            $mail_data['paid_amount'] = $lims_payment_data->amount;
            try{
                Mail::send( 'mail.payment_details', $mail_data, function( $message ) use ($mail_data)
                {
                    $message->to( $mail_data['email'] )->subject( 'Payment Details' );
                });
            }
            catch(\Exception $e){
                $message = 'Payment updated successfully. Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
            }
        }
        return redirect('/bookings/index')->with('message', $message);
    }

    public function deletePayment(Request $request)
    {
        $lims_payment_data = Payment::find($request['id']);
        $lims_sale_data = Booking::where('id', $lims_payment_data->booking_id)->first();
        $lims_sale_data->paid_amount -= $lims_payment_data->amount;
        $balance = $lims_sale_data->grand_total - $lims_sale_data->paid_amount;
        if($balance > 0 || $balance < 0)
            $lims_sale_data->payment_status = 2;
        elseif ($balance == 0)
            $lims_sale_data->payment_status = 4;
        $lims_sale_data->save();

        if ($lims_payment_data->paying_method == 'Gift Card') {
            $lims_payment_gift_card_data = PaymentWithGiftCard::where('payment_id', $request['id'])->first();
            $lims_gift_card_data = GiftCard::find($lims_payment_gift_card_data->gift_card_id);
            $lims_gift_card_data->expense -= $lims_payment_data->amount;
            $lims_gift_card_data->save();
            $lims_payment_gift_card_data->delete();
        }
        elseif($lims_payment_data->paying_method == 'Credit Card'){
            $lims_payment_with_credit_card_data = PaymentWithCreditCard::where('payment_id', $request['id'])->first();
            $lims_pos_setting_data = PosSetting::latest()->first();
            Stripe::setApiKey($lims_pos_setting_data->stripe_secret_key);
            \Stripe\Refund::create(array(
                "charge" => $lims_payment_with_credit_card_data->charge_id,
            ));

            $lims_payment_with_credit_card_data->delete();
        }
        elseif ($lims_payment_data->paying_method == 'Cheque') {
            $lims_payment_cheque_data = PaymentWithCheque::where('payment_id', $request['id'])->first();
            $lims_payment_cheque_data->delete();
        }
        elseif ($lims_payment_data->paying_method == 'Paypal') {
            $lims_payment_paypal_data = PaymentWithPaypal::where('payment_id', $request['id'])->first();
            if($lims_payment_paypal_data){
                $provider = new ExpressCheckout;
                $response = $provider->refundTransaction($lims_payment_paypal_data->transaction_id);
                $lims_payment_paypal_data->delete();
            }
        }
        elseif ($lims_payment_data->paying_method == 'Deposit'){
            $lims_customer_data = Customer::find($lims_sale_data->customer_id);
            $lims_customer_data->expense -= $lims_payment_data->amount;
            $lims_customer_data->save();
        }
        elseif ($lims_payment_data->paying_method == 'Points'){
            $lims_customer_data = Customer::find($lims_sale_data->customer_id);
            $lims_customer_data->points += $lims_payment_data->used_points;
            $lims_customer_data->save();
        }
        $lims_payment_data->delete();
        return redirect('/bookings/index')->with('not_permitted', 'Payment deleted successfully');
    }

    public function destroy($id)
    {
        $role = Role::find(Auth::user()->role_id);
        if (!$role || !$role->hasPermissionTo('booking_delete')) {
            abort(403, 'You are not allowed to delete bookings.');
        }

        $url = url()->previous();
        $lims_sale_data = Booking::find($id);
        $lims_product_sale_data = BookingProduct::where('booking_id', $id)->get();
//        $lims_delivery_data = Delivery::where('booking_id',$id)->first();
        if($lims_sale_data->booking_status == 3)
            $message = 'Draft deleted successfully';
        else
            $message = 'Booking deleted successfully';
        foreach ($lims_product_sale_data as $product_sale) {
            $lims_product_data = Product::find($product_sale->product_id);
            //adjust product quantity
            if( ($lims_sale_data->booking_status == 1) && ($lims_product_data->type == 'combo') ){
                $product_list = explode(",", $lims_product_data->product_list);
                $qty_list = explode(",", $lims_product_data->qty_list);

                foreach ($product_list as $index=>$child_id) {
                    $child_data = Product::find($child_id);
                    $child_warehouse_data = Product_Warehouse::where([
                        ['product_id', $child_id],
                        ['warehouse_id', $lims_sale_data->warehouse_id ],
                    ])->first();

                    $child_data->qty += $product_sale->qty * $qty_list[$index];
                    $child_warehouse_data->qty += $product_sale->qty * $qty_list[$index];

                    $child_data->save();
                    $child_warehouse_data->save();
                }
            }
            elseif(($lims_sale_data->booking_status == 1) && ($product_sale->sale_unit_id != 0)){
                $lims_sale_unit_data = Unit::find($product_sale->sale_unit_id);
                if ($lims_sale_unit_data->operator == '*')
                    $product_sale->qty = $product_sale->qty * $lims_sale_unit_data->operation_value;
                else
                    $product_sale->qty = $product_sale->qty / $lims_sale_unit_data->operation_value;
                if($product_sale->variant_id) {
                    $lims_product_variant_data = ProductVariant::select('id', 'qty')->FindExactProduct($lims_product_data->id, $product_sale->variant_id)->first();
                    $lims_product_warehouse_data = Product_Warehouse::FindProductWithVariant($lims_product_data->id, $product_sale->variant_id, $lims_sale_data->warehouse_id)->first();
                    $lims_product_variant_data->qty += $product_sale->qty;
                    $lims_product_variant_data->save();
                }
                elseif($product_sale->product_batch_id) {
                    $lims_product_batch_data = ProductBatch::find($product_sale->product_batch_id);
                    $lims_product_warehouse_data = Product_Warehouse::where([
                        ['product_batch_id', $product_sale->product_batch_id],
                        ['warehouse_id', $lims_sale_data->warehouse_id]
                    ])->first();

                    $lims_product_batch_data->qty -= $product_sale->qty;
                    $lims_product_batch_data->save();
                }
                else {
                    $lims_product_warehouse_data = Product_Warehouse::FindProductWithoutVariant($lims_product_data->id, $lims_sale_data->warehouse_id)->first();
                }

                $lims_product_data->qty += $product_sale->qty;
                $lims_product_warehouse_data->qty += $product_sale->qty;
                $lims_product_data->save();
                $lims_product_warehouse_data->save();
            }
            $product_sale->delete();
        }
        $lims_payment_data = Payment::where('booking_id', $id)->get();
        foreach ($lims_payment_data as $payment) {
            if($payment->paying_method == 'Gift Card'){
                $lims_payment_with_gift_card_data = PaymentWithGiftCard::where('payment_id', $payment->id)->first();
                $lims_gift_card_data = GiftCard::find($lims_payment_with_gift_card_data->gift_card_id);
                $lims_gift_card_data->expense -= $payment->amount;
                $lims_gift_card_data->save();
                $lims_payment_with_gift_card_data->delete();
            }
            elseif($payment->paying_method == 'Cheque'){
                $lims_payment_cheque_data = PaymentWithCheque::where('payment_id', $payment->id)->first();
                $lims_payment_cheque_data->delete();
            }
            elseif($payment->paying_method == 'Credit Card'){
                $lims_payment_with_credit_card_data = PaymentWithCreditCard::where('payment_id', $payment->id)->first();
                $lims_payment_with_credit_card_data->delete();
            }
            elseif($payment->paying_method == 'Paypal'){
                $lims_payment_paypal_data = PaymentWithPaypal::where('payment_id', $payment->id)->first();
                if($lims_payment_paypal_data)
                    $lims_payment_paypal_data->delete();
            }
            elseif($payment->paying_method == 'Deposit'){
                $lims_customer_data = Customer::find($lims_sale_data->customer_id);
                $lims_customer_data->expense -= $payment->amount;
                $lims_customer_data->save();
            }
            $payment->delete();
        }

        if($lims_sale_data->coupon_id) {
            $lims_coupon_data = Coupon::find($lims_sale_data->coupon_id);
            $lims_coupon_data->used -= 1;
            $lims_coupon_data->save();
        }
        $lims_sale_data->delete();
        return Redirect::to($url)->with('not_permitted', $message);
    }

    public function addCategoryIdInSale() {
        $sales = BookingProduct::select('product_id', 'id')->get();
        foreach ($sales as $sale) {
            $product = Product::where('id', $sale->product_id)->select('category_id')->first();
            if(isset($product->category_id)){
                $sale->update(['category_id' => $product->category_id]);
            }
        }
    }

    public function quickStoreCustomer(Request $request)
    {
        $request->validate([
            'customer_name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:255',
            'customer_group_id' => 'required|integer',
        ]);

        $customer = Customer::create([
            'customer_group_id' => $request->customer_group_id,
            'name' => $request->customer_name,
            'phone_number' => \App\Support\WhatsAppPhone::sanitizeForStorage($request->phone_number),
            'email' => $request->email,
            'address' => $request->address ?: 'N/A',
            'city' => $request->city ?: 'N/A',
            'is_active' => true,
        ]);

        return response()->json([
            'id' => $customer->id,
            'label' => $customer->name . ' (' . $customer->phone_number . ')',
        ]);
    }

    public function quickStoreProduct(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:products,code',
            'category_id' => 'required|integer',
            'rent_price_per_day' => 'nullable|numeric',
            'rent_price_per_hour' => 'nullable|numeric',
            'rent_price_per_month' => 'nullable|numeric',
        ]);

        $unit = Unit::where('is_active', true)->first();
        if (!$unit) {
            return response()->json(['message' => 'No active unit found. Please create a unit first.'], 422);
        }

        $requiresQuantity = $request->boolean('requires_quantity', true);

        $product = Product::create([
            'name' => $request->name,
            'code' => $request->code,
            'type' => 'digital',
            'requires_quantity' => $requiresQuantity,
            'category_id' => $request->category_id,
            'unit_id' => $unit->id,
            'purchase_unit_id' => $unit->id,
            'sale_unit_id' => $unit->id,
            'cost' => 0,
            'price' => $request->rent_price_per_day ?: 0,
            'qty' => $requiresQuantity ? ($request->qty ?: 1) : 0,
            'alert_quantity' => 0,
            'tax_method' => 1,
            'barcode_symbology' => 'C128',
            'image' => 'zummXD2dvAtI.png',
            'is_active' => true,
            'rent_price_per_hour' => $request->rent_price_per_hour ?: 0,
            'rent_price_per_day' => $request->rent_price_per_day ?: 0,
            'rent_price_per_month' => $request->rent_price_per_month ?: 0,
        ]);

        $warehouse = Warehouse::where('is_active', true)->first();
        if ($warehouse) {
            Product_Warehouse::create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'qty' => $product->qty,
            ]);
        }

        return response()->json([
            'id' => $product->id,
            'code' => $product->code,
            'name' => $product->name,
        ]);
    }
}

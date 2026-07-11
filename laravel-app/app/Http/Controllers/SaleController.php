<?php

namespace App\Http\Controllers;

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
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\Rule;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use PDF;
use App\GeneralSetting;
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
use Illuminate\Support\Facades\Validator;

class SaleController extends Controller
{
    public function sendWhatsapp(Request $request)
    {
        $data = $request->all();
        return $this->genPDFInvoice($data['sale_id']);
    }

    public function genPDFInvoice($id)
    {
        $role = Role::find(Auth::user()->role_id);
        $permissions = Role::findByName($role->name)->permissions;

        foreach ($permissions as $permission) {
            $all_permission[] = $permission->name;
        }
        $lims_sale_data = Sale::find($id);
        $lims_product_sale_data = Product_Sale::where('sale_id', $id)->get();
        $lims_biller_data = Biller::find($lims_sale_data->biller_id);
        $lims_warehouse_data = Warehouse::find($lims_sale_data->warehouse_id);
        $lims_customer_data = Customer::find($lims_sale_data->customer_id);
        $lims_payment_data = Payment::where('sale_id', $id)->get();
        $lims_payment_debit_data = Payment::where('debit_sale_id', $id)->get();
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

        $pdf = PDF::loadView('pdf.sale_pdf', $data);

        $content = $pdf->download()->getOriginalContent();

        Storage::put('public/sale/sale_invoice.pdf',$content);
        $path = storage_path('app/public/sale/sale_invoice.pdf');

        $message = 'Sale notification sent successfully';
        try{
            $this->wpPDFMessage($path, $lims_customer_data, 'sale_invoice.pdf');
        }
        catch(\Exception $e){
            $message = 'Sale notification not sent: '.$e->getMessage().'. Please verify WhatsApp settings and customer phone number.';
        }
        return back()->with('message', $message);
    }

    public function index(Request $request)
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

            return view('sale.index',compact('starting_date', 'ending_date', 'warehouse_id', 'lims_gift_card_list', 'lims_pos_setting_data', 'lims_reward_point_setting_data', 'lims_account_list', 'lims_warehouse_list', 'all_permission'));
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
            $totalData = Sale::where('user_id', Auth::id())
                        ->whereDate('created_at', '>=' ,$request->input('starting_date'))
                        ->whereDate('created_at', '<=' ,$request->input('ending_date'))
                        ->count();
        elseif($warehouse_id != 0)
            $totalData = Sale::where('warehouse_id', $warehouse_id)->whereDate('created_at', '>=' ,$request->input('starting_date'))->whereDate('created_at', '<=' ,$request->input('ending_date'))->count();
        else
            $totalData = Sale::whereDate('created_at', '>=' ,$request->input('starting_date'))->whereDate('created_at', '<=' ,$request->input('ending_date'))->count();

        $totalFiltered = $totalData;
        if($request->input('length') != -1)
            $limit = $request->input('length');
        else
            $limit = $totalData;
        $start = $request->input('start');
        $order = 'sales.'.$columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');
        if(empty($request->input('search.value'))) {
            if(Auth::user()->role_id > 2 && config('staff_access') == 'own')
                $sales = Sale::with('biller', 'customer', 'warehouse', 'user')
                        ->where('user_id', Auth::id())
                        ->whereDate('created_at', '>=' ,$request->input('starting_date'))
                        ->whereDate('created_at', '<=' ,$request->input('ending_date'))
                        ->offset($start)
                        ->limit($limit)
                        ->orderBy($order, $dir)
                        ->get();
            elseif($warehouse_id != 0)
                $sales = Sale::with('biller', 'customer', 'warehouse', 'user')
                        ->where('warehouse_id', $warehouse_id)
                        ->whereDate('created_at', '>=' ,$request->input('starting_date'))
                        ->whereDate('created_at', '<=' ,$request->input('ending_date'))
                        ->offset($start)
                        ->limit($limit)
                        ->orderBy($order, $dir)
                        ->get();
            else
                $sales = Sale::with('biller', 'customer', 'warehouse', 'user')
                        ->whereDate('created_at', '>=' ,$request->input('starting_date'))
                        ->whereDate('created_at', '<=' ,$request->input('ending_date'))
                        ->offset($start)
                        ->limit($limit)
                        ->orderBy($order, $dir)
                        ->get();
        }
        else
        {
            $search = $request->input('search.value');
            if(Auth::user()->role_id > 2 && config('staff_access') == 'own') {
                $sales =  Sale::select('sales.*')
                            ->with('biller', 'customer', 'warehouse', 'user')
                            ->join('customers', 'sales.customer_id', '=', 'customers.id')
                            ->join('billers', 'sales.biller_id', '=', 'billers.id')
                            ->whereDate('sales.created_at', '=' , date('Y-m-d', strtotime(str_replace('/', '-', $search))))
                            ->where('sales.user_id', Auth::id())
                            ->orwhere([
                                ['sales.reference_no', 'LIKE', "%{$search}%"],
                                ['sales.user_id', Auth::id()]
                            ])
                            ->orwhere([
                                ['customers.name', 'LIKE', "%{$search}%"],
                                ['sales.user_id', Auth::id()]
                            ])
                            ->orwhere([
                                ['customers.phone_number', 'LIKE', "%{$search}%"],
                                ['sales.user_id', Auth::id()]
                            ])
                            ->orwhere([
                                ['billers.name', 'LIKE', "%{$search}%"],
                                ['sales.user_id', Auth::id()]
                            ])
                            ->offset($start)
                            ->limit($limit)
                            ->orderBy($order,$dir)->get();

                $totalFiltered = Sale::
                            join('customers', 'sales.customer_id', '=', 'customers.id')
                            ->join('billers', 'sales.biller_id', '=', 'billers.id')
                            ->whereDate('sales.created_at', '=' , date('Y-m-d', strtotime(str_replace('/', '-', $search))))
                            ->where('sales.user_id', Auth::id())
                            ->orwhere([
                                ['sales.reference_no', 'LIKE', "%{$search}%"],
                                ['sales.user_id', Auth::id()]
                            ])
                            ->orwhere([
                                ['customers.name', 'LIKE', "%{$search}%"],
                                ['sales.user_id', Auth::id()]
                            ])
                            ->orwhere([
                                ['customers.phone_number', 'LIKE', "%{$search}%"],
                                ['sales.user_id', Auth::id()]
                            ])
                            ->orwhere([
                                ['billers.name', 'LIKE', "%{$search}%"],
                                ['sales.user_id', Auth::id()]
                            ])
                            ->count();
            }
            else {
                $sales =  Sale::select('sales.*')
                            ->with('biller', 'customer', 'warehouse', 'user')
                            ->join('customers', 'sales.customer_id', '=', 'customers.id')
                            ->join('billers', 'sales.biller_id', '=', 'billers.id')
                            ->whereDate('sales.created_at', '=' , date('Y-m-d', strtotime(str_replace('/', '-', $search))))
                            ->where('sales.user_id', Auth::id())
                            ->orwhere('sales.reference_no', 'LIKE', "%{$search}%")
                            ->orwhere('customers.name', 'LIKE', "%{$search}%")
                            ->orwhere('customers.phone_number', 'LIKE', "%{$search}%")
                            ->orwhere('billers.name', 'LIKE', "%{$search}%")
                            ->offset($start)
                            ->limit($limit)
                            ->orderBy($order,$dir)->get();

                $totalFiltered = Sale::
                            join('customers', 'sales.customer_id', '=', 'customers.id')
                            ->join('billers', 'sales.biller_id', '=', 'billers.id')
                            ->whereDate('sales.created_at', '=' , date('Y-m-d', strtotime(str_replace('/', '-', $search))))
                            ->where('sales.user_id', Auth::id())
                            ->orwhere('sales.reference_no', 'LIKE', "%{$search}%")
                            ->orwhere('customers.name', 'LIKE', "%{$search}%")
                            ->orwhere('customers.phone_number', 'LIKE', "%{$search}%")
                            ->orwhere('billers.name', 'LIKE', "%{$search}%")
                            ->count();
            }
        }
        $data = array();
        if(!empty($sales))
        {
            foreach ($sales as $key=>$sale)
            {
                $nestedData['id'] = $sale->id;
                $nestedData['key'] = $key;
                $nestedData['date'] = date(config('date_format'), strtotime($sale->created_at->toDateString()));
                $nestedData['reference_no'] = $sale->reference_no;
                $nestedData['biller'] = $sale->biller->name ?? 'NAN';
                $nestedData['customer'] = $sale->customer->name.'<input type="hidden" class="deposit" value="'.($sale->customer->deposit - $sale->customer->expense).'" />'.'<input type="hidden" class="points" value="'.$sale->customer->points.'" />';

                if($sale->sale_status == 1){
                    $nestedData['sale_status'] = '<div class="badge badge-success">'.trans('file.Completed').'</div>';
                    $sale_status = trans('file.Completed');
                }
                elseif($sale->sale_status == 2){
                    $nestedData['sale_status'] = '<div class="badge badge-danger">'.trans('file.Pending').'</div>';
                    $sale_status = trans('file.Pending');
                }
                else{
                    $nestedData['sale_status'] = '<div class="badge badge-warning">'.trans('file.Draft').'</div>';
                    $sale_status = trans('file.Draft');
                }

                if($sale->payment_status == 1)
                    $nestedData['payment_status'] = '<div class="badge badge-danger">'.trans('file.Pending').'</div>';
                elseif($sale->payment_status == 2)
                    $nestedData['payment_status'] = '<div class="badge badge-danger">'.trans('file.Due').'</div>';
                elseif($sale->payment_status == 3)
                    $nestedData['payment_status'] = '<div class="badge badge-warning">'.trans('file.Partial').'</div>';
                else
                    $nestedData['payment_status'] = '<div class="badge badge-success">'.trans('file.Paid').'</div>';

                $nestedData['grand_total'] = number_format($sale->grand_total, 2);
                $nestedData['paid_amount'] = number_format($sale->paid_amount, 2);
                $nestedData['due'] = number_format($sale->grand_total - $sale->paid_amount, 2);
                $nestedData['options'] = '<div class="btn-group">
                            <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">'.trans("file.action").'
                              <span class="caret"></span>
                              <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu">
                                <li><a href="'.route('sale.invoice', $sale->id).'" class="btn btn-link"><i class="fa fa-copy"></i> '.trans('file.Generate Invoice').'</a></li>
                                <li>
                                    <button type="button" class="btn btn-link view"><i class="fa fa-eye"></i> '.trans('file.View').'</button>
                                </li>';
                if(in_array("sales-edit", $request['all_permission'])){
                    if($sale->sale_status != 3)
                        $nestedData['options'] .= '<li>
                            <a href="'.route('sales.edit', $sale->id).'" class="btn btn-link"><i class="dripicons-document-edit"></i> '.trans('file.edit').'</a>
                            </li>';
                    else
                        $nestedData['options'] .= '<li>
                            <a href="'.url('sales/'.$sale->id.'/create').'" class="btn btn-link"><i class="dripicons-document-edit"></i> '.trans('file.edit').'</a>
                        </li>';
                }
                $nestedData['options'] .=
                    '<li>
                        <button type="button" class="add-payment btn btn-link" data-id = "'.$sale->id.'" data-toggle="modal" data-target="#add-payment"><i class="fa fa-plus"></i> '.trans('file.Add Payment').'</button>
                    </li>
                    <li>
                        <button type="button" class="get-payment btn btn-link" data-id = "'.$sale->id.'"><i class="fa fa-money"></i> '.trans('file.View Payment').'</button>
                    </li>
                    <li>
                        <button type="button" class="add-delivery btn btn-link" data-id = "'.$sale->id.'"><i class="fa fa-truck"></i> '.trans('file.Add Delivery').'</button>
                    </li>';
                if(in_array("sales-delete", $request['all_permission']))
                    $nestedData['options'] .= \Form::open(["route" => ["sales.destroy", $sale->id], "method" => "DELETE"] ).'
                            <li>
                              <button type="submit" class="btn btn-link" onclick="return confirmDelete()"><i class="dripicons-trash"></i> '.trans("file.delete").'</button>
                            </li>'.\Form::close().'
                        </ul>
                    </div>';
                // data for sale details by one click
                $coupon = Coupon::find($sale->coupon_id);
                if($coupon)
                    $coupon_code = $coupon->code;
                else
                    $coupon_code = null;

                $nestedData['sale'] = array( '[ "'.date(config('date_format'), strtotime($sale->created_at->toDateString())).'"', ' "'.$sale->reference_no.'"', ' "'.$sale_status.'"', ' "'.$sale->biller->name.'"', ' "'.$sale->biller->company_name.'"', ' "'.$sale->biller->email.'"', ' "'.$sale->biller->phone_number.'"', ' "'.$sale->biller->address.'"', ' "'.$sale->biller->city.'"', ' "'.$sale->customer->name.'"', ' "'.$sale->customer->phone_number.'"', ' "'.$sale->customer->address.'"', ' "'.$sale->customer->city.'"', ' "'.$sale->id.'"', ' "'.$sale->total_tax.'"', ' "'.$sale->total_discount.'"', ' "'.$sale->total_price.'"', ' "'.$sale->order_tax.'"', ' "'.$sale->order_tax_rate.'"', ' "'.$sale->order_discount.'"', ' "'.$sale->shipping_cost.'"', ' "'.$sale->grand_total.'"', ' "'.$sale->paid_amount.'"', ' "'.preg_replace('/[\n\r]/', "<br>", $sale->sale_note).'"', ' "'.preg_replace('/[\n\r]/', "<br>", $sale->staff_note).'"', ' "'.$sale->user->name.'"', ' "'.$sale->user->email.'"', ' "'.$sale->warehouse->name.'"', ' "'.$coupon_code.'"', ' "'.$sale->coupon_discount.'"]'
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
        if($role->hasPermissionTo('sales-add')) {
            $lims_customer_list = Customer::where('is_active', true)->get();
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

            return view('sale.create',compact('all_permission', 'lims_customer_list', 'lims_warehouse_list', 'lims_biller_list', 'lims_pos_setting_data', 'lims_tax_list', 'lims_reward_point_setting_data'));
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    public function store(Request $request)
    {
        $data = $request->all();
        if(isset($request->reference_no)) {
            $this->validate($request, [
                'reference_no' => [
                    'max:191', 'required', 'unique:sales'
                ],
            ]);
        }
        //return dd($data);
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
                $data['reference_no'] = 'posr-' . date("Ymd") . '-'. date("his");

            $balance = $data['grand_total'] - $data['paid_amount'];
            if($balance > 0 || $balance < 0)
                $data['payment_status'] = 2;
            else
                $data['payment_status'] = 4;

            if($data['paid_by_id'] == 8 || $data['paid_by_id'] == 9) {
                $data['payment_status'] = 1;
                $data['paid_amount'] = 0;
                if($data['paid_by_id'] == 9) {
                    $paying_method = 'Pay Later';
                }
            }
            if($data['draft']) {
                $lims_sale_data = Sale::find($data['sale_id']);
                $lims_product_sale_data = Product_Sale::where('sale_id', $data['sale_id'])->get();
                foreach ($lims_product_sale_data as $product_sale_data) {
                    $product_sale_data->delete();
                }
                $lims_sale_data->delete();
            }
        }
        else {
            if(!isset($data['reference_no']))
                $data['reference_no'] = 'sr-' . date("Ymd") . '-'. date("his");
        }

        $document = $request->document;
        if ($document) {
            $v = Validator::make(
                [
                    'extension' => strtolower($request->document->getClientOriginalExtension()),
                ],
                [
                    'extension' => 'in:jpg,jpeg,png,gif,pdf,csv,docx,xlsx,txt',
                ]
            );
            if ($v->fails())
                return redirect()->back()->withErrors($v->errors());

            $documentName = $document->getClientOriginalName();
            $document->move('public/sale/documents', $documentName);
            $data['document'] = $documentName;
        }
        if($data['coupon_active']) {
            $lims_coupon_data = Coupon::find($data['coupon_id']);
            $lims_coupon_data->used += 1;
            $lims_coupon_data->save();
        }

        $lims_customer_data = Customer::find($data['customer_id']);
        if($data['paid_by_id'] == 10 && $lims_customer_data->credit_limit != null) {
            $customer_balance = $lims_customer_data->deposit - $data['paid_amount'];
            if ($lims_customer_data->credit_limit + $customer_balance < 0) {
                return back()->with('not_permitted', 'Your credit limit is exceeded.');
            }
        }

        if($data['paid_by_id'] == 11) {
            $customer_group = CustomerGroup::where('id',$lims_customer_data->customer_group_id)->first();
            if($customer_group) {
                $data['customer_group_id'] = $customer_group->id;
                if ($customer_group->credit_limit != null) {
                    $after_sale_total_credit = $customer_group->deposit - $data['paid_amount'];
                    if ($customer_group->credit_limit + $after_sale_total_credit < 0) {
                        return back()->with('not_permitted', 'Your Group Credit limit is exceeded.');
                    }
                }
            } else {
                return back()->with('not_permitted', 'You do not have customer Group.');
            }
        }


        $lims_sale_data = Sale::create($data);

        $lims_reward_point_setting_data = RewardPointSetting::latest()->first();
        //checking if customer gets some points or not
        if($lims_reward_point_setting_data->is_active &&  $data['grand_total'] >= $lims_reward_point_setting_data->minimum_amount) {
            $point = (int)($data['grand_total'] / $lims_reward_point_setting_data->per_point_amount);
            $lims_customer_data->points += $point;
            $lims_customer_data->save();
        }

        if ($data['paid_by_id'] == 10) {
            $lims_customer_data->deposit -= $lims_sale_data->paid_amount;
            $lims_customer_data->save();
        }

        if ($data['paid_by_id'] == 11) {
            $customer_group->deposit -= $lims_sale_data->paid_amount;
            $customer_group->save();
        }

        //collecting male data
        $mail_data['email'] = $lims_customer_data->email;
        $mail_data['reference_no'] = $lims_sale_data->reference_no;
        $mail_data['sale_status'] = $lims_sale_data->sale_status;
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
            if($lims_product_data->type == 'combo' && $data['sale_status'] == 1){
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
                    if($child_warehouse_data->qty < 0) {
                        $child_warehouse_data->qty = 0;
                    }

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

                if($data['sale_status'] == 1) {
                    if($lims_sale_unit_data->operator == '*')
                        $quantity = $qty[$i] * $lims_sale_unit_data->operation_value;
                    elseif($lims_sale_unit_data->operator == '/')
                        $quantity = $qty[$i] / $lims_sale_unit_data->operation_value;
                    //deduct quantity
                    $lims_product_data->qty = $lims_product_data->qty - $quantity;
                    if ($lims_product_data->qty < 0) {
                        $lims_product_data->qty = 0;
                    }
                    $this->stockDurationSave($lims_product_data->id, $lims_product_data->qty);
                    $lims_product_data->save();
                    //deduct product variant quantity if exist
                    $multi_qty = 1;
                    if($lims_product_data->is_variant) {
                        $lims_product_variant_data->qty -= $quantity;
                        if ($lims_product_variant_data->qty < 0) {
                            $lims_product_variant_data->qty = 0;
                        }
                        $lims_product_variant_data->save();
                        $lims_product_warehouse_data = Product_Warehouse::FindProductWithVariant($id, $lims_product_variant_data->variant_id, $data['warehouse_id'])->first();
                    }
                    elseif($product_batch_id[$i] && $product_batch_id[$i] != "null") {
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
                                    if ($lims_product_batch_data->qty < 0) {
                                        $lims_product_batch_data->qty = 0;
                                    }
                                    $multi_product_batch_id[] = $lims_product_batch_data->id;
                                    $multi_product_batch_qty[] = $multi_qty;
                                    $lims_product_batch_data->save();
                                    $lims_product_warehouse_data->qty -= $multi_qty;
                                    if ($lims_product_batch_data->qty < 0) {
                                        $lims_product_batch_data->qty = 0;
                                    }
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
                            if ($lims_product_batch_data->qty < 0) {
                                $lims_product_batch_data->qty = 0;
                            }
                            $lims_product_batch_data->save();
                        }

                    }
                    else {
                        $lims_product_warehouse_data = Product_Warehouse::FindProductWithoutVariant($id, $data['warehouse_id'])->first();
                    }

                    if ($multi_qty == 1) {
                        //deduct quantity from warehouse
                        if($lims_product_warehouse_data) {
                            $lims_product_warehouse_data->qty -= $quantity;
                            if ($lims_product_warehouse_data->qty < 0) {
                                $lims_product_warehouse_data->qty = 0;
                            }
                            $lims_product_warehouse_data->save();
                        }
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

            $product_sale['sale_id'] = $lims_sale_data->id ;
            $product_sale['category_id'] = $lims_product_data->category_id;
            $product_sale['warehouse_id'] = (int)$data['warehouse_id'];
            $product_sale['product_id'] = $id;
            $product_sale['qty'] = $mail_data['qty'][$i] = $qty[$i];
            $product_sale['sale_unit_id'] = $sale_unit_id;
            $product_sale['net_unit_price'] = $net_unit_price[$i];
            $product_sale['discount'] = $discount[$i];
            $product_sale['tax_rate'] = $tax_rate[$i];
            $product_sale['tax'] = $tax[$i];
            $product_sale['total'] = $mail_data['total'][$i] = $total[$i];
            $this->stockDurationSave($lims_product_data->id, $lims_product_data->qty);
            Product_Sale::create($product_sale);
        }
        if($data['sale_status'] == 3)
            $message = 'Sale successfully added to draft';
        else
            $message = ' Sale created successfully';
        if($mail_data['email'] && $data['sale_status'] == 1) {
            try {
                Mail::send( 'mail.sale_details', $mail_data, function( $message ) use ($mail_data)
                {
                    $message->to( $mail_data['email'] )->subject( 'Sale Details' );
                });
            }
            catch(\Exception $e){
                $message = ' Sale created successfully. Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
            }
        }

        if($data['payment_status'] == 2 || $data['payment_status'] == 3  || $data['payment_status'] == 4 || ($data['pos'] && $data['paying_amount'] > 0)) {

            $lims_payment_data = new Payment();
            $lims_payment_data->user_id = Auth::id();

            if($data['paid_by_id'] == 1)
                $paying_method = 'Cash';
            elseif ($data['paid_by_id'] == 2) {
                $paying_method = 'Gift Card';
            }
            elseif ($data['paid_by_id'] == 10) {
                $paying_method = 'Credit';
            }
            elseif ($data['paid_by_id'] == 11) {
                $paying_method = 'Group Credit';
            }
            elseif ($data['paid_by_id'] == 9) {
                $paying_method = 'Pay Later';
            }
            elseif ($data['paid_by_id'] == 8) {

                $biller = Biller::find($request->biller_id);
                $this->sendWhatsappMsg($lims_customer_data, $lims_sale_data, $mail_data, $biller, 'Momo/Orange', $net_unit_price);

                $data['status'] = 0;

                $token = getenv("MOMO_TOKEN");
                $route = route('pos.payment_check');
                $mtn_number = $lims_customer_data->phone_number;
                $amount = $lims_sale_data->grand_total;
                $link = $this->mobileMoneyRequestLink($token, $amount, $route, $lims_sale_data->id, $mtn_number);
                if ($link == false) {
                    $message = 'Phone Number is incorrect or There is any other issue in payment method';
                    return back()->with('not_permitted', $message);
                }
                Session::put('data', $data);

                header("Location: $link");
                die();
            }
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
                $lims_payment_data_debit->debit_sale_id = $lims_sale_data->id;
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
            if(!array_key_exists('credit', $data)) {
                $lims_account_data = Account::where('is_default', true)->first();
            }else{
                $lims_account_data = Account::where('id', $data['credit'])->first();
            }
            $lims_payment_data->account_id = $lims_account_data->id;
            $lims_payment_data->sale_id = $lims_sale_data->id;
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
                $lims_customer_data->deposit -= $data['paid_amount'];
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
        elseif($data['paid_by_id'] == 8)
            $paying_method = 'Momo/Orange';
        elseif($data['paid_by_id'] == 9)
            $paying_method = 'Pay Later';
        elseif($data['paid_by_id'] == 10)
            $paying_method = 'Credit';
        elseif($data['paid_by_id'] == 11)
            $paying_method = 'Group Credit';

        $message = $this->sendWhatsappMsg($lims_customer_data, $lims_sale_data, $mail_data, $biller, $paying_method, $net_unit_price);

        $role = Role::find(Auth::user()->role_id);
        $role->revokePermissionTo('search_all_products');

        if($data['paid_by_id'] == 9) {
            return back()->with('message', $message)        ;
        }

        if ($data['payment_status'] == 4) {
            if ($lims_sale_data->products->count() > 0) {
                Delivery::create([
                    'sale_id' => $lims_sale_data->id,
                    'reference_no' => 'dr-' . date("Ymd") . '-'. date("his"),
                    'user_id' => Auth::id(),
                    'customer_id' => $lims_customer_data->id,
                    'address' => $lims_customer_data->address.' '.$lims_customer_data->city.' '.$lims_customer_data->country,
                    'delivered_by' => Auth::user()->name,
                    'recieved_by' => $lims_customer_data->name,
                    'status' => 0,
                ]);
            }
        }

        if($data['pos'] == 1) {
            if($lims_sale_data->sale_status == '1') {
                return redirect('sales/gen_invoice/' . $lims_sale_data->id)->with('message', $message);
            }
            return redirect('pos')->with('message', $message);
        }else{
            if($lims_sale_data->sale_status == '1') {
                return redirect('sales/gen_invoice/' . $lims_sale_data->id)->with('message', $message);
            }
            return redirect('sales')->with('message', $message);
        }
    }


    public function addPaymentMTN(Request $request)
    {
        $data = $request->all();
//        dd($data);
        if($data['status'] != 'SUCCESSFUL'){
            return back()->with('not_permitted', 'payment failed.');
        }

        $data['sale_id'] = $data['external_reference'];
        $data['amount'] = $data['app_amount'];
        $data['payment_reference'] = $data['reference'];

        if (!$data['amount'])
            $data['amount'] = 0.00;

        $lims_sale_data = Sale::find($data['sale_id']);
        $lims_customer_data = Customer::find($lims_sale_data->customer_id);

        $lims_sale_data->paid_amount += $data['amount'];
        $balance = $lims_sale_data->grand_total - $lims_sale_data->paid_amount;
        if ($balance > 0 || $balance < 0) {

            $lims_sale_data->payment_status = 2;
        } elseif ($balance == 0) {
            $lims_sale_data->payment_status = 4;
            if ($lims_sale_data->products->count() > 0) {
                Delivery::create([
                    'sale_id' => $lims_sale_data->id,
                    'reference_no' => 'dr-' . date("Ymd") . '-' . date("his"),
                    'user_id' => Auth::id(),
                    'customer_id' => $lims_customer_data->id,
                    'address' => $lims_customer_data->address . ' ' . $lims_customer_data->city . ' ' . $lims_customer_data->country,
                    'delivered_by' => Auth::user()->name,
                    'recieved_by' => $lims_customer_data->name,
                    'status' => 0,
                ]);
            }
        }
        $paying_method = 'Momo/Orange';

        $cash_register_data = CashRegister::where([
            ['user_id', Auth::id()],
            ['warehouse_id', $lims_sale_data->warehouse_id],
            ['status', true]
        ])->first();

        $lims_payment_data = new Payment();
        $lims_payment_data->user_id = Auth::id();
        $lims_payment_data->sale_id = $lims_sale_data->id;
        if($cash_register_data)
            $lims_payment_data->cash_register_id = $cash_register_data->id;
        $lims_payment_data->account_id = 1;
        $data['payment_reference'] = 'spr-' . date("Ymd") . '-'. date("his");
        $lims_payment_data->payment_reference = $data['payment_reference'];
        $lims_payment_data->amount = $data['amount'];
        $lims_payment_data->change = $balance - $data['amount'];
        $lims_payment_data->paying_method = $paying_method;
        $lims_payment_data->payment_note = $data['payment_note'] ?? '';
        $lims_payment_data->save();
        $lims_sale_data->save();

        $lims_payment_data = Payment::latest()->first();

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
        return back()->with('message', $message);
    }

    public function addPaymentMTNPOS(Request $request)
    {
        $data = $request->all();
//        dd($data);
        if($data['status'] != 'SUCCESSFUL'){
            return back()->with('not_permitted', 'payment failed.');
        }

        $data['sale_id'] = $data['external_reference'];
        $data['amount'] = $data['app_amount'];
        $data['payment_reference'] = $data['reference'];

        if (!$data['amount'])
            $data['amount'] = 0.00;

        $lims_sale_data = Sale::find($data['sale_id']);
        $lims_customer_data = Customer::find($lims_sale_data->customer_id);

        $lims_sale_data->paid_amount += $data['amount'];
        $balance = $lims_sale_data->grand_total - $lims_sale_data->paid_amount;
        if ($balance > 0 || $balance < 0) {
            $lims_sale_data->payment_status = 2;
        } elseif ($balance == 0) {
            $lims_sale_data->payment_status = 4;
        }
        $paying_method = 'Momo/Orange';

        $cash_register_data = CashRegister::where([
            ['user_id', Auth::id()],
            ['warehouse_id', $lims_sale_data->warehouse_id],
            ['status', true]
        ])->first();

        $lims_payment_data = new Payment();
        $lims_payment_data->user_id = Auth::id();
        $lims_payment_data->sale_id = $lims_sale_data->id;
        if($cash_register_data)
            $lims_payment_data->cash_register_id = $cash_register_data->id;
        $lims_payment_data->account_id = 1;
        $data['payment_reference'] = 'spr-' . date("Ymd") . '-'. date("his");
        $lims_payment_data->payment_reference = $data['payment_reference'];
        $lims_payment_data->amount = $data['amount'];
        $lims_payment_data->change = $balance - $data['amount'];
        $lims_payment_data->paying_method = $paying_method;
        $lims_payment_data->payment_note = $data['payment_note'] ?? '';
        $lims_payment_data->save();
        $lims_sale_data->save();

        $lims_payment_data = Payment::latest()->first();

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

        return redirect('sales/gen_invoice/' . $lims_sale_data->id)->with('message', $message);
    }

    public function sendWhatsappMsg($lims_customer_data, $lims_sale_data, $mail_data, $biller, $paying_method, $net_unit_price){
        $general_setting = GeneralSetting::first();

        $msg = '*Subject:* Sales Confirmation for '. $lims_customer_data->name . '\n\n';
        $msg .= 'Dear '. $lims_customer_data->name . '\n\n';
        $msg .= 'Thank you for choosing '.$general_setting->site_title.' as your preferred supplier. We are pleased to confirm the availability of the products requested.\n\n\n';

        $msg .= '*Order Details:*\n';
        $msg .= 'Order Number: '.$lims_sale_data->reference_no.'\n';
        $msg .=  'Order Date: '.$lims_sale_data->created_at.'\n\n';

        $msg .= '*Product Detail:*\n';
        foreach ($mail_data['products'] as $key => $product) {
            $msg .= $key+1 .') ['. $product . '] [' . $mail_data['qty'][$key] . '] x [ '. number_format($net_unit_price[$key], 2) .'] = ['. number_format($mail_data['total'][$key], 2) .']\n';
        }

        $msg .= 'Total Amount: ' . number_format($mail_data['grand_total'], 2) . '\n\n';

        $msg .= '*Payment Information:*\n';
        $msg .= 'Payment Method: ' . $paying_method . '\n';
        $msg .= 'Billing Address: ' . @$biller->address . '\n';
        $msg .= 'Delivery Information: ' . $lims_customer_data->address . '\n';
        $msg .= 'Estimated Delivery Date: ' . $lims_sale_data->created_at . '\n\n';

        $msg .= 'Once again, we appreciate your business and trust in '. $general_setting->site_title .'. We strive to provide exceptional products and services, and we are confident that you will be satisfied with our products.\n';
        $msg .= 'Thank you for choosing ' . $general_setting->site_title . '.\n\n';

        $msg .= 'Best regards,\n';
        $msg .= @$biller->name. '\n';
        $msg .= $general_setting->site_title;

        $message = 'Sale created successfully';
        try{
            $this->wpMessage($lims_customer_data->phone_number, $msg);
        }
        catch(\Exception $e){
            $message = 'Sale created successfully. Please setup your whatsapp setting.';
        }

        // send QR code
        $path = public_path('public/images/sales/qr/');
        if (!File::exists($path)) {
            File::makeDirectory($path, 0755, true);
        }
        $filename = 'qr_code_' . $lims_sale_data->reference_no . '.png';
        $url = url("sale/scan/$lims_sale_data->reference_no");
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

    public function sendMail(Request $request)
    {
        $data = $request->all();
        $lims_sale_data = Sale::find($data['sale_id']);
        $lims_product_sale_data = Product_Sale::where('sale_id', $data['sale_id'])->get();
        $lims_customer_data = Customer::find($lims_sale_data->customer_id);
        if($lims_customer_data->email) {
            //collecting male data
            $mail_data['email'] = $lims_customer_data->email;
            $mail_data['reference_no'] = $lims_sale_data->reference_no;
            $mail_data['sale_status'] = $lims_sale_data->sale_status;
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
            }

            try{
                Mail::send( 'mail.sale_details', $mail_data, function( $message ) use ($mail_data)
                {
                    $message->to( $mail_data['email'] )->subject( 'Sale Details' );
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

    public function paypalSuccess(Request $request)
    {
        $lims_sale_data = Sale::latest()->first();
        $lims_payment_data = Payment::latest()->first();
        $lims_product_sale_data = Product_Sale::where('sale_id', $lims_sale_data->id)->get();
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
        $role = Role::find(Auth::user()->role_id);
        $lims_product_warehouse_data = Product::join('product_warehouse', 'products.id', '=', 'product_warehouse.product_id')
        ->where([
            ['products.is_active', true],
            ['product_warehouse.warehouse_id', $id],
            ['product_warehouse.qty', '>', 0]
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
            ['product_warehouse.qty', '>', 0]
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
            ['product_warehouse.qty', '>', 0]
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

        if ($role->hasPermissionTo('search_all_products')) {
            $zero_stock = Product::whereNotIn('id', $product_id)->where('is_active', true)->get();
            foreach ($zero_stock as $zero) {
                $product_qty[] = $zero->qty;
                $product_price[] = $zero->price;
                $lims_product_data = $zero;
                $product_code[] = $zero->code;
                $product_name[] = htmlspecialchars($zero->name);
                $product_type[] = $zero->type;
                $product_id[] = $zero->id;
                $product_list[] = null;
                $qty_list[] = null;
                $batch_no[] = null;
                $product_batch_id[] = null;
                $product_expired_date[] = null;
            }
        }

        $product_data = [$product_code, $product_name, $product_qty, $product_type, $product_id, $product_list, $qty_list, $product_price, $batch_no, $product_batch_id, $product_expired_date];
        return $product_data;
    }

    public function searchAllProducts()
    {
        $role = Role::find(Auth::user()->role_id);
        if (!$role->hasPermissionTo('search_all_products')) {
            $permission = Permission::firstOrCreate(['name' => 'search_all_products']);
            $role->givePermissionTo($permission);
        }

        return redirect()->route('sale.pos');
    }

    public function searchQuickProducts()
    {
        $role = Role::find(Auth::user()->role_id);
        if ($role->hasPermissionTo('search_all_products')) {
            $role->revokePermissionTo('search_all_products');
        }

        return redirect()->route('sale.pos');
    }

    public function posSale()
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('sales-add')){
            $permissions = Role::findByName($role->name)->permissions;
            foreach ($permissions as $permission)
                $all_permission[] = $permission->name;
            if(empty($all_permission))
                $all_permission[] = 'dummy text';

            $lims_customer_list = Customer::select('id', 'name', 'phone_number', 'email', 'address', 'city', 'deposit', 'expense', 'points')->where('is_active', true)->get();
            $customer_owing = \App\Sale::selectRaw('customer_id, SUM(grand_total - paid_amount) as owing')
                ->where('payment_status', '!=', 4)
                ->whereColumn('paid_amount', '<', 'grand_total')
                ->groupBy('customer_id')
                ->pluck('owing', 'customer_id')
                ->toArray();
            $lims_account_list = Account::select('id', 'name', 'account_no', 'is_default', 'is_default_debit', 'is_active')->where('is_active', true)->get();
            $lims_customer_group_all = CustomerGroup::select('id', 'name')->where('is_active', true)->get();
            $lims_warehouse_list = Warehouse::select('id', 'name')->where('is_active', true)->get();
            $lims_biller_list = Biller::select('id', 'name', 'company_name', 'email', 'phone_number', 'address', 'city')->where('is_active', true)->get();
            $lims_reward_point_setting_data = RewardPointSetting::latest()->first();
            $lims_tax_list = Tax::select('id', 'name', 'rate')->where('is_active', true)->get();
            $lims_unit_list = Unit::select('id', 'unit_name', 'unit_code')->where('is_active', true)->get();
            $lims_category_list = Category::select('id', 'name', 'parent_id')->where('is_active', true)->get();

            $canSearchAllProducts = $role->hasPermissionTo('search_all_products');
            $baseProductsLimit = $canSearchAllProducts ? 900 : 300;
            $variantProductsLimit = $canSearchAllProducts ? 400 : 180;

            $baseProductQuery = Product::select('id', 'name', 'code', 'image')
                ->ActiveFeatured()
                ->whereNull('is_variant')
                ->orderBy('id', 'desc')
                ->take($baseProductsLimit);
            if (!$canSearchAllProducts) {
                $baseProductQuery->where('qty', '>', 0);
            }
            $lims_product_list = $baseProductQuery->get();

            foreach ($lims_product_list as $key => $product) {
                $images = explode(",", $product->image);
                $product->base_image = $images[0];
            }

            $variantProductQuery = Product::select('id', 'name', 'code', 'image')
                ->ActiveFeatured()
                ->whereNotNull('is_variant')
                ->with(['variant' => function ($query) {
                    $query->select('variants.id', 'name')->orderBy('position');
                }])
                ->orderBy('id', 'desc')
                ->take($variantProductsLimit);
            if (!$canSearchAllProducts) {
                $variantProductQuery->where('qty', '>', 0);
            }
            $lims_product_list_with_variant = $variantProductQuery->get();

            foreach ($lims_product_list_with_variant as $product) {
                $images = explode(",", $product->image);
                $product->base_image = $images[0];
                $lims_product_variant_data = $product->variant;
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
            $lims_brand_list = Brand::select('id', 'title', 'image')->where('is_active', true)->get();

            if(Auth::user()->role_id > 2 && config('staff_access') == 'own') {
                $recent_sale = Sale::where([
                    ['sale_status', 1],
                    ['user_id', Auth::id()]
                ])->orderBy('id', 'desc')->take(10)->get();
                $recent_draft = Sale::where([
                    ['sale_status', 3],
                    ['user_id', Auth::id()]
                ])->orderBy('id', 'desc')->take(10)->get();
            }
            else {
                $recent_sale = Sale::where('sale_status', 1)->orderBy('id', 'desc')->take(10)->get();
                $recent_draft = Sale::where('sale_status', 3)->orderBy('id', 'desc')->take(10)->get();
            }

            $lims_account_default = Account::where('is_default', true)->first();
            $lims_account_default_debit = Account::where('is_default_debit', true)->first();
            $lims_coupon_list = Coupon::where('is_active',true)->get();
            $flag = 0;

            return view('sale.pos', compact('all_permission', 'lims_customer_list', 'customer_owing', 'lims_customer_group_all', 'lims_warehouse_list', 'lims_reward_point_setting_data', 'lims_product_list', 'product_number', 'lims_tax_list', 'lims_biller_list', 'lims_pos_setting_data', 'lims_brand_list', 'lims_category_list', 'recent_sale', 'recent_draft', 'lims_coupon_list', 'flag', 'lims_account_list', 'lims_account_default', 'lims_account_default_debit', 'lims_category_list', 'lims_unit_list'));
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



    public function storeModel(Request $request)
    {
        $this->validate($request, [
            'name' => [
                'max:255',
                Rule::unique('products')->where(function ($query) {
                    return $query->where('is_active', 1);
                }),
            ]
        ]);

        $data = $request->all();
        $data['type'] = 'standard';
        $data['barcode_symbology'] = 'C128';
        $data['name'] = htmlspecialchars(trim($data['product_name']));
        $data['code'] = mt_rand(10000000, 99999999);
        $data['price'] = $data['product_price'];
        $data['cost'] = $data['product_price'] - ($data['product_price']/100 * $data['profit']);
        $data['unit_id'] = $data['unit'];
        $data['category_id'] = $data['category'];
        $data['purchase_unit_id'] = $data['unit'];
        $data['sale_unit_id'] = 0;
        $data['rent_price_per_hour'] =  0;
        $data['rent_price_per_day'] = 0;
        $data['rent_price_per_month'] = 0;
        $data['qty'] = 0;
        $data['is_active'] = true;
        $data['featured'] = true;
        $data['image'] = 'zummXD2dvAtI.png';
        $lims_product_data = Product::create($data);

        $warehouse = Warehouse::where('is_active', true)->first();
        Product_Warehouse::create([
            "product_id" => $lims_product_data->id,
            "warehouse_id" => $warehouse->id,
            "qty" => $data['qty'],
        ]);

        if($lims_product_data) {

            $todayDate = date('Y-m-d');

            $product[] = $lims_product_data->name;
            $product[] = $lims_product_data->code;
            $product[] = $lims_product_data->price;
            $product[] = 0;
            $product[] = 'No Tax';
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
            return $product;

        } else {
            return back()->with('not_permitted', 'Product created successfully');
        }
    }



    public function limsProductSearch(Request $request)
    {
        $todayDate = date('Y-m-d');
        $product_code = explode("(", $request['data']);
        $product_code[0] = rtrim($product_code[0], " ");
        $product_variant_id = null;
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
        return $product;

    }

    public function getGiftCard()
    {
        $gift_card = GiftCard::where("is_active", true)->whereDate('expired_date', '>=', date("Y-m-d"))->get(['id', 'card_no', 'amount', 'expense']);
        return json_encode($gift_card);
    }

    public function productSaleData($id)
    {
        $lims_product_sale_data = Product_Sale::where('sale_id', $id)->get();
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
        }
        return $product_sale;
    }

    public function saleByCsv()
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('sales-add')){
            $lims_customer_list = Customer::where('is_active', true)->get();
            $lims_warehouse_list = Warehouse::where('is_active', true)->get();
            $lims_biller_list = Biller::where('is_active', true)->get();
            $lims_tax_list = Tax::where('is_active', true)->get();

            return view('sale.import',compact('lims_customer_list', 'lims_warehouse_list', 'lims_biller_list', 'lims_tax_list'));
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    public function importSale(Request $request)
    {
        //get the file
        $upload=$request->file('file');
        $ext = pathinfo($upload->getClientOriginalName(), PATHINFO_EXTENSION);
        //checking if this is a CSV file
        if($ext != 'csv')
            return redirect()->back()->with('message', 'Please upload a CSV file');

        $filePath=$upload->getRealPath();
        $file_handle = fopen($filePath, 'r');
        $i = 0;
        //validate the file
        while (!feof($file_handle) ) {
            $current_line = fgetcsv($file_handle);
            if($current_line && $i > 0){
                $product_data[] = Product::where('code', $current_line[0])->first();
                if(!$product_data[$i-1])
                    return redirect()->back()->with('message', 'Product does not exist!');
                $unit[] = Unit::where('unit_code', $current_line[2])->first();
                if(!$unit[$i-1] && $current_line[2] == 'n/a')
                    $unit[$i-1] = 'n/a';
                elseif(!$unit[$i-1]){
                    return redirect()->back()->with('message', 'Sale unit does not exist!');
                }
                if(strtolower($current_line[5]) != "no tax"){
                    $tax[] = Tax::where('name', $current_line[5])->first();
                    if(!$tax[$i-1])
                        return redirect()->back()->with('message', 'Tax name does not exist!');
                }
                else
                    $tax[$i-1]['rate'] = 0;

                $qty[] = $current_line[1];
                $price[] = $current_line[3];
                $discount[] = $current_line[4];
            }
            $i++;
        }
        //return $unit;
        $data = $request->except('document');
        $data['reference_no'] = 'sr-' . date("Ymd") . '-'. date("his");
        $data['user_id'] = Auth::user()->id;
        $document = $request->document;
        if ($document) {
            $v = Validator::make(
                [
                    'extension' => strtolower($request->document->getClientOriginalExtension()),
                ],
                [
                    'extension' => 'in:jpg,jpeg,png,gif,pdf,csv,docx,xlsx,txt',
                ]
            );
            if ($v->fails())
                return redirect()->back()->withErrors($v->errors());

            $ext = pathinfo($document->getClientOriginalName(), PATHINFO_EXTENSION);
            $documentName = $data['reference_no'] . '.' . $ext;
            $document->move('public/documents/sale', $documentName);
            $data['document'] = $documentName;
        }
        $item = 0;
        $grand_total = $data['shipping_cost'];
        Sale::create($data);
        $lims_sale_data = Sale::latest()->first();
        $lims_customer_data = Customer::find($lims_sale_data->customer_id);

        foreach ($product_data as $key => $product) {
            if($product['tax_method'] == 1){
                $net_unit_price = $price[$key] - $discount[$key];
                $product_tax = $net_unit_price * ($tax[$key]['rate'] / 100) * $qty[$key];
                $total = ($net_unit_price * $qty[$key]) + $product_tax;
            }
            elseif($product['tax_method'] == 2){
                $net_unit_price = (100 / (100 + $tax[$key]['rate'])) * ($price[$key] - $discount[$key]);
                $product_tax = ($price[$key] - $discount[$key] - $net_unit_price) * $qty[$key];
                $total = ($price[$key] - $discount[$key]) * $qty[$key];
            }
            if($data['sale_status'] == 1 && $unit[$key]!='n/a'){
                $sale_unit_id = $unit[$key]['id'];
                if($unit[$key]['operator'] == '*')
                    $quantity = $qty[$key] * $unit[$key]['operation_value'];
                elseif($unit[$key]['operator'] == '/')
                    $quantity = $qty[$key] / $unit[$key]['operation_value'];
                $product['qty'] -= $quantity;
                $product_warehouse = Product_Warehouse::where([
                    ['product_id', $product['id']],
                    ['warehouse_id', $data['warehouse_id']]
                ])->first();
                $product_warehouse->qty -= $quantity;
                if ($product_warehouse->qty < 0) {
                    $product_warehouse->qty = 0;
                }
                $product->save();
                $product_warehouse->save();
            }
            else
                $sale_unit_id = 0;
            //collecting mail data
            $mail_data['products'][$key] = $product['name'];
            if($product['type'] == 'digital')
                $mail_data['file'][$key] = url('/public/product/files').'/'.$product['file'];
            else
                $mail_data['file'][$key] = '';
            if($sale_unit_id)
                $mail_data['unit'][$key] = $unit[$key]['unit_code'];
            else
                $mail_data['unit'][$key] = '';

            $product_sale = new Product_Sale();
            $product_sale->sale_id = $lims_sale_data->id;
            $product_sale->product_id = $product['id'];
            $product_sale->qty = $mail_data['qty'][$key] = $qty[$key];
            $product_sale->sale_unit_id = $sale_unit_id;
            $product_sale->net_unit_price = number_format((float)$net_unit_price, 2, '.', '');
            $product_sale->discount = $discount[$key] * $qty[$key];
            $product_sale->tax_rate = $tax[$key]['rate'];
            $product_sale->tax = number_format((float)$product_tax, 2, '.', '');
            $product_sale->total = $mail_data['total'][$key] = number_format((float)$total, 2, '.', '');
            $product_sale->save();
            $lims_sale_data->total_qty += $qty[$key];
            $lims_sale_data->total_discount += $discount[$key] * $qty[$key];
            $lims_sale_data->total_tax += number_format((float)$product_tax, 2, '.', '');
            $lims_sale_data->total_price += number_format((float)$total, 2, '.', '');
        }
        $lims_sale_data->item = $key + 1;
        $lims_sale_data->order_tax = ($lims_sale_data->total_price - $lims_sale_data->order_discount) * ($data['order_tax_rate'] / 100);
        $lims_sale_data->grand_total = ($lims_sale_data->total_price + $lims_sale_data->order_tax + $lims_sale_data->shipping_cost) - $lims_sale_data->order_discount;
        $lims_sale_data->save();
        $message = 'Sale imported successfully';
        if($lims_customer_data->email){
            //collecting male data
            $mail_data['email'] = $lims_customer_data->email;
            $mail_data['reference_no'] = $lims_sale_data->reference_no;
            $mail_data['sale_status'] = $lims_sale_data->sale_status;
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
                    Mail::send( 'mail.sale_details', $mail_data, function( $message ) use ($mail_data)
                    {
                        $message->to( $mail_data['email'] )->subject( 'Sale Details' );
                    });
                }

                catch(\Exception $e){
                    $message = 'Sale imported successfully. Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
                }
            }
        }
        return redirect('sales')->with('message', $message);
    }

    public function createSale($id)
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('sales-edit')){
            $lims_biller_list = Biller::where('is_active', true)->get();
            $lims_customer_list = Customer::where('is_active', true)->get();
            $lims_customer_group_all = CustomerGroup::where('is_active', true)->get();
            $lims_warehouse_list = Warehouse::where('is_active', true)->get();
            $lims_tax_list = Tax::where('is_active', true)->get();
            $lims_sale_data = Sale::find($id);
            $lims_product_sale_data = Product_Sale::where('sale_id', $id)->get();
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

    public function edit($id)
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('sales-edit')){
            $lims_customer_list = Customer::where('is_active', true)->get();
            $lims_warehouse_list = Warehouse::where('is_active', true)->get();
            $lims_biller_list = Biller::where('is_active', true)->get();
            $lims_tax_list = Tax::where('is_active', true)->get();
            $lims_sale_data = Sale::find($id);
            $lims_product_sale_data = Product_Sale::where('sale_id', $id)->get();
            return view('sale.edit',compact('lims_customer_list', 'lims_warehouse_list', 'lims_biller_list', 'lims_tax_list', 'lims_sale_data','lims_product_sale_data'));
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    public function update(Request $request, $id)
    {
        $data = $request->except('document');
        //return dd($data);
        $document = $request->document;
        if ($document) {
            $v = Validator::make(
                [
                    'extension' => strtolower($request->document->getClientOriginalExtension()),
                ],
                [
                    'extension' => 'in:jpg,jpeg,png,gif,pdf,csv,docx,xlsx,txt',
                ]
            );
            if ($v->fails())
                return redirect()->back()->withErrors($v->errors());

            $documentName = $document->getClientOriginalName();
            $document->move('public/sale/documents', $documentName);
            $data['document'] = $documentName;
        }
        $balance = $data['grand_total'] - $data['paid_amount'];
        if($balance < 0 || $balance > 0)
            $data['payment_status'] = 2;
        else
            $data['payment_status'] = 4;
        $lims_sale_data = Sale::find($id);
        $lims_product_sale_data = Product_Sale::where('sale_id', $id)->get();
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

            if( ($lims_sale_data->sale_status == 1) && ($lims_product_data->type == 'combo') ) {
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
            elseif( ($lims_sale_data->sale_status == 1) && ($product_sale_data->sale_unit_id != 0)) {
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
                if($lims_product_warehouse_data) {
                    $lims_product_warehouse_data->qty += $old_product_qty;
                    $lims_product_warehouse_data->save();
                }
                $this->stockDurationSave($lims_product_data->id, $lims_product_data->qty);
                $lims_product_data->save();
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
            if($lims_product_data->type == 'combo' && $data['sale_status'] == 1){
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
                    if($child_warehouse_data->qty < 0) {
                        $child_warehouse_data->qty = 0;
                    }

                    $child_data->save();
                    $child_warehouse_data->save();
                }
            }
            if($sale_unit[$key] != 'n/a') {
                $lims_sale_unit_data = Unit::where('unit_name', $sale_unit[$key])->first();
                $sale_unit_id = $lims_sale_unit_data->id;
                if($data['sale_status'] == 1) {
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
                        if ($lims_product_variant_data->qty < 0) {
                            $lims_product_variant_data->qty = 0;
                        }
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
                        if ($product_batch_data->qty < 0) {
                            $product_batch_data->qty = 0;
                        }
                        $product_batch_data->save();
                    }
                    else {
                        $lims_product_warehouse_data = Product_Warehouse::FindProductWithoutVariant($pro_id, $data['warehouse_id'])
                        ->first();
                    }
                    $lims_product_data->qty -= $new_product_qty;
                    if ($lims_product_data->qty < 0) {
                        $lims_product_data->qty = 0;
                    }
                    if($lims_product_warehouse_data) {
                        $lims_product_warehouse_data->qty -= $new_product_qty;
                        if ($lims_product_warehouse_data->qty < 0) {
                            $lims_product_warehouse_data->qty = 0;
                        }
                        $lims_product_warehouse_data->save();
                    }

                    $this->stockDurationSave($lims_product_data->id, $lims_product_data->qty);
                    $lims_product_data->save();
                }
            }
            else
                $sale_unit_id = 0;

            //collecting mail data
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

            $product_sale['sale_id'] = $id ;
            $product_sale['product_id'] = $pro_id;
            $product_sale['product_batch_id'] = $product_batch_id[$key];
            $product_sale['qty'] = $mail_data['qty'][$key] = $qty[$key];
            $product_sale['sale_unit_id'] = $sale_unit_id;
            $product_sale['net_unit_price'] = $net_unit_price[$key];
            $product_sale['discount'] = $discount[$key];
            $product_sale['tax_rate'] = $tax_rate[$key];
            $product_sale['tax'] = $tax[$key];
            $product_sale['total'] = $mail_data['total'][$key] = $total[$key];

            if($product_sale['variant_id'] && in_array($product_variant_id[$key], $old_product_variant_id)) {
                Product_Sale::where([
                    ['product_id', $pro_id],
                    ['variant_id', $product_sale['variant_id']],
                    ['sale_id', $id]
                ])->update($product_sale);
            }
            elseif( $product_sale['variant_id'] === null && (in_array($pro_id, $old_product_id)) ) {
                Product_Sale::where([
                    ['sale_id', $id],
                    ['product_id', $pro_id]
                ])->update($product_sale);
            }
            else
                Product_Sale::create($product_sale);
        }
        $lims_sale_data->update($data);
        $lims_customer_data = Customer::find($data['customer_id']);
        $message = 'Sale updated successfully';
        //collecting mail data
        if($lims_customer_data->email){
            $mail_data['email'] = $lims_customer_data->email;
            $mail_data['reference_no'] = $lims_sale_data->reference_no;
            $mail_data['sale_status'] = $lims_sale_data->sale_status;
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
                    Mail::send( 'mail.sale_details', $mail_data, function( $message ) use ($mail_data)
                    {
                        $message->to( $mail_data['email'] )->subject( 'Sale Details' );
                    });
                }
                catch(\Exception $e){
                    $message = 'Sale updated successfully. Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
                }
            }
        }

        return redirect('sales')->with('message', $message);
    }

    public function printLastReciept()
    {
        $sale = Sale::where('sale_status', 1)->latest()->first();
        return redirect()->route('sale.invoice', $sale->id);
    }

    public function genInvoice($id)
    {
        $role = Role::find(Auth::user()->role_id);
        $permissions = Role::findByName($role->name)->permissions;

        foreach ($permissions as $permission) {
            $all_permission[] = $permission->name;
        }
        $lims_sale_data = Sale::find($id);
        $lims_product_sale_data = Product_Sale::where('sale_id', $id)->get();
        $lims_biller_data = Biller::find($lims_sale_data->biller_id);
        $lims_warehouse_data = Warehouse::find($lims_sale_data->warehouse_id);
        $lims_customer_data = Customer::find($lims_sale_data->customer_id);
        $lims_payment_data = Payment::where('sale_id', $id)->get();
        $lims_payment_debit_data = Payment::where('debit_sale_id', $id)->get();
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

        return view('sale.invoice', compact('header', 'footer', 'water_mark', 'all_permission', 'lims_account_data_cradit', 'lims_account_data_debit', 'lims_sale_data', 'lims_product_sale_data', 'lims_biller_data', 'lims_warehouse_data', 'lims_customer_data', 'lims_payment_data', 'numberInWords'));
    }

    public function addPayment(Request $request)
    {
        $data = $request->all();
        if (!$data['amount'])
            $data['amount'] = 0.00;

        $lims_sale_data = Sale::find($data['sale_id']);
        $lims_customer_data = Customer::find($lims_sale_data->customer_id);
        $lims_sale_data->paid_amount += $data['amount'];
        $balance = $lims_sale_data->grand_total - $lims_sale_data->paid_amount;
        if ($balance > 0 || $balance < 0)
            $lims_sale_data->payment_status = 2;
        elseif ($balance == 0)
            $lims_sale_data->payment_status = 4;

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
            $lims_payment_data->debit_sale_id = $lims_sale_data->id;
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
        $lims_payment_data->sale_id = $lims_sale_data->id;
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
        return redirect('sales')->with('message', $message);
    }

    public function getPayment($id)
    {
        $lims_payment_list = Payment::where('sale_id', $id)->get();
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
        //return $data;
        $lims_payment_data = Payment::find($data['payment_id']);
        $lims_sale_data = Sale::find($lims_payment_data->sale_id);
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
        elseif ($data['edit_paid_by_id'] == 3){
            $lims_pos_setting_data = PosSetting::latest()->first();
            Stripe::setApiKey($lims_pos_setting_data->stripe_secret_key);
            if($lims_payment_data->paying_method == 'Credit Card'){
                $lims_payment_with_credit_card_data = PaymentWithCreditCard::where('payment_id', $lims_payment_data->id)->first();

                \Stripe\Refund::create(array(
                  "charge" => $lims_payment_with_credit_card_data->charge_id,
                ));

                $customer_id =
                $lims_payment_with_credit_card_data->customer_stripe_id;

                $charge = \Stripe\Charge::create([
                    'amount' => $data['edit_amount'] * 100,
                    'currency' => 'usd',
                    'customer' => $customer_id
                ]);
                $lims_payment_with_credit_card_data->charge_id = $charge->id;
                $lims_payment_with_credit_card_data->save();
            }
            else{
                $token = $data['stripeToken'];
                $amount = $data['edit_amount'];
                $lims_payment_with_credit_card_data = PaymentWithCreditCard::where('customer_id', $lims_sale_data->customer_id)->first();

                if(!$lims_payment_with_credit_card_data) {
                    $customer = \Stripe\Customer::create([
                        'source' => $token
                    ]);

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
                        'customer' => $customer_id
                    ]);
                    $data['customer_stripe_id'] = $customer_id;
                }
                $data['customer_id'] = $lims_sale_data->customer_id;
                $data['charge_id'] = $charge->id;
                PaymentWithCreditCard::create($data);
            }
            $lims_payment_data->paying_method = 'Credit Card';
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
        return redirect('sales')->with('message', $message);
    }

    public function deletePayment(Request $request)
    {
        $lims_payment_data = Payment::find($request['id']);
        $lims_sale_data = Sale::where('id', $lims_payment_data->sale_id)->first();
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
        return redirect('sales')->with('not_permitted', 'Payment deleted successfully');
    }

    public function todaySale()
    {
        $data['total_sale_amount'] = Sale::whereDate('created_at', date("Y-m-d"))->sum('grand_total');
        $data['total_payment'] = Payment::whereDate('created_at', date("Y-m-d"))->sum('amount');
        $data['cash_payment'] = Payment::where([
                                    ['paying_method', 'Cash']
                                ])->whereDate('created_at', date("Y-m-d"))->sum('amount');
        $data['credit_card_payment'] = Payment::where([
                                    ['paying_method', 'Credit Card']
                                ])->whereDate('created_at', date("Y-m-d"))->sum('amount');
        $data['gift_card_payment'] = Payment::where([
                                    ['paying_method', 'Gift Card']
                                ])->whereDate('created_at', date("Y-m-d"))->sum('amount');
        $data['deposit_payment'] = Payment::where([
                                    ['paying_method', 'Deposit']
                                ])->whereDate('created_at', date("Y-m-d"))->sum('amount');
        $data['cheque_payment'] = Payment::where([
                                    ['paying_method', 'Cheque']
                                ])->whereDate('created_at', date("Y-m-d"))->sum('amount');
        $data['paypal_payment'] = Payment::where([
                                    ['paying_method', 'Paypal']
                                ])->whereDate('created_at', date("Y-m-d"))->sum('amount');
        $data['total_sale_return'] = Returns::whereDate('created_at', date("Y-m-d"))->sum('grand_total');
        $data['total_expense'] = Expense::whereDate('created_at', date("Y-m-d"))->sum('amount');
        $data['total_cash'] = $data['total_payment'] - ($data['total_sale_return'] + $data['total_expense']);
        return $data;
    }

    public function todayProfit($warehouse_id)
    {
        if($warehouse_id == 0)
            $product_sale_data = Product_Sale::select(DB::raw('product_id, product_batch_id, sum(qty) as sold_qty, sum(total) as sold_amount'))->whereDate('created_at', date("Y-m-d"))->groupBy('product_id', 'product_batch_id')->get();
        else
            $product_sale_data = Sale::join('product_sales', 'sales.id', '=', 'product_sales.sale_id')
            ->select(DB::raw('product_sales.product_id, product_sales.product_batch_id, sum(product_sales.qty) as sold_qty, sum(product_sales.total) as sold_amount'))
            ->where('sales.warehouse_id', $warehouse_id)->whereDate('sales.created_at', date("Y-m-d"))
            ->groupBy('product_sales.product_id', 'product_sales.product_batch_id')->get();

        $product_revenue = 0;
        $product_cost = 0;
        $profit = 0;
        foreach ($product_sale_data as $key => $product_sale) {
            if($warehouse_id == 0) {
                if($product_sale->product_batch_id)
                    $product_purchase_data = ProductPurchase::where([
                        ['product_id', $product_sale->product_id],
                        ['product_batch_id', $product_sale->product_batch_id]
                    ])->get();
                else
                    $product_purchase_data = ProductPurchase::where('product_id', $product_sale->product_id)->get();
            }
            else {
                if($product_sale->product_batch_id) {
                    $product_purchase_data = Purchase::join('product_purchases', 'purchases.id', '=', 'product_purchases.purchase_id')
                    ->where([
                        ['product_purchases.product_id', $product_sale->product_id],
                        ['product_purchases.product_batch_id', $product_sale->product_batch_id],
                        ['purchases.warehouse_id', $warehouse_id]
                    ])->select('product_purchases.*')->get();
                }
                else
                    $product_purchase_data = Purchase::join('product_purchases', 'purchases.id', '=', 'product_purchases.purchase_id')
                    ->where([
                        ['product_purchases.product_id', $product_sale->product_id],
                        ['purchases.warehouse_id', $warehouse_id]
                    ])->select('product_purchases.*')->get();
            }

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
            $profit += $product_sale->sold_amount - $purchased_amount;
        }

        $data['product_revenue'] = $product_revenue;
        $data['product_cost'] = $product_cost;
        if($warehouse_id == 0)
            $data['expense_amount'] = Expense::whereDate('created_at', date("Y-m-d"))->sum('amount');
        else
            $data['expense_amount'] = Expense::where('warehouse_id', $warehouse_id)->whereDate('created_at', date("Y-m-d"))->sum('amount');

        $data['profit'] = $profit - $data['expense_amount'];
        return $data;
    }

    public function deleteBySelection(Request $request)
    {
        $sale_id = $request['saleIdArray'];
        foreach ($sale_id as $id) {
            $lims_sale_data = Sale::find($id);
            $lims_product_sale_data = Product_Sale::where('sale_id', $id)->get();
            $lims_delivery_data = Delivery::where('sale_id',$id)->first();
            if($lims_sale_data->sale_status == 3)
                $message = 'Draft deleted successfully';
            else
                $message = 'Sale deleted successfully';
            foreach ($lims_product_sale_data as $product_sale) {
                $lims_product_data = Product::find($product_sale->product_id);
                //adjust product quantity
                if( ($lims_sale_data->sale_status == 1) && ($lims_product_data->type == 'combo') ){
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
                elseif(($lims_sale_data->sale_status == 1) && ($product_sale->sale_unit_id != 0)){
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
                        if ($lims_product_batch_data->qty < 0) {
                            $lims_product_batch_data->qty = 0;
                        }
                        $lims_product_batch_data->save();
                    }
                    else {
                        $lims_product_warehouse_data = Product_Warehouse::FindProductWithoutVariant($lims_product_data->id, $lims_sale_data->warehouse_id)->first();
                    }

                    $lims_product_data->qty += $product_sale->qty;
                    if($lims_product_warehouse_data) {
                        $lims_product_warehouse_data->qty += $product_sale->qty;
                        $this->stockDurationSave($lims_product_data->id, $lims_product_data->qty);
                        $lims_product_warehouse_data->save();
                    }
                        $lims_product_data->save();
                }
                $product_sale->delete();
            }
            $lims_payment_data = Payment::where('sale_id', $id)->get();
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
            if($lims_delivery_data)
                $lims_delivery_data->delete();
            if($lims_sale_data->coupon_id) {
                $lims_coupon_data = Coupon::find($lims_sale_data->coupon_id);
                $lims_coupon_data->used -= 1;
                $lims_coupon_data->save();
            }
            $lims_sale_data->delete();
        }
        return 'Sale deleted successfully!';
    }

    public function destroy($id)
    {
        $url = url()->previous();
        $lims_sale_data = Sale::find($id);
        $lims_product_sale_data = Product_Sale::where('sale_id', $id)->get();
        $lims_delivery_data = Delivery::where('sale_id',$id)->first();
        if($lims_sale_data->sale_status == 3)
            $message = 'Draft deleted successfully';
        else
            $message = 'Sale deleted successfully';
        foreach ($lims_product_sale_data as $product_sale) {
            $lims_product_data = Product::find($product_sale->product_id);
            //adjust product quantity
            if( ($lims_sale_data->sale_status == 1) && ($lims_product_data->type == 'combo') ){
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
            elseif(($lims_sale_data->sale_status == 1) && ($product_sale->sale_unit_id != 0)){
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
                    if ($lims_product_batch_data->qty < 0) {
                        $lims_product_batch_data->qty = 0;
                    }
                    $lims_product_batch_data->save();
                }
                else {
                    $lims_product_warehouse_data = Product_Warehouse::FindProductWithoutVariant($lims_product_data->id, $lims_sale_data->warehouse_id)->first();
                }

                $lims_product_data->qty += $product_sale->qty;
                if($lims_product_warehouse_data) {
                    $lims_product_warehouse_data->qty += $product_sale->qty;
                    $lims_product_warehouse_data->save();
                }
                    $lims_product_data->save();
            }
            $product_sale->delete();
        }
        $lims_payment_data = Payment::where('sale_id', $id)->get();
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
        if($lims_delivery_data)
            $lims_delivery_data->delete();
        if($lims_sale_data->coupon_id) {
            $lims_coupon_data = Coupon::find($lims_sale_data->coupon_id);
            $lims_coupon_data->used -= 1;
            $lims_coupon_data->save();
        }
        $lims_sale_data->delete();
        return Redirect::to($url)->with('not_permitted', $message);
    }

    public function stockDurationSave($id, $qty) {
        $stockDuration = StockDuration::where([
            'product_id' => $id,
            'restock' => null
        ])->first();
        if ($qty == 0.0) {
            if(!$stockDuration) {
                StockDuration::create([
                    'product_id' => $id,
                    'out_of_stock' => date('Y-m-d')
                ]);
            }
        } else {
            if ($stockDuration) {
                $stockDuration->update(['restock' => date('Y-m-d')]);
            }
        }
    }

    public function addCategoryIdInSale() {
        $sales = Product_Sale::select('product_id', 'id')->get();
        foreach ($sales as $sale) {
            $product = Product::where('id', $sale->product_id)->select('category_id')->first();
            if(isset($product->category_id)){
                $sale->update(['category_id' => $product->category_id]);
            }
        }
    }
}

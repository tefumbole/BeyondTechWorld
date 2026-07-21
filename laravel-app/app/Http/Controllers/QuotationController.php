<?php

namespace App\Http\Controllers;

use App\GeneralSetting;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PDF;
use Illuminate\Http\Request;
use App\Customer;
use App\CustomerGroup;
use App\Supplier;
use App\Warehouse;
use App\Biller;
use App\Product;
use App\Category;
use App\Unit;
use App\Tax;
use App\Quotation;
use App\User;
use App\Delivery;
use App\PosSetting;
use App\ProductQuotation;
use App\Product_Warehouse;
use App\ProductVariant;
use App\ProductBatch;
use App\Variant;
use DB;
use NumberToWords\NumberToWords;
use Auth;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Mail\UserNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use App\Support\ActiveRecords;
use App\Support\BookingNoteFormatter;
use App\Support\WhatsAppMessage;

class QuotationController extends Controller
{
    protected function activeMasters()
    {
        return [
            'lims_biller_list' => ActiveRecords::of(Biller::class),
            'lims_warehouse_list' => ActiveRecords::of(Warehouse::class),
            'lims_customer_list' => ActiveRecords::of(Customer::class),
            'lims_supplier_list' => ActiveRecords::of(Supplier::class),
            'lims_tax_list' => ActiveRecords::of(Tax::class),
        ];
    }

    public function index(Request $request)
    {
        $role = Role::find(Auth::user()->role_id);
        $setting = GeneralSetting::first();
        $header = $setting->email_header;
        $footer = $setting->email_footer;
        $water_mark = $setting->email_water_mark;
        if($role->hasPermissionTo('quotes-index')){
            $permissions = Role::findByName($role->name)->permissions;
            foreach ($permissions as $permission)
                $all_permission[] = $permission->name;
            if(empty($all_permission))
                $all_permission[] = 'dummy text';

            $tab = $request->get('tab', 'awaiting');
            if (! in_array($tab, ['awaiting', 'approved', 'rejected', 'draft'], true)) {
                $tab = 'awaiting';
            }

            $statusMap = [
                'draft' => Quotation::STATUS_PENDING,
                'awaiting' => Quotation::STATUS_AWAITING,
                'approved' => Quotation::STATUS_APPROVED,
                'rejected' => Quotation::STATUS_REJECTED,
            ];

            $query = Quotation::with('biller', 'customer', 'supplier', 'user')
                ->where('quotation_status', $statusMap[$tab])
                ->orderBy('id', 'desc');

            if(Auth::user()->role_id > 2 && config('staff_access') == 'own') {
                $query->where('user_id', Auth::id());
            }

            $lims_quotation_all = $query->get();

            $baseCounts = Quotation::query();
            if(Auth::user()->role_id > 2 && config('staff_access') == 'own') {
                $baseCounts->where('user_id', Auth::id());
            }
            $tabCounts = [
                'awaiting' => (clone $baseCounts)->where('quotation_status', Quotation::STATUS_AWAITING)->count(),
                'approved' => (clone $baseCounts)->where('quotation_status', Quotation::STATUS_APPROVED)->count(),
                'rejected' => (clone $baseCounts)->where('quotation_status', Quotation::STATUS_REJECTED)->count(),
                'draft' => (clone $baseCounts)->where('quotation_status', Quotation::STATUS_PENDING)->count(),
            ];

            return view('quotation.index', compact(
                'header', 'footer', 'water_mark', 'lims_quotation_all', 'all_permission', 'tab', 'tabCounts'
            ));
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    protected function formExtras()
    {
        $gs = GeneralSetting::first();

        return [
            'lims_customer_group_all' => CustomerGroup::whereRaw('COALESCE(is_active, 0) = 1')->orderBy('name')->get(),
            'lims_category_list' => Category::whereRaw('COALESCE(is_active, 0) = 1')->orderBy('name')->get(),
            'lims_unit_list' => Unit::whereRaw('COALESCE(is_active, 0) = 1')->orderBy('unit_name')->get(),
            'default_category_id' => optional($gs)->category,
            'default_unit_id' => optional($gs)->unit,
            'default_profit' => optional($gs)->profit_percentage ?: 25,
            'cloneQuotation' => null,
            'cloneLines' => [],
        ];
    }

    public function create()
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('quotes-add')){
            extract($this->activeMasters());
            extract($this->formExtras());
            $all_permission = [];
            $permissions = $role->permissions;
            foreach ($permissions as $permission) {
                $all_permission[] = $permission->name;
            }

            return view('quotation.create', compact(
                'all_permission',
                'lims_biller_list',
                'lims_warehouse_list',
                'lims_customer_list',
                'lims_supplier_list',
                'lims_tax_list',
                'lims_customer_group_all',
                'lims_category_list',
                'lims_unit_list',
                'default_category_id',
                'default_unit_id',
                'default_profit',
                'cloneQuotation',
                'cloneLines'
            ));
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    public function cloneQuotation($id)
    {
        $role = Role::find(Auth::user()->role_id);
        if (! $role->hasPermissionTo('quotes-add')) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $sourceQuotation = Quotation::with(['customer', 'biller', 'warehouse', 'supplier'])->findOrFail($id);
        $sourceLines = [];
        $rows = ProductQuotation::where('quotation_id', $id)->get();
        foreach ($rows as $row) {
            $product = Product::find($row->product_id);
            if (! $product) {
                continue;
            }
            $code = $product->code;
            if ($row->variant_id) {
                $variant = ProductVariant::select('item_code')
                    ->FindExactProduct($row->product_id, $row->variant_id)
                    ->first();
                if ($variant) {
                    $code = $variant->item_code;
                }
            }
            $sourceLines[] = [
                'code' => $code,
                'qty' => $row->qty,
                'net_unit_price' => $row->net_unit_price,
                'discount' => $row->discount,
            ];
        }

        extract($this->activeMasters());
        extract($this->formExtras());
        $cloneQuotation = $sourceQuotation;
        $cloneLines = $sourceLines;
        // Keep clone customer/supplier even if inactive
        if ($cloneQuotation->customer_id && ! $lims_customer_list->contains('id', $cloneQuotation->customer_id)) {
            $c = Customer::find($cloneQuotation->customer_id);
            if ($c) {
                $lims_customer_list->push($c);
            }
        }

        $all_permission = [];
        $permissions = $role->permissions;
        foreach ($permissions as $permission) {
            $all_permission[] = $permission->name;
        }

        return view('quotation.create', compact(
            'all_permission',
            'lims_biller_list',
            'lims_warehouse_list',
            'lims_customer_list',
            'lims_supplier_list',
            'lims_tax_list',
            'lims_customer_group_all',
            'lims_category_list',
            'lims_unit_list',
            'default_category_id',
            'default_unit_id',
            'default_profit',
            'cloneQuotation',
            'cloneLines'
        ));
    }

    public function quickStoreProduct(Request $request)
    {
        $role = Role::find(Auth::user()->role_id);
        if (! $role || (! $role->hasPermissionTo('quotes-add') && ! $role->hasPermissionTo('quotes-edit') && ! $role->hasPermissionTo('products-add'))) {
            return response()->json(['success' => false, 'message' => 'Not permitted'], 403);
        }

        $data = $request->validate([
            'product_name' => 'required|string|max:255',
            'product_price' => 'required|numeric|min:0',
            'category' => 'required|integer',
            'unit' => 'required|integer',
            'profit' => 'nullable|numeric|min:0',
            'warehouse_id' => 'nullable|integer',
        ]);

        $profit = isset($data['profit']) ? (float) $data['profit'] : 25;
        $price = (float) $data['product_price'];
        do {
            $code = (string) mt_rand(10000000, 99999999);
        } while (Product::where('code', $code)->exists());

        $payload = [
            'type' => 'standard',
            'barcode_symbology' => 'C128',
            'name' => htmlspecialchars(trim($data['product_name'])),
            'code' => $code,
            'price' => $price,
            'cost' => max(0, $price - ($price / 100 * $profit)),
            'unit_id' => $data['unit'],
            'category_id' => $data['category'],
            'purchase_unit_id' => $data['unit'],
            'sale_unit_id' => $data['unit'],
            'rent_price_per_hour' => 0,
            'rent_price_per_day' => 0,
            'rent_price_per_month' => 0,
            'qty' => 1000000,
            'is_active' => true,
            'featured' => false,
            'image' => 'zummXD2dvAtI.png',
            'tax_method' => 1,
        ];

        $product = Product::create($payload);
        $warehouseId = ! empty($data['warehouse_id'])
            ? (int) $data['warehouse_id']
            : optional(Warehouse::whereRaw('COALESCE(is_active, 0) = 1')->first())->id;
        if ($warehouseId) {
            Product_Warehouse::create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouseId,
                'qty' => 1000000,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Product created',
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'price' => $product->price,
                'label' => $product->name.' ['.$product->code.']',
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->except('document');
        //return dd($data);
        $data['user_id'] = Auth::id();
        $data = $this->applyCcCustomerIds($request, $data);
        if (array_key_exists('note', $data)) {
            $data['note'] = BookingNoteFormatter::forStorage($data['note']);
        }
        $data['show_client_discount'] = $request->input('show_client_discount') == '1';
        $document = $request->document;
        if($document){
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
            $document->move('public/quotation/documents', $documentName);
            $data['document'] = $documentName;
        }
        $data['reference_no'] = 'qr-' . date("Ymd") . '-'. date("his");
        // Only draft (1) or send for approval (2) from create form
        $data['quotation_status'] = (int) ($data['quotation_status'] ?? Quotation::STATUS_AWAITING);
        if (! in_array($data['quotation_status'], [Quotation::STATUS_PENDING, Quotation::STATUS_AWAITING], true)) {
            $data['quotation_status'] = Quotation::STATUS_AWAITING;
        }
        $lims_quotation_data = Quotation::create($data);
        $mail_data = [];
        $lims_customer_data = Customer::find($data['customer_id']);
        if($lims_quotation_data->quotation_status == Quotation::STATUS_AWAITING){
            $lims_quotation_data->ensureApprovalToken();
            //collecting mail data
            $mail_data['email'] = $lims_customer_data ? $lims_customer_data->email : null;
            $mail_data['reference_no'] = $lims_quotation_data->reference_no;
            $mail_data['total_qty'] = $lims_quotation_data->total_qty;
            $mail_data['total_price'] = $lims_quotation_data->total_price;
            $mail_data['order_tax'] = $lims_quotation_data->order_tax;
            $mail_data['order_tax_rate'] = $lims_quotation_data->order_tax_rate;
            $mail_data['order_discount'] = $lims_quotation_data->order_discount;
            $mail_data['shipping_cost'] = $lims_quotation_data->shipping_cost;
            $mail_data['grand_total'] = $lims_quotation_data->grand_total;
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
        $product_quotation = [];

        foreach ($product_id as $i => $id) {
            if($sale_unit[$i] != 'n/a'){
                $lims_sale_unit_data = Unit::where('unit_name', $sale_unit[$i])->first();
                $sale_unit_id = $lims_sale_unit_data->id;
            }
            else
                $sale_unit_id = 0;
            if($sale_unit_id)
                $mail_data['unit'][$i] = $lims_sale_unit_data->unit_code;
            else
                $mail_data['unit'][$i] = '';
            $lims_product_data = Product::find($id);
            if($lims_product_data->is_variant) {
                $lims_product_variant_data = ProductVariant::select('variant_id')->FindExactProductWithCode($id, $product_code[$i])->first();
                $product_quotation['variant_id'] = $lims_product_variant_data->variant_id;
            }
            else
                $product_quotation['variant_id'] = null;
            if($product_quotation['variant_id']){
                $variant_data = Variant::find($product_quotation['variant_id']);
                $mail_data['products'][$i] = $lims_product_data->name . ' [' . $variant_data->name .']';
            }
            else
                $mail_data['products'][$i] = $lims_product_data->name;
            $product_quotation['quotation_id'] = $lims_quotation_data->id ;
            $product_quotation['product_id'] = $id;
            $product_quotation['product_batch_id'] = $product_batch_id[$i];
            $product_quotation['qty'] = $mail_data['qty'][$i] = $qty[$i];
            $product_quotation['sale_unit_id'] = $sale_unit_id;
            $product_quotation['net_unit_price'] = $net_unit_price[$i];
            $product_quotation['discount'] = $discount[$i];
            $product_quotation['tax_rate'] = $tax_rate[$i];
            $product_quotation['tax'] = $tax[$i];
            $product_quotation['total'] = $mail_data['total'][$i] = $total[$i];
            ProductQuotation::create($product_quotation);
        }
        $message = 'Quotation created successfully';
        if($lims_quotation_data->quotation_status == Quotation::STATUS_AWAITING && !empty($mail_data['email'])){
            try{
                Mail::send( 'mail.quotation_details', $mail_data, function( $message ) use ($mail_data)
                {
                    $message->to( $mail_data['email'] )->subject( 'Quotation Details' );
                });
            }
            catch(\Exception $e){
                $message = 'Quotation created successfully. Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
            }
        }
        if($lims_quotation_data->quotation_status == Quotation::STATUS_AWAITING && $lims_customer_data){
            try {
                $biller = optional(Biller::find($request->biller_id))->name;
                $message = $this->sendWhatsappMsg($lims_customer_data, $lims_quotation_data, $mail_data, $biller, $net_unit_price);
                $lims_quotation_data->approval_sent_at = now();
                $lims_quotation_data->save();
            } catch (\Throwable $e) {
                \Log::error('Quotation WhatsApp notify failed: '.$e->getMessage());
                $message = 'Quotation created. Approval link could not be sent automatically — use “Send for approval” from the list.';
            }
            return redirect()->route('quotations.index', ['tab' => 'awaiting'])->with('message', $message);
        }
        if ($lims_quotation_data->quotation_status == Quotation::STATUS_PENDING) {
            return redirect()->route('quotations.index', ['tab' => 'draft'])->with('message', $message);
        }
        return redirect()->route('quotations.index', ['tab' => 'awaiting'])->with('message', $message);
    }

    public function sendMail(Request $request)
    {
        $setting = GeneralSetting::first();
        $data = $request->all();
        $lims_quotation_data = Quotation::find($data['quotation_id']);
        $lims_product_quotation_data = ProductQuotation::where('quotation_id', $data['quotation_id'])->get();
        $lims_customer_data = Customer::find($lims_quotation_data->customer_id);
        if($lims_customer_data->email) {
            //collecting male data
            $mail_data['email'] = $lims_customer_data->email;
            $mail_data['reference_no'] = $lims_quotation_data->reference_no;
            $mail_data['total_qty'] = $lims_quotation_data->total_qty;
            $mail_data['total_price'] = $lims_quotation_data->total_price;
            $mail_data['order_tax'] = $lims_quotation_data->order_tax;
            $mail_data['order_tax_rate'] = $lims_quotation_data->order_tax_rate;
            $mail_data['order_discount'] = $lims_quotation_data->order_discount;
            $mail_data['shipping_cost'] = $lims_quotation_data->shipping_cost;
            $mail_data['grand_total'] = $lims_quotation_data->grand_total;

            $mail_data['header'] = $setting->email_header;
            $mail_data['footer'] = $setting->email_footer;
            $mail_data['water_mark'] = $setting->email_water_mark;

            foreach ($lims_product_quotation_data as $key => $product_quotation_data) {
                $lims_product_data = Product::find($product_quotation_data->product_id);
                if($product_quotation_data->variant_id) {
                    $variant_data = Variant::find($product_quotation_data->variant_id);
                    $mail_data['products'][$key] = $lims_product_data->name . ' [' . $variant_data->name . ']';
                }
                else
                    $mail_data['products'][$key] = $lims_product_data->name;
                if($product_quotation_data->sale_unit_id){
                    $lims_unit_data = Unit::find($product_quotation_data->sale_unit_id);
                    $mail_data['unit'][$key] = $lims_unit_data->unit_code;
                }
                else
                    $mail_data['unit'][$key] = '';

                $mail_data['qty'][$key] = $product_quotation_data->qty;
                $mail_data['total'][$key] = $product_quotation_data->total;
            }
            try{
                Mail::send( 'mail.quotation_details', $mail_data, function( $message ) use ($mail_data)
                {
                    $message->to( $mail_data['email'] )->subject( 'Quotation Details' );
                });
                $message = 'Mail sent successfully';
                $lims_quotation_data->quotation_status = Quotation::STATUS_AWAITING;
                $lims_quotation_data->ensureApprovalToken();
                $lims_quotation_data->save();
            }
            catch(\Exception $e){
                $message = 'Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
            }
        }
        else
            $message = 'Customer doesnt have email!';

        return redirect()->back()->with('message', $message);
    }


    public function sendWhatsapp(Request $request)
    {

        $data = $request->all();
        return $this->genPDFInvoice($data['quotation_id']);
    }


    public function genPDFInvoice($id)
    {
        $role = Role::find(Auth::user()->role_id);
        $permissions = Role::findByName($role->name)->permissions;

        foreach ($permissions as $permission) {
            $all_permission[] = $permission->name;
        }
        $lims_sale_data = Quotation::find($id);
        $lims_product_sale_data = ProductQuotation::where('quotation_id', $id)->get();
        $lims_biller_data = Biller::find($lims_sale_data->biller_id);
        $lims_warehouse_data = Warehouse::find($lims_sale_data->warehouse_id);
        $lims_customer_data = Customer::find($lims_sale_data->customer_id);
        $lims_account_data = null;
        $lims_account_data_debit = null;
        $lims_account_data_cradit = null;


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
            'lims_warehouse_data' => $lims_warehouse_data,
            'lims_customer_data' => $lims_customer_data,
            'numberInWords' => $numberInWords
        ];
//        return view('pdf.quotation_pdf', $data);
        $pdf = PDF::loadView('pdf.quotation_pdf', $data);

        $content = $pdf->download()->getOriginalContent();

        Storage::put('public/quotation/quotation_invoice.pdf',$content);
        $path = storage_path('app/public/quotation/quotation_invoice.pdf');

        $message = 'Quotation notification sent successfully';
        try{
            $this->wpPDFMessage($path, $lims_customer_data, 'quotation_invoice.pdf');
        }
        catch(\Exception $e){
            $message = 'Quotation notification not sent: ' . $e->getMessage();
        }
        return back()->with('message', $message);
    }

    public function sendWhatsappMsg($lims_customer_data, $lims_quotation_data, $mail_data, $biller, $net_unit_price){
        $approvalUrl = $lims_quotation_data->approvalUrl();
        $products = $this->quotationWhatsAppProducts($lims_quotation_data, $mail_data);
        $pricing = $this->quotationWhatsAppPricing($lims_quotation_data, $mail_data);

        $msg = WhatsAppMessage::quotationApprovalRequest(
            $lims_customer_data->name,
            $lims_quotation_data->reference_no,
            number_format((float) $lims_quotation_data->grand_total, 2),
            $approvalUrl,
            array_merge($pricing, ['products' => $products])
        );

        $message = 'Quotation sent for client approval via WhatsApp.';
        try{
            $this->wpMessage($lims_customer_data->phone_number, $msg);
        }
        catch(\Exception $e){
            $message = 'Quotation saved, but WhatsApp approval link could not be sent: '.$e->getMessage();
        }

        // Optional QR attachment — must never fail the quotation save (writable under public/images)
        try {
            $path = public_path('images/quotations/qr');
            if (! File::isDirectory($path)) {
                File::makeDirectory($path, 0775, true);
            }
            $filename = 'qr_code_'.preg_replace('/[^A-Za-z0-9_\-]/', '_', $lims_quotation_data->reference_no).'.png';
            $full = $path.DIRECTORY_SEPARATOR.$filename;
            QrCode::format('png')->size(300)->generate($approvalUrl, $full);
            try {
                $this->wpAttachMessage($full, $lims_customer_data->phone_number, $filename);
            } catch (\Exception $e) {
                // text link already sent
            }
            if (File::exists($full)) {
                File::delete($full);
            }
        } catch (\Throwable $e) {
            \Log::warning('Quotation QR WhatsApp attach skipped: '.$e->getMessage());
        }

        // Creator + CC notifications (copy of status / items)
        $this->notifyQuotationStakeholders($lims_quotation_data, 'sent', $mail_data);

        return $message;
    }

    /**
     * Persist multi-select CC customers (same pattern as rentals).
     */
    protected function applyCcCustomerIds(Request $request, array $data)
    {
        $ccCustomerIds = $request->input('cc_customer', []);
        $data['cc_customer_ids'] = ! empty($ccCustomerIds)
            ? implode(',', array_unique(array_map('intval', (array) $ccCustomerIds)))
            : null;
        unset($data['cc_customer']);

        return $data;
    }

    /**
     * WhatsApp the quotation creator and CC contacts.
     *
     * @param  string  $event  sent|approved|rejected
     */
    public function notifyQuotationStakeholders(Quotation $quotation, $event, array $mail_data = [])
    {
        $customer = Customer::find($quotation->customer_id);
        $customerName = $customer ? $customer->name : 'Client';
        $grandTotal = number_format((float) $quotation->grand_total, 2);
        $comment = (string) ($quotation->client_comment ?? '');
        $lines = $this->quotationWhatsAppProducts($quotation, $mail_data);
        $pricing = $this->quotationWhatsAppPricing($quotation, $mail_data);

        $tab = 'awaiting';
        if ($event === 'approved') {
            $tab = 'approved';
        } elseif ($event === 'rejected') {
            $tab = 'rejected';
        }
        $listUrl = url('quotations?tab='.$tab);
        $approvalUrl = $event === 'sent' ? $quotation->approvalUrl() : null;

        $recipients = [];

        $creator = User::find($quotation->user_id);
        if ($creator && ! empty(trim((string) $creator->phone))) {
            $recipients[] = [
                'phone' => $creator->phone,
                'name' => $creator->name,
            ];
        }

        foreach ($quotation->ccCustomerIdList() as $ccId) {
            $cc = Customer::find($ccId);
            if (! $cc || empty(trim((string) $cc->phone_number))) {
                continue;
            }
            // Don't double-notify the same phone as the primary client on "sent"
            if ($customer && trim((string) $cc->phone_number) === trim((string) $customer->phone_number) && $event === 'sent') {
                continue;
            }
            $recipients[] = [
                'phone' => $cc->phone_number,
                'name' => $cc->name,
            ];
        }

        // De-dupe by phone digits
        $seen = [];
        foreach ($recipients as $recipient) {
            $digits = preg_replace('/\D/', '', (string) $recipient['phone']);
            if ($digits === '' || isset($seen[$digits])) {
                continue;
            }
            $seen[$digits] = true;
            try {
                $msg = WhatsAppMessage::quotationStakeholderNotify(
                    $recipient['name'],
                    $event,
                    $quotation->reference_no,
                    $customerName,
                    $grandTotal,
                    $comment,
                    $lines,
                    $approvalUrl,
                    $listUrl,
                    $pricing
                );
                $this->wpMessage($recipient['phone'], $msg);
            } catch (\Throwable $e) {
                \Log::warning('Quotation stakeholder notify failed: '.$e->getMessage());
            }
        }
    }

    /**
     * Item lines for WhatsApp: name × qty only (never undiscounted line totals).
     */
    protected function quotationWhatsAppProducts(Quotation $quotation, array $mail_data = [])
    {
        $lines = [];
        if (! empty($mail_data['products']) && is_array($mail_data['products'])) {
            foreach ($mail_data['products'] as $key => $product) {
                $lines[] = [
                    'name' => $product,
                    'qty' => $mail_data['qty'][$key] ?? '',
                ];
            }

            return $lines;
        }

        $rows = ProductQuotation::where('quotation_id', $quotation->id)->get();
        foreach ($rows as $row) {
            $product = Product::find($row->product_id);
            $name = $product ? $product->name : 'Item';
            if ($row->variant_id) {
                $variant = Variant::find($row->variant_id);
                if ($variant) {
                    $name .= ' ['.$variant->name.']';
                }
            }
            $lines[] = [
                'name' => $name,
                'qty' => $row->qty,
            ];
        }

        return $lines;
    }

    /**
     * Pricing block for WhatsApp / stakeholder messages.
     */
    protected function quotationWhatsAppPricing(Quotation $quotation, array $mail_data = [])
    {
        $discount = (float) ($mail_data['order_discount'] ?? $quotation->order_discount ?? 0);
        $showDiscount = array_key_exists('show_client_discount', $quotation->getAttributes())
            ? (bool) $quotation->show_client_discount
            : true;

        return [
            'subtotal' => (float) ($mail_data['total_price'] ?? $quotation->total_price ?? 0),
            'order_discount' => $discount,
            'order_tax' => (float) ($mail_data['order_tax'] ?? $quotation->order_tax ?? 0),
            'shipping_cost' => (float) ($mail_data['shipping_cost'] ?? $quotation->shipping_cost ?? 0),
            'show_discount' => $showDiscount && $discount > 0,
        ];
    }

    public function getCustomerGroup($id)
    {
         $lims_customer_data = Customer::find($id);
         $lims_customer_group_data = CustomerGroup::find($lims_customer_data->customer_group_id);
         return $lims_customer_group_data->percentage;
    }

    public function getProduct($id)
    {
        $product_code = [];
        $product_name = [];
        $product_qty = [];
        $product_price = [];
        $product_data = [];
        $batch_no = [];
        $product_batch_id = [];

        //retrieve data of product without variant
        $lims_product_warehouse_data = Product::join('product_warehouse', 'products.id', '=', 'product_warehouse.product_id')
        ->where([
            ['products.is_active', true],
            ['product_warehouse.warehouse_id', $id],
        ])
        ->whereNull('product_warehouse.variant_id')
        ->whereNull('product_warehouse.product_batch_id')
        ->select('product_warehouse.*')
        ->get();

        foreach ($lims_product_warehouse_data as $product_warehouse)
        {
            $product_qty[] = $product_warehouse->qty;
            $product_price[] = $product_warehouse->price;
            $lims_product_data = Product::find($product_warehouse->product_id);
            $product_code[] =  $lims_product_data->code;
            $product_name[] = $lims_product_data->name;
            $product_type[] = $lims_product_data->type;
            $product_id[] = $lims_product_data->id;
            $product_list[] = null;
            $qty_list[] = null;
            $batch_no[] = null;
            $product_batch_id[] = null;
        }

        config()->set('database.connections.mysql.strict', false);
        \DB::reconnect(); //important as the existing connection if any would be in strict mode

        $lims_product_with_batch_warehouse_data = Product::join('product_warehouse', 'products.id', '=', 'product_warehouse.product_id')
        ->where([
            ['products.is_active', true],
            ['product_warehouse.warehouse_id', $id],
        ])
        ->whereNull('product_warehouse.variant_id')
        ->whereNotNull('product_warehouse.product_batch_id')
        ->select('product_warehouse.*')
        ->groupBy('product_warehouse.product_id')
        ->get();

        //now changing back the strict ON
        config()->set('database.connections.mysql.strict', true);
        \DB::reconnect();

        foreach ($lims_product_with_batch_warehouse_data as $product_warehouse)
        {
            $product_qty[] = $product_warehouse->qty;
            $product_price[] = $product_warehouse->price;
            $lims_product_data = Product::find($product_warehouse->product_id);
            $product_code[] =  $lims_product_data->code;
            $product_name[] = $lims_product_data->name;
            $product_type[] = $lims_product_data->type;
            $product_id[] = $lims_product_data->id;
            $product_list[] = null;
            $qty_list[] = null;
            $product_batch_data = ProductBatch::select('id', 'batch_no')->find($product_warehouse->product_batch_id);
            $batch_no[] = $product_batch_data->batch_no;
            $product_batch_id[] = $product_batch_data->id;
        }
        //retrieve data of product with variant
        $lims_product_warehouse_data = Product::join('product_warehouse', 'products.id', '=', 'product_warehouse.product_id')
        ->where([
            ['products.is_active', true],
            ['product_warehouse.warehouse_id', $id],
        ])->whereNotNull('product_warehouse.variant_id')->select('product_warehouse.*')->get();
        foreach ($lims_product_warehouse_data as $product_warehouse)
        {
            $product_qty[] = $product_warehouse->qty;
            $lims_product_data = Product::find($product_warehouse->product_id);
            $lims_product_variant_data = ProductVariant::select('item_code')->FindExactProduct($product_warehouse->product_id, $product_warehouse->variant_id)->first();
            $product_code[] =  $lims_product_variant_data->item_code;
            $product_name[] = $lims_product_data->name;
            $product_type[] = $lims_product_data->type;
            $product_id[] = $lims_product_data->id;
            $product_list[] = null;
            $qty_list[] = null;
            $batch_no[] = null;
            $product_batch_id[] = null;
        }
        //retrieve product data of digital and combo
        $lims_product_data = Product::whereNotIn('type', ['standard'])->where('is_active', true)->get();
        foreach ($lims_product_data as $product)
        {
            $product_qty[] = $product->qty;
            $lims_product_data = $product->id;
            $product_code[] =  $product->code;
            $product_name[] = $product->name;
            $product_type[] = $product->type;
            $product_id[] = $product->id;
            $product_list[] = $product->product_list;
            $qty_list[] = $product->qty_list;
        }
        $product_data = [$product_code, $product_name, $product_qty, $product_type, $product_id, $product_list, $qty_list, $product_price, $batch_no, $product_batch_id];
        return $product_data;
    }

    public function limsProductSearch(Request $request)
    {
        $todayDate = date('Y-m-d');
        $product_code = explode("(", $request['data']);
        $product_code[0] = rtrim($product_code[0], " ");
        $product_variant_id = null;
        $lims_product_data = Product::where('code', $product_code[0])->first();
        if(!$lims_product_data) {
            $lims_product_data = Product::join('product_variants', 'products.id', 'product_variants.product_id')
                ->select('products.*', 'product_variants.id as product_variant_id', 'product_variants.item_code', 'product_variants.additional_price')
                ->where('product_variants.item_code', $product_code[0])
                ->first();
            $product_variant_id = $lims_product_data->product_variant_id;
            $lims_product_data->code = $lims_product_data->item_code;
            $lims_product_data->price += $lims_product_data->additional_price;
        }
        $product[] = $lims_product_data->name;
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
        else {
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

    public function productQuotationData($id)
    {
        $lims_product_quotation_data = ProductQuotation::where('quotation_id', $id)->get();
        foreach ($lims_product_quotation_data as $key => $product_quotation_data) {
            $product = Product::find($product_quotation_data->product_id);
            if($product_quotation_data->variant_id) {
                $lims_product_variant_data = ProductVariant::select('item_code')->FindExactProduct($product_quotation_data->product_id, $product_quotation_data->variant_id)->first();
                $product->code = $lims_product_variant_data->item_code;
            }
            if($product_quotation_data->sale_unit_id){
                $unit_data = Unit::find($product_quotation_data->sale_unit_id);
                $unit = $unit_data->unit_code;
            }
            else
                $unit = '';

            $product_quotation[0][$key] = $product->name . ' [' . $product->code . ']';
            $product_quotation[1][$key] = $product_quotation_data->qty;
            $product_quotation[2][$key] = $unit;
            $product_quotation[3][$key] = $product_quotation_data->tax;
            $product_quotation[4][$key] = $product_quotation_data->tax_rate;
            $product_quotation[5][$key] = $product_quotation_data->discount;
            $product_quotation[6][$key] = $product_quotation_data->total;
            if($product_quotation_data->product_batch_id) {
                $product_batch_data = ProductBatch::select('batch_no')->find($product_quotation_data->product_batch_id);
                $product_quotation[7][$key] = $product_batch_data->batch_no;
            }
            else
                $product_quotation[7][$key] = 'N/A';
        }
        return $product_quotation;
    }

    public function edit($id)
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('quotes-edit')){
            extract($this->activeMasters());
            extract($this->formExtras());
            $lims_quotation_data = Quotation::find($id);
            // Keep currently selected inactive records visible so edit doesn't break
            if ($lims_quotation_data) {
                if ($lims_quotation_data->customer_id && ! $lims_customer_list->contains('id', $lims_quotation_data->customer_id)) {
                    $c = Customer::find($lims_quotation_data->customer_id);
                    if ($c) { $lims_customer_list->push($c); }
                }
            }
            $lims_product_quotation_data = ProductQuotation::where('quotation_id', $id)->get();
            $permissions = $role->permissions;
            $all_permission = [];
            foreach ($permissions as $permission) {
                $all_permission[] = $permission->name;
            }
            return view('quotation.edit', compact(
                'lims_customer_list',
                'lims_warehouse_list',
                'lims_biller_list',
                'lims_tax_list',
                'lims_quotation_data',
                'lims_product_quotation_data',
                'lims_supplier_list',
                'lims_customer_group_all',
                'lims_category_list',
                'lims_unit_list',
                'default_category_id',
                'default_unit_id',
                'default_profit',
                'all_permission'
            ));
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    public function update(Request $request, $id)
    {
        $data = $request->except('document');
        //return dd($data);
        $data = $this->applyCcCustomerIds($request, $data);
        if (array_key_exists('note', $data)) {
            $data['note'] = BookingNoteFormatter::forStorage($data['note']);
        }
        $data['show_client_discount'] = $request->input('show_client_discount') == '1';
        $document = $request->document;
        if($document) {
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
            $document->move('public/quotation/documents', $documentName);
            $data['document'] = $documentName;
        }
        $lims_quotation_data = Quotation::find($id);
        $lims_product_quotation_data = ProductQuotation::where('quotation_id', $id)->get();
        // When re-sending a rejected/draft quotation, reset client response fields
        $newStatus = (int) ($data['quotation_status'] ?? $lims_quotation_data->quotation_status);
        if ($newStatus === Quotation::STATUS_AWAITING) {
            $data['client_comment'] = null;
            $data['client_signed_at'] = null;
            $data['client_signature_path'] = null;
            $data['client_responded_at'] = null;
        }
        //update quotation table
        $lims_quotation_data->update($data);
        $lims_quotation_data = $lims_quotation_data->fresh();
        $mail_data = [];
        $lims_customer_data = Customer::find($data['customer_id']);
        if($lims_quotation_data->quotation_status == Quotation::STATUS_AWAITING){
            $lims_quotation_data->ensureApprovalToken();
            //collecting mail data
            $mail_data['email'] = $lims_customer_data ? $lims_customer_data->email : null;
            $mail_data['reference_no'] = $lims_quotation_data->reference_no;
            $mail_data['total_qty'] = $data['total_qty'];
            $mail_data['total_price'] = $data['total_price'];
            $mail_data['order_tax'] = $data['order_tax'];
            $mail_data['order_tax_rate'] = $data['order_tax_rate'];
            $mail_data['order_discount'] = $data['order_discount'];
            $mail_data['shipping_cost'] = $data['shipping_cost'];
            $mail_data['grand_total'] = $data['grand_total'];
        }
        $product_id = $data['product_id'];
        $product_batch_id = $data['product_batch_id'];
        $product_variant_id = $data['product_variant_id'];
        $qty = $data['qty'];
        $sale_unit = $data['sale_unit'];
        $net_unit_price = $data['net_unit_price'];
        $discount = $data['discount'];
        $tax_rate = $data['tax_rate'];
        $tax = $data['tax'];
        $total = $data['subtotal'];

        foreach ($lims_product_quotation_data as $key => $product_quotation_data) {
            $old_product_id[] = $product_quotation_data->product_id;
            $lims_product_data = Product::select('id')->find($product_quotation_data->product_id);
            if($product_quotation_data->variant_id) {
                $lims_product_variant_data = ProductVariant::select('id')->FindExactProduct($product_quotation_data->product_id, $product_quotation_data->variant_id)->first();
                $old_product_variant_id[] = $lims_product_variant_data->id;
                if(!in_array($lims_product_variant_data->id, $product_variant_id))
                    $product_quotation_data->delete();
            }
            else {
                $old_product_variant_id[] = null;
                if(!in_array($product_quotation_data->product_id, $product_id))
                    $product_quotation_data->delete();
            }
        }

        foreach ($product_id as $i => $pro_id) {
            if($sale_unit[$i] != 'n/a'){
                $lims_sale_unit_data = Unit::where('unit_name', $sale_unit[$i])->first();
                $sale_unit_id = $lims_sale_unit_data->id;
            }
            else
                $sale_unit_id = 0;
            $lims_product_data = Product::select('id', 'name', 'is_variant')->find($pro_id);
            if($sale_unit_id)
                $mail_data['unit'][$i] = $lims_sale_unit_data->unit_code;
            else
                $mail_data['unit'][$i] = '';
            $input['quotation_id'] = $id;
            $input['product_id'] = $pro_id;
            $input['product_batch_id'] = $product_batch_id[$i];
            $input['qty'] = $mail_data['qty'][$i] = $qty[$i];
            $input['sale_unit_id'] = $sale_unit_id;
            $input['net_unit_price'] = $net_unit_price[$i];
            $input['discount'] = $discount[$i];
            $input['tax_rate'] = $tax_rate[$i];
            $input['tax'] = $tax[$i];
            $input['total'] = $mail_data['total'][$i] = $total[$i];
            $flag = 1;
            if($lims_product_data->is_variant) {
                $lims_product_variant_data = ProductVariant::select('variant_id')->where('id', $product_variant_id[$i])->first();
                $input['variant_id'] = $lims_product_variant_data->variant_id;
                if(in_array($product_variant_id[$i], $old_product_variant_id)) {
                    ProductQuotation::where([
                        ['product_id', $pro_id],
                        ['variant_id', $input['variant_id']],
                        ['quotation_id', $id]
                    ])->update($input);
                }
                else {
                    ProductQuotation::create($input);
                }
                $variant_data = Variant::find($input['variant_id']);
                $mail_data['products'][$i] = $lims_product_data->name . ' [' . $variant_data->name . ']';
            }
            else {
                $input['variant_id'] = null;
                if(in_array($pro_id, $old_product_id)) {
                    ProductQuotation::where([
                        ['product_id', $pro_id],
                        ['quotation_id', $id]
                    ])->update($input);
                }
                else {
                    ProductQuotation::create($input);
                }
                $mail_data['products'][$i] = $lims_product_data->name;
            }
        }

        $message = 'Quotation updated successfully';

        if($lims_quotation_data->quotation_status == Quotation::STATUS_AWAITING && !empty($mail_data['email'])){
            try{
                Mail::send( 'mail.quotation_details', $mail_data, function( $message ) use ($mail_data)
                {
                    $message->to( $mail_data['email'] )->subject( 'Quotation Details' );
                });
            }
            catch(\Exception $e){
                $message = 'Quotation updated successfully. Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
            }
        }
        if($lims_quotation_data->quotation_status == Quotation::STATUS_AWAITING && $lims_customer_data){
            try {
                $biller = optional(Biller::find($request->biller_id))->name;
                $message = $this->sendWhatsappMsg($lims_customer_data, $lims_quotation_data, $mail_data, $biller, $net_unit_price);
                $lims_quotation_data->approval_sent_at = now();
                $lims_quotation_data->save();
            } catch (\Throwable $e) {
                \Log::error('Quotation WhatsApp notify (update) failed: '.$e->getMessage());
                $message = 'Quotation updated. Approval link could not be sent automatically — use “Send for approval” from the list.';
            }
            return redirect()->route('quotations.index', ['tab' => 'awaiting'])->with('message', $message);
        }

        $tab = 'awaiting';
        if ((int) $lims_quotation_data->quotation_status === Quotation::STATUS_APPROVED) {
            $tab = 'approved';
        } elseif ((int) $lims_quotation_data->quotation_status === Quotation::STATUS_REJECTED) {
            $tab = 'rejected';
        } elseif ((int) $lims_quotation_data->quotation_status === Quotation::STATUS_PENDING) {
            $tab = 'draft';
        }
        return redirect()->route('quotations.index', ['tab' => $tab])->with('message', $message);
    }

    public function resendApproval($id)
    {
        $quotation = Quotation::findOrFail($id);
        if (! in_array((int) $quotation->quotation_status, [
            Quotation::STATUS_AWAITING,
            Quotation::STATUS_REJECTED,
            Quotation::STATUS_PENDING,
            Quotation::STATUS_APPROVED,
        ], true)) {
            return back()->with('not_permitted', 'This quotation cannot be sent for approval.');
        }

        $quotation->quotation_status = Quotation::STATUS_AWAITING;
        $quotation->client_comment = null;
        $quotation->client_signed_at = null;
        $quotation->client_signature_path = null;
        $quotation->client_responded_at = null;
        $quotation->ensureApprovalToken();
        $quotation->approval_sent_at = now();
        $quotation->save();

        $customer = Customer::find($quotation->customer_id);
        if (! $customer || empty($customer->phone_number)) {
            return redirect()->route('quotations.index', ['tab' => 'awaiting'])
                ->with('not_permitted', 'Customer phone number is required to send the approval link.');
        }

        $mail_data = [
            'grand_total' => $quotation->grand_total,
            'total_price' => $quotation->total_price,
            'order_discount' => $quotation->order_discount,
            'order_tax' => $quotation->order_tax,
            'shipping_cost' => $quotation->shipping_cost,
            'products' => [],
            'qty' => [],
            'total' => [],
        ];
        try {
            $message = $this->sendWhatsappMsg($customer, $quotation, $mail_data, optional($quotation->biller)->name, []);
        } catch (\Throwable $e) {
            \Log::error('Quotation resend approval failed: '.$e->getMessage());
            $message = 'Quotation is awaiting approval, but WhatsApp could not be sent: '.$e->getMessage();
        }

        return redirect()->route('quotations.index', ['tab' => 'awaiting'])->with('message', $message);
    }

    public function createSale($id)
    {
        $lims_quotation_data = Quotation::find($id);
        if (! $lims_quotation_data || (int) $lims_quotation_data->quotation_status !== Quotation::STATUS_APPROVED) {
            return redirect()->route('quotations.index', ['tab' => 'approved'])
                ->with('not_permitted', 'Create Sale is only available for client-approved quotations.');
        }
        extract($this->activeMasters());
        $lims_product_quotation_data = ProductQuotation::where('quotation_id', $id)->get();
        $lims_pos_setting_data = PosSetting::latest()->first();
        return view('quotation.create_sale',compact('lims_customer_list', 'lims_warehouse_list', 'lims_biller_list', 'lims_tax_list', 'lims_quotation_data','lims_product_quotation_data', 'lims_pos_setting_data'));
    }

    public function createPurchase($id)
    {
        extract($this->activeMasters());
        $lims_tax_list = ActiveRecords::of(Tax::class);
        $lims_quotation_data = Quotation::find($id);
        $lims_product_quotation_data = ProductQuotation::where('quotation_id', $id)->get();
        $lims_product_list_without_variant = $this->productWithoutVariant();
        $lims_product_list_with_variant = $this->productWithVariant();

        return view('quotation.create_purchase',compact('lims_product_list_without_variant', 'lims_product_list_with_variant', 'lims_supplier_list', 'lims_warehouse_list', 'lims_tax_list', 'lims_quotation_data','lims_product_quotation_data'));
    }

    public function productWithoutVariant()
    {
        return Product::ActiveStandard()->select('id', 'name', 'code')
                ->whereNull('is_variant')->get();
    }

    public function productWithVariant()
    {
        return Product::join('product_variants', 'products.id', 'product_variants.product_id')
                ->ActiveStandard()
                ->whereNotNull('is_variant')
                ->select('products.id', 'products.name', 'product_variants.item_code')
                ->orderBy('position')->get();
    }

    public function deleteBySelection(Request $request)
    {
        $quotation_id = $request['quotationIdArray'];
        foreach ($quotation_id as $id) {
            $lims_quotation_data = Quotation::find($id);
            $lims_product_quotation_data = ProductQuotation::where('quotation_id', $id)->get();
            foreach ($lims_product_quotation_data as $product_quotation_data) {
                $product_quotation_data->delete();
            }
            $lims_quotation_data->delete();
        }
        return 'Quotation deleted successfully!';
    }

    public function destroy($id)
    {
        $lims_quotation_data = Quotation::find($id);
        $lims_product_quotation_data = ProductQuotation::where('quotation_id', $id)->get();
        foreach ($lims_product_quotation_data as $product_quotation_data) {
            $product_quotation_data->delete();
        }
        $lims_quotation_data->delete();
        return redirect('quotations')->with('not_permitted', 'Quotation deleted successfully');
    }
}

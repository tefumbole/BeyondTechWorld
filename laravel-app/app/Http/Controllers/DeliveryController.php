<?php

namespace App\Http\Controllers;

use App\ProductBatch;
use Illuminate\Http\Request;
use App\Customer;
use App\Sale;
use App\Product_Sale;
use App\Product;
use App\ProductVariant;
use App\Delivery;
use App\Support\DeliveryVerifyQr;
use App\Support\WhatsAppMessage;
use Spatie\Permission\Models\Role;
use DB;
use Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PDF;

class DeliveryController extends Controller
{
    public function index(Request $request)
    {
        $role = Role::find(Auth::user()->role_id);
        if (! $role->hasPermissionTo('delivery')) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $filter = $request->get('filter', 'all'); // all|pending|signed
        $query = Delivery::with(['sale.customer', 'user'])->orderBy('id', 'desc');
        if (Auth::user()->role_id > 2 && config('staff_access') == 'own') {
            $query->where('user_id', Auth::id());
        }
        if ($filter === 'pending') {
            $query->whereNull('client_signed_at');
        } elseif ($filter === 'signed') {
            $query->whereNotNull('client_signed_at');
        }

        $lims_delivery_all = $query->get();
        $lims_customer_list = Customer::where('is_active', true)->orderBy('name')->get(['id', 'name', 'phone_number']);

        return view('delivery.index', compact('lims_delivery_all', 'filter', 'lims_customer_list'));
    }

    public function create($id)
    {
        $lims_delivery_data = Delivery::where('sale_id', $id)->first();
        if ($lims_delivery_data) {
            $customer_sale = DB::table('sales')->join('customers', 'sales.customer_id', '=', 'customers.id')->where('sales.id', $id)->select('sales.reference_no', 'customers.name', 'customers.id as customer_id')->get();

            $delivery_data[] = $lims_delivery_data->reference_no;
            $delivery_data[] = $customer_sale[0]->reference_no;
            $delivery_data[] = $lims_delivery_data->status;
            $delivery_data[] = $lims_delivery_data->delivered_by;
            $delivery_data[] = $lims_delivery_data->recieved_by;
            $delivery_data[] = $customer_sale[0]->name;
            $delivery_data[] = $lims_delivery_data->address;
            $delivery_data[] = $lims_delivery_data->note;
            $delivery_data[] = $lims_delivery_data->delivered_by_customer_id;
            $delivery_data[] = $lims_delivery_data->received_by_customer_id ?: $customer_sale[0]->customer_id;
        } else {
            $customer_sale = DB::table('sales')->join('customers', 'sales.customer_id', '=', 'customers.id')->where('sales.id', $id)->select('sales.reference_no', 'customers.name', 'customers.address', 'customers.city', 'customers.country', 'customers.id as customer_id')->get();

            $delivery_data[] = 'dr-'.date('Ymd').'-'.date('his');
            $delivery_data[] = $customer_sale[0]->reference_no;
            $delivery_data[] = '';
            $delivery_data[] = Auth::user()->name;
            $delivery_data[] = $customer_sale[0]->name;
            $delivery_data[] = $customer_sale[0]->name;
            $delivery_data[] = trim($customer_sale[0]->address.' '.$customer_sale[0]->city.' '.$customer_sale[0]->country);
            $delivery_data[] = '';
            $delivery_data[] = null;
            $delivery_data[] = $customer_sale[0]->customer_id;
        }

        return $delivery_data;
    }

    public function store(Request $request)
    {
        $data = $request->except('file');
        $delivery = Delivery::firstOrNew(['reference_no' => $data['reference_no']]);
        $document = $request->file;
        if ($document) {
            $ext = pathinfo($document->getClientOriginalName(), PATHINFO_EXTENSION);
            $documentName = $data['reference_no'].'.'.$ext;
            $document->move('public/documents/delivery', $documentName);
            $delivery->file = $documentName;
        }

        $deliveredCustomer = ! empty($data['delivered_by_customer_id']) ? Customer::find($data['delivered_by_customer_id']) : null;
        $receivedCustomer = ! empty($data['received_by_customer_id']) ? Customer::find($data['received_by_customer_id']) : null;

        $delivery->sale_id = $data['sale_id'];
        $delivery->user_id = Auth::id();
        $delivery->address = $data['address'];
        $delivery->delivered_by_customer_id = $deliveredCustomer ? $deliveredCustomer->id : null;
        $delivery->received_by_customer_id = $receivedCustomer ? $receivedCustomer->id : null;
        $delivery->delivered_by = $deliveredCustomer ? $deliveredCustomer->name : ($data['delivered_by'] ?? '');
        $delivery->recieved_by = $receivedCustomer ? $receivedCustomer->name : ($data['recieved_by'] ?? '');
        $delivery->status = $data['status'];
        $delivery->note = $data['note'];
        $delivery->save();

        $lims_sale_data = Sale::find($data['sale_id']);
        $lims_customer_data = Customer::find($lims_sale_data->customer_id);
        $message = 'Delivery created successfully';

        // Always issue a fresh signature link for the receiver
        $delivery->rotateSignatureToken();
        $signTarget = $receivedCustomer ?: $lims_customer_data;
        try {
            $this->sendSignatureWhatsApp($delivery->fresh(), $signTarget, $lims_sale_data);
            $delivery->signature_sent_at = now();
            $delivery->save();
            $message = 'Delivery created and signature link sent via WhatsApp.';
        } catch (\Throwable $e) {
            \Log::warning('Delivery signature WhatsApp failed: '.$e->getMessage());
            $message = 'Delivery created, but WhatsApp signature link could not be sent: '.$e->getMessage();
        }

        if ($lims_customer_data->email && $data['status'] != 1) {
            $mail_data['email'] = $lims_customer_data->email;
            $mail_data['customer'] = $lims_customer_data->name;
            $mail_data['sale_reference'] = $lims_sale_data->reference_no;
            $mail_data['delivery_reference'] = $delivery->reference_no;
            $mail_data['status'] = $data['status'];
            $mail_data['address'] = $data['address'];
            $mail_data['delivered_by'] = $delivery->delivered_by;
            try {
                Mail::send('mail.delivery_details', $mail_data, function ($message) use ($mail_data) {
                    $message->to($mail_data['email'])->subject('Delivery Details');
                });
            } catch (\Exception $e) {
                // keep delivery message
            }
        }

        return redirect()->route('delivery.index', ['filter' => 'pending'])->with('message', $message);
    }

    public function resendSignature($id)
    {
        $delivery = Delivery::with('sale.customer')->findOrFail($id);
        if ($delivery->isSigned()) {
            return back()->with('not_permitted', 'This delivery is already signed. Send a new delivery if needed.');
        }

        $delivery->rotateSignatureToken();
        $sale = $delivery->sale;
        $signTarget = $delivery->received_by_customer_id
            ? Customer::find($delivery->received_by_customer_id)
            : optional($sale)->customer;

        try {
            $this->sendSignatureWhatsApp($delivery, $signTarget, $sale);
            $delivery->signature_sent_at = now();
            $delivery->save();
            $message = 'Signature link resent via WhatsApp.';
        } catch (\Throwable $e) {
            $message = 'Could not send WhatsApp: '.$e->getMessage();
        }

        return back()->with('message', $message);
    }

    public function sendWhatsapp(Request $request)
    {
        $delivery = Delivery::with(['sale.customer', 'user'])->findOrFail($request->delivery_id);
        if (! $delivery->isSigned()) {
            return back()->with('not_permitted', 'Only signed deliveries can be sent as the final document.');
        }

        $sale = $delivery->sale;
        $customer = optional($sale)->customer;
        if (! $customer || empty(trim((string) $customer->phone_number))) {
            return back()->with('not_permitted', 'Customer phone number is required.');
        }

        try {
            $path = $this->buildDeliveryPdfPath($delivery);
            $caption = WhatsAppMessage::deliverySignedDocument(
                $customer->name,
                $delivery->reference_no,
                optional($sale)->reference_no
            );
            $this->wpMessage($customer->phone_number, $caption);
            $this->wpPDFMessage($path, $customer, $delivery->reference_no.'.pdf');
            $message = 'Signed delivery sent via WhatsApp.';
        } catch (\Throwable $e) {
            $message = 'WhatsApp send failed: '.$e->getMessage();
        }

        return back()->with('message', $message);
    }

    public function productDeliveryData($id)
    {
        $lims_delivery_data = Delivery::find($id);
        $lims_product_sale_data = Product_Sale::where('sale_id', $lims_delivery_data->sale->id)->get();
        $product_sale = [];

        foreach ($lims_product_sale_data as $key => $product_sale_data) {
            $product = Product::select('name', 'code', 'location')->find($product_sale_data->product_id);
            if ($product_sale_data->variant_id) {
                $lims_product_variant_data = ProductVariant::select('item_code')->FindExactProduct($product_sale_data->product_id, $product_sale_data->variant_id)->first();
                $product->code = $lims_product_variant_data->item_code;
            }

            $product_sale[0][$key] = $product->code;
            $product_sale[1][$key] = $product->name;
            $product_sale[3][$key] = [$product->location];
            if ($product_sale_data->multi_product_batch_id == null && $product_sale_data->product_batch_id) {
                $batch = ProductBatch::where('id', $product_sale_data->product_batch_id)->select('batch_no', 'expired_date')->first();
                $product_sale[4][$key] = [$batch->batch_no];
                $product_sale[5][$key] = [$batch->expired_date];
                $product_sale[3][$key] = [$product->location];
            }

            if ($product_sale_data->multi_product_batch_id != null) {
                $batch_id = json_decode($product_sale_data->multi_product_batch_id);
                $batch_qty = json_decode($product_sale_data->multi_product_batch_qty);
                $product_sale_qty = [];
                $product_batch_no = [];
                $product_batch_expiry = [];
                $product_batch_location = [];
                foreach ($batch_id as $k => $bid) {
                    $batch = ProductBatch::where('id', $bid)->select('batch_no', 'expired_date', 'location')->first();
                    $product_batch_no[] = $batch->batch_no;
                    $product_batch_expiry[] = $batch->expired_date;
                    $product_sale_qty[] = $batch_qty[$k];
                    $product_batch_location[] = $batch->location;
                }
                $product_sale[2][$key] = $product_sale_qty;
                $product_sale[3][$key] = $product_batch_location;
                $product_sale[4][$key] = $product_batch_no;
                $product_sale[5][$key] = $product_batch_expiry;
            } else {
                $product_sale[2][$key] = [$product_sale_data->qty];
            }
        }

        return $product_sale;
    }

    public function sendMail(Request $request)
    {
        $data = $request->all();
        $lims_delivery_data = Delivery::find($data['delivery_id']);
        $lims_sale_data = Sale::find($lims_delivery_data->sale->id);
        $lims_product_sale_data = Product_Sale::where('sale_id', $lims_delivery_data->sale->id)->get();
        $lims_customer_data = Customer::find($lims_sale_data->customer_id);
        if ($lims_customer_data->email) {
            $mail_data['email'] = $lims_customer_data->email;
            $mail_data['date'] = date(config('date_format'), strtotime($lims_delivery_data->created_at->toDateString()));
            $mail_data['delivery_reference_no'] = $lims_delivery_data->reference_no;
            $mail_data['sale_reference_no'] = $lims_sale_data->reference_no;
            $mail_data['status'] = $lims_delivery_data->status;
            $mail_data['customer_name'] = $lims_customer_data->name;
            $mail_data['address'] = $lims_customer_data->address.', '.$lims_customer_data->city;
            $mail_data['phone_number'] = $lims_customer_data->phone_number;
            $mail_data['note'] = $lims_delivery_data->note;
            $mail_data['prepared_by'] = $lims_delivery_data->user->name;
            $mail_data['delivered_by'] = $lims_delivery_data->delivered_by ?: 'N/A';
            $mail_data['recieved_by'] = $lims_delivery_data->recieved_by ?: 'N/A';

            foreach ($lims_product_sale_data as $key => $product_sale_data) {
                $lims_product_data = Product::select('code', 'name')->find($product_sale_data->product_id);
                $mail_data['codes'][$key] = $lims_product_data->code;
                $mail_data['name'][$key] = $lims_product_data->name;
                if ($product_sale_data->variant_id) {
                    $lims_product_variant_data = ProductVariant::select('item_code')->FindExactProduct($product_sale_data->product_id, $product_sale_data->variant_id)->first();
                    $mail_data['codes'][$key] = $lims_product_variant_data->item_code;
                }
                $mail_data['qty'][$key] = $product_sale_data->qty;
            }

            try {
                $pdfBinary = null;
                try {
                    $pdfBinary = $this->buildDeliveryPdfBinary($lims_delivery_data);
                } catch (\Throwable $e) {
                }
                Mail::send('mail.delivery_challan', $mail_data, function ($message) use ($mail_data, $pdfBinary, $lims_delivery_data) {
                    $message->to($mail_data['email'])->subject('Delivery Note '.$mail_data['delivery_reference_no']);
                    if ($pdfBinary) {
                        $message->attachData($pdfBinary, $lims_delivery_data->reference_no.'.pdf', ['mime' => 'application/pdf']);
                    }
                });
                $message = 'Mail sent successfully';
            } catch (\Exception $e) {
                $message = 'Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
            }
        } else {
            $message = 'Customer does not have email!';
        }

        return redirect()->back()->with('message', $message);
    }

    public function edit($id)
    {
        $lims_delivery_data = Delivery::find($id);
        $customer_sale = DB::table('sales')->join('customers', 'sales.customer_id', '=', 'customers.id')->where('sales.id', $lims_delivery_data->sale_id)->select('sales.reference_no', 'customers.name', 'customers.id as customer_id')->get();

        $delivery_data[] = $lims_delivery_data->reference_no;
        $delivery_data[] = $customer_sale[0]->reference_no;
        $delivery_data[] = $lims_delivery_data->status;
        $delivery_data[] = $lims_delivery_data->delivered_by;
        $delivery_data[] = $lims_delivery_data->recieved_by;
        $delivery_data[] = $customer_sale[0]->name;
        $delivery_data[] = $lims_delivery_data->address;
        $delivery_data[] = $lims_delivery_data->note;
        $delivery_data[] = $lims_delivery_data->delivered_by_customer_id;
        $delivery_data[] = $lims_delivery_data->received_by_customer_id ?: $customer_sale[0]->customer_id;

        return $delivery_data;
    }

    public function update(Request $request)
    {
        $input = $request->except('file');
        $lims_delivery_data = Delivery::find($input['delivery_id']);
        $document = $request->file;
        if ($document) {
            $ext = pathinfo($document->getClientOriginalName(), PATHINFO_EXTENSION);
            $documentName = $input['reference_no'].'.'.$ext;
            $document->move('public/documents/delivery', $documentName);
            $input['file'] = $documentName;
        }

        $deliveredCustomer = ! empty($input['delivered_by_customer_id']) ? Customer::find($input['delivered_by_customer_id']) : null;
        $receivedCustomer = ! empty($input['received_by_customer_id']) ? Customer::find($input['received_by_customer_id']) : null;
        $input['delivered_by_customer_id'] = $deliveredCustomer ? $deliveredCustomer->id : null;
        $input['received_by_customer_id'] = $receivedCustomer ? $receivedCustomer->id : null;
        $input['delivered_by'] = $deliveredCustomer ? $deliveredCustomer->name : ($input['delivered_by'] ?? $lims_delivery_data->delivered_by);
        $input['recieved_by'] = $receivedCustomer ? $receivedCustomer->name : ($input['recieved_by'] ?? $lims_delivery_data->recieved_by);

        $lims_delivery_data->update($input);
        $lims_sale_data = Sale::find($lims_delivery_data->sale_id);
        $lims_customer_data = Customer::find($lims_sale_data->customer_id);
        $message = 'Delivery updated successfully';

        if (! $lims_delivery_data->isSigned() && $request->boolean('resend_signature')) {
            $lims_delivery_data->rotateSignatureToken();
            $signTarget = $receivedCustomer ?: $lims_customer_data;
            try {
                $this->sendSignatureWhatsApp($lims_delivery_data->fresh(), $signTarget, $lims_sale_data);
                $lims_delivery_data->signature_sent_at = now();
                $lims_delivery_data->save();
                $message = 'Delivery updated and signature link resent.';
            } catch (\Throwable $e) {
                $message = 'Delivery updated, but WhatsApp failed: '.$e->getMessage();
            }
        }

        return redirect()->route('delivery.index')->with('message', $message);
    }

    public function deleteBySelection(Request $request)
    {
        $delivery_id = $request['deliveryIdArray'];
        foreach ($delivery_id as $id) {
            $lims_delivery_data = Delivery::find($id);
            $lims_delivery_data->delete();
        }

        return 'Delivery deleted successfully';
    }

    public function delete($id)
    {
        $lims_delivery_data = Delivery::find($id);
        $lims_delivery_data->delete();

        return redirect('delivery')->with('not_permitted', 'Delivery deleted successfully');
    }

    protected function sendSignatureWhatsApp(Delivery $delivery, $customer, $sale)
    {
        if (! $customer || empty(trim((string) $customer->phone_number))) {
            throw new \RuntimeException('Receiver phone number is required to send the signature link.');
        }

        $msg = WhatsAppMessage::deliverySignatureRequest(
            $customer->name,
            $delivery->reference_no,
            optional($sale)->reference_no,
            $delivery->signatureUrl()
        );
        $this->wpMessage($customer->phone_number, $msg);
    }

    protected function deliveryLines(Delivery $delivery)
    {
        $rows = Product_Sale::where('sale_id', $delivery->sale_id)->get();
        $lines = [];
        foreach ($rows as $row) {
            $product = Product::find($row->product_id);
            $name = $product ? $product->name : 'Product';
            $code = $product ? $product->code : '';
            if ($row->variant_id) {
                $variant = ProductVariant::select('item_code')->FindExactProduct($row->product_id, $row->variant_id)->first();
                if ($variant) {
                    $code = $variant->item_code;
                }
            }
            $lines[] = ['code' => $code, 'name' => $name, 'qty' => $row->qty + 0];
        }

        return $lines;
    }

    protected function buildDeliveryPdfBinary(Delivery $delivery)
    {
        $delivery->loadMissing(['sale.customer', 'user']);
        $pdf = PDF::loadView('pdf.delivery_pdf', [
            'delivery' => $delivery,
            'lines' => $this->deliveryLines($delivery),
            'verifyUrl' => DeliveryVerifyQr::scanUrl($delivery),
        ])->setPaper('A4', 'portrait');

        return $pdf->output();
    }

    protected function buildDeliveryPdfPath(Delivery $delivery)
    {
        $content = $this->buildDeliveryPdfBinary($delivery);
        Storage::put('public/delivery/delivery_'.$delivery->id.'.pdf', $content);

        return storage_path('app/public/delivery/delivery_'.$delivery->id.'.pdf');
    }
}

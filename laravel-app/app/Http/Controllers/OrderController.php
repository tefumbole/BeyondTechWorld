<?php

namespace App\Http\Controllers;

use App\Booking;
use App\BookingProduct;
use App\Customer;
use App\GeneralSetting;
use App\Order;
use App\OrderProduct;
use App\paymentRequest;
use App\Product;
use App\Product_Warehouse;
use App\User;
use Doctrine\DBAL\Schema\AbstractAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use NumberToWords\NumberToWords;
use Spatie\Permission\Models\Role;
use PDF;
use Twilio\TwiML\Voice\Pay;

class OrderController extends Controller
{

    public function index() {
        $vendor_id = null;
        if (Auth::user()->role_id == 12) {
            $vendor_id =  Auth::user()->id;
        }
        if ($vendor_id == null) {
            $data = Order::where('is_donation', 0)->where('is_service', 0)->orderByDesc('id')->get();
        } else {
            $data = Order::where('is_donation', 0)->where('is_service', 0)->where('vendor_id', $vendor_id)->orderByDesc('id')->get();
        }
        return view('order.index', compact('data'));
    }

    public function shopOrders($id) {
        $vendor_id =  $id;
        $data = Order::where('is_donation', 0)->where('is_service', 0)->where('vendor_id', $vendor_id)->orderByDesc('id')->get();
        return view('order.index', compact('data'));
    }

    public function paymentList() {
        $vendor_id = null;
        if (Auth::user()->role_id == 12) {
            $vendor_id =  Auth::user()->id;
        }
        if ($vendor_id == null) {
            $data = PaymentRequest::orderByDesc('id')->get();
        } else {
            $data = PaymentRequest::where('vendor_id', $vendor_id)->orderByDesc('id')->get();
        }
        return view('payment.index', compact('data'));
    }

    public function paymentListShop($id) {
        $vendor_id = $id;
        $data = PaymentRequest::where('vendor_id', $vendor_id)->orderByDesc('id')->get();
        return view('payment.index', compact('data'));
    }

    public function paymentDelete($id) {
        PaymentRequest::where('id', $id)->delete();
        return back()->with('not_permitted','Payment deleted successfully');
    }

    public function paymentEdit($id) {

        $data = PaymentRequest::find($id);
        return view('payment.edit', compact('data'));
    }

    public function paymentUpdate(Request $request, $id)
    {
        $data = PaymentRequest::find($id)->update(['status' => $request->status]);
        return back()->with('message','Payment Update successfully');
    }

    public function withdraw($id) {
        $data = Order::find($id);
        $vendor = User::where('id', $data->vendor_id)->first();
        $commission = $vendor->commission/100*$data->grand_total;
        $total = $data->grand_total;
        if($commission) {
            $total = $data->grand_total - $commission;
        }
        $payment = PaymentRequest::create([
            'vendor_id' => $data->vendor_id,
            'order_id' => $data->id,
            'amount' => $total,
            'status' => 0,
        ]);

        if ($payment) {
            $data->update(['payment_request' => 1]);
        }
        return back()->with('message','Payment Request is created successfully');
    }

    public function donationList() {
        $vendor_id = null;
        if (Auth::user()->role_id == 12) {
            $vendor_id =  Auth::user()->id;
        }
        if ($vendor_id == null) {
            $data = Order::where('is_donation', 1)->orderByDesc('id')->get();
        } else {
            $data = Order::where('is_donation', 1)->where('vendor_id', $vendor_id)->orderByDesc('id')->get();
        }
        return view('order.donation-index', compact('data'));
    }

    public function serviceList() {
        $data = Order::where('is_service', 1)->orderByDesc('id')->get();
        return view('order.service-index', compact('data'));
    }

    public function show($id) {
        $data = Order::find($id);
        return view('order.show', compact('data'));
    }

    public function donationShow($id) {
        $data = Order::find($id);
        return view('order.donation-show', compact('data'));
    }

    public function serviceShow($id) {
        $data = Order::find($id);
        return view('order.service-show', compact('data'));
    }

    public function serviceDelete($id) {
        Order::where('id', $id)->delete();
        OrderProduct::where('order_id', $id)->delete();
        return back()->with('not_permitted','Service Order deleted successfully');
    }

    public function donationDelete($id) {
        Order::where('id', $id)->delete();
        OrderProduct::where('order_id', $id)->delete();
        return back()->with('not_permitted','Donation deleted successfully');
    }

    public function edit($id)
    {
        $data = Order::find($id);
        return view('order.edit', compact('data'));
    }

    public function update(Request $request, $id)
    {
        $data = $request->except('id');
        if (isset($data['is_approve'])) {
            $data['is_approve'] = 1;
        } else {
            $data['is_approve'] = 0;
        }
        $order = Order::with('orderProducts')->find($id);

        if($order->order_status != 1 && $data['order_status'] == 1) {
            foreach ($order->orderProducts as $orderProducts) {
                $lims_product_data = Product::where('id', $orderProducts->product_id)->first();
                if($lims_product_data->type == 'standard') {
                    $warehouse_data = Product_Warehouse::where([
                        ['product_id', $lims_product_data->id],
                        ['warehouse_id', 1],
                    ])->first();

                    if($warehouse_data) {
                        $warehouse_data->qty -= $orderProducts->quantity;
                        $warehouse_data->save();
                    }

                    $lims_product_data->qty -= $orderProducts->quantity;
                    $lims_product_data->save();

                }
            }
        }

        if($order->order_status == 1 && $data['order_status'] != 1) {
            foreach ($order->orderProducts as $orderProducts) {
                $lims_product_data = Product::where('id', $orderProducts->product_id)->first();
                if($lims_product_data->type == 'standard') {
                    $warehouse_data = Product_Warehouse::where([
                        ['product_id', $lims_product_data->id],
                        ['warehouse_id', 1],
                    ])->first();

                    if($warehouse_data) {
                        $warehouse_data->qty += $orderProducts->quantity;
                        $warehouse_data->save();
                    }

                    $lims_product_data->qty += $orderProducts->quantity;
                    $lims_product_data->save();

                }
            }
        }

        $msg = '*Dear :* '. $order->name .' \n\n';

        if ($data['order_status'] == 0) {
            $status = 'Pending';
            $msg .= '*Your Order Status is updated to :* '. $status . '\n\n';
        } elseif ($data['order_status'] == 1) {
            $status = 'Completed';
            $msg .= '*Your Order Status is updated to :* '. $status . '\n\n';
            $msg .= '*Note :* If you have received your order please go to in my orders and mark order received. \n\n';
            $msg .= '*Thank you*';
        } elseif ($data['order_status'] == 2) {
            $status = 'Rejected';
            $msg .= '*Your Order Status is updated to :* '. $status . '\n\n';
        } else {
            $status = 'Ready For Delivery';
            $msg .= '*Your Order Status is updated to :* '. $status . '\n\n';
            $msg .= '*Order Receiving Date is :* '. $data['delivery_date'] . '\n\n';
        }

        $general_setting = GeneralSetting::first();

        $msg .= '*Order Details:*\n';
        $msg .= 'Order Number: '.$order->id.'\n';
        $msg .=  'Order Date: '.$order->created_at.'\n\n';

        $msg .= '*Product Detail:*\n';
        foreach ($order->orderProducts as $key => $product) {
            $msg .= $key+1 .') ['. $product->product->name . '] [' . $product->quantity . '] x [ '. number_format($product->price, 2) .'] = ['. number_format($product->sub_total, 2) .']\n';
        }

        $msg .= 'Total Amount: ' . number_format($order->grand_total, 2) . '\n\n';

        $msg .= '*Payment Information:*\n';
        $msg .= 'Payment Method: ' . $order->payment_method . '\n';
        $msg .= 'Delivery Information: ' . $order->address . '\n';

        $msg .= 'Once again, we appreciate your business and trust in '. $general_setting->site_title .'. We strive to provide exceptional products and services, and we are confident that you will be satisfied with our products.\n';
        $msg .= 'Thank you for choosing ' . $general_setting->site_title . '.\n\n';

        $msg .= 'Best regards,\n';
        $msg .= @$general_setting->develoled_by. '\n';
        $msg .= $general_setting->site_title. '\n\n';
        $msg .= request()->getHost();

        try{
            $this->wpMessage($order->phone, $msg);
        }
        catch(\Exception $e){
        }
        $order->update($data);

        return redirect()->route('order.index')->with('message','Order Update successfully');
    }

    public function serviceUpdate(Request $request)
    {
        $data = $request->all();
        if (isset($data['is_approve'])) {
            $data['is_approve'] = 1;
        } else {
            $data['is_approve'] = 0;
        }
        $id = $request->id;
        $order = Order::find($id);

        if (isset($data['result_doc'])) {
            $image = $data['result_doc'];
            $imageName = date("Ymdhis").'.'.$image->getClientOriginalExtension();
            $image->move('public/images/customer/docs', $imageName);
            $data['result_doc'] = $imageName;
        }

        $msg = '*Dear :* '. $order->name .' \n\n';

        if ($data['order_status'] == 0) {
            $status = 'Pending';
            $msg .= '*Your Order Status is updated to :* '. $status . '\n\n';
        } elseif ($data['order_status'] == 1) {
            $status = 'Completed';
            $msg .= '*Your Order Status is updated to :* '. $status . '\n\n';
            $msg .= '*Note :* If you have received your order please go to in my orders and mark order received. \n\n';
            $msg .= '*Thank you*';
        } elseif ($data['order_status'] == 2) {
            $status = 'Rejected';
            $msg .= '*Your Order Status is updated to :* '. $status . '\n\n';
        } else {
            $status = 'Ready For Delivery';
            $msg .= '*Your Order Status is updated to :* '. $status . '\n\n';
            $msg .= '*Order Expected Date is :* '. $data['delivery_date'] . '\n\n';
        }

        $general_setting = GeneralSetting::first();

        $msg .= '*Service Order Details:*\n';
        $msg .= 'Order Number: '.$order->id.'\n';
        $msg .=  'Order Date: '.$order->created_at.'\n\n';

        $msg .= '*Service Detail:*\n';
        foreach ($order->orderProducts as $key => $product) {
            $msg .= 'Name: '. $product->product->name . '\n';
            $msg .= 'Subject: '. $order->subject . '\n';
            $msg .= 'Project Title: '. $order->project_title . '\n';

            $msg .= 'project_guide_lines: '. $order->project_guide_lines . '\n';
            $msg .= 'Citation Sytle: '. $order->citation_style . '\n';
            $msg .= 'Font Style: '. $order->font_style . '\n';
            $msg .= 'Language: '. $order->language . '\n';
            $msg .= 'References: '. $order->references . '\n';
            $msg .= 'Academic Level: '. $order->academic_year . '\n';
            $msg .= 'DeadLine: '. $order->variant_id . '\n';
            $msg .= 'Number Of Pages: '. $order->number_of_pages . '\n';
            $msg .= 'Word Count: '. $order->word_count . '\n';
            $msg .= 'Line Spacing: '. $order->spacing . '\n\n';

            $msg .= '*Addons* \n';
            if($order->quality_double_checker){$msg .= '-- Quality Double Checker \n';}
            if($order->abstract_page){$msg .= '-- Abstract Page \n';}
            if($order->one_page_summary){$msg .= '-- One Page Summary \n';}
            if($order->grammar_checker){$msg .= '-- Grammar Checker \n';}
            if($order->preferred_expert){$msg .= '-- Preferred Expert \n';}

        }
        $msg .= '\n*Grand Total:* ';
        $msg .= number_format($order->grand_total, 2) . '\n\n';

        $msg .= '*Payment Information:*\n';
        $msg .= 'Payment Method: ' . $order->payment_method . '\n';
        $msg .= 'Delivery Information: ' . $order->address . '\n';

        $msg .= 'Once again, we appreciate your business and trust in '. $general_setting->site_title .'. We strive to provide exceptional products and services, and we are confident that you will be satisfied with our products.\n';
        $msg .= 'Thank you for choosing ' . $general_setting->site_title . '.\n\n';

        $msg .= 'Best regards,\n';
        $msg .= @$general_setting->develoled_by. '\n';
        $msg .= $general_setting->site_title. '\n\n';
        $msg .= request()->getHost();

        try{
            $this->wpMessage($order->phone, $msg);
        }
        catch(\Exception $e){
        }

        $order->update($data);

//        result doc
        $path = public_path('public/images/customer/docs/'.$order->result_doc);
        if($order->result_doc) {
            try{
                $this->wpAttachMessage($path, $order->phone, $order->result_doc);
            }
            catch(\Exception $e){
            }
        }

        return redirect()->route('services.list')->with('message','Service Update successfully');
    }

    public function delete($id)
    {
        Order::where('id', $id)->delete();
        OrderProduct::where('order_id', $id)->delete();
        return back()->with('not_permitted','Order deleted successfully');
    }

    public function deleteDoc($id)
    {
        $order = Order::find($id);
        $path = public_path('public/images/customer/docs/'.$order->result_doc);
        unlink($path);
        $order->update(['result_doc' => null]);
        return back()->with('not_permitted','Delivered Doc is deleted successfully');
    }


    public function frontendOrderIndex() {
        $data = Order::where('user_id', Auth::user()->id)->where('is_donation', 0)->where('is_service', 0)->orderByDesc('id')->paginate(5);
        return view('frontend.order_index', compact('data'));
    }

    public function frontendBookIndex() {
        $data = Booking::with('bookingProduct')->where('customer_id', Auth::user()->customer->id)->orderByDesc('id')->paginate(5);
        return view('frontend.book_index', compact('data'));
    }

    public function frontendDonationIndex() {
        $data = Order::where('user_id', Auth::user()->id)->where('is_donation', 1)->orderByDesc('id')->paginate(5);
        return view('frontend.donation_index', compact('data'));
    }

    public function frontendServiceIndex() {
        $data = Order::where('user_id', Auth::user()->id)->where('is_service', 1)->orderByDesc('id')->paginate(5);
        return view('frontend.service_index', compact('data'));
    }

    public function frontendOrderTrack() {
        return view('frontend.order_track');
    }

    public function orderStatus(Request $request) {
        $order_status = Order::where('id', $request->id)->where('user_id', Auth::user()->id)->first();
        if($order_status) {
            return view('frontend.order_track', compact('order_status'));
        }
        $message = "You have enetered incorrect Order ID";
        return view('frontend.order_track', compact('message'));
    }

    public function generateInvoice($id)
    {
        $lims_sale_data = Order::find($id);
        $lims_product_sale_data = OrderProduct::where('order_id', $id)->get();
        $lims_customer_data = User::find($lims_sale_data->user_id);
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
            'lims_account_data_cradit' => $lims_account_data_cradit,
            'lims_account_data_debit' => $lims_account_data_debit,
            'lims_sale_data' => $lims_sale_data,
            'lims_product_sale_data' => $lims_product_sale_data,
            'lims_customer_data' => $lims_customer_data,
            'numberInWords' => $numberInWords
        ];

//        return View('pdf.order_pdf', $data);
        return view('pdf.order_pdf', compact('header', 'footer', 'water_mark', 'lims_account_data_cradit', 'lims_account_data_debit', 'lims_sale_data', 'lims_product_sale_data', 'lims_customer_data', 'numberInWords'));


        $pdf = PDF::loadView('pdf.order_pdf', $data);
        return $pdf->download('order-invoice.pdf');
    }

    public function bookingGenerateInvoice($id)
    {
        if (!$this->canAccessBookingInvoice($id)) {
            abort(403, 'You are not authorized to download this invoice.');
        }

        $lims_sale_data = Booking::findOrFail($id);
        $lims_product_sale_data = BookingProduct::where('booking_id', $id)->get();
        $lims_customer_data = User::find($lims_sale_data->user_id);
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
            'lims_account_data_cradit' => $lims_account_data_cradit,
            'lims_account_data_debit' => $lims_account_data_debit,
            'lims_sale_data' => $lims_sale_data,
            'lims_product_sale_data' => $lims_product_sale_data,
            'lims_customer_data' => $lims_customer_data,
            'numberInWords' => $numberInWords
        ];

//        return View('pdf.rent_pdf', $data);

        $pdf = PDF::loadView('pdf.rent_pdf', $data);
        return $pdf->download('booking-invoice.pdf');
    }

    private function canAccessBookingInvoice($bookingId)
    {
        $booking = Booking::find($bookingId);
        if (!$booking) {
            return false;
        }

        if (session('booking_invoice_' . $bookingId) === true) {
            return true;
        }

        if (!Auth::check()) {
            return false;
        }

        $role = Role::find(Auth::user()->role_id);
        if ($role && $role->hasPermissionTo('booking_index')) {
            return true;
        }

        $customer = Customer::where('user_id', Auth::id())->first();

        return $customer && (int) $booking->customer_id === (int) $customer->id;
    }
}

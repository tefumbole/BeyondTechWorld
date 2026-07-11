<?php

namespace App\Http\Controllers;

use App\Account;
use App\Biller;
use App\Booking;
use App\BookingProduct;
use App\Customer;
use App\GeneralSetting;
use App\Letter;
use App\Payment;
use App\Product_Sale;
use App\ProductQuotation;
use App\Quotation;
use App\Sale;
use App\Warehouse;
use Illuminate\Http\Request;
use NumberToWords\NumberToWords;

class QRController extends Controller
{
    Public function saleScan($ref) {
        $lims_sale_data = Sale::where('reference_no', $ref)->first();
        if (!$lims_sale_data) {
            return "no data found";
        }
        $lims_product_sale_data = Product_Sale::where('sale_id', $lims_sale_data->id)->get();
        $lims_biller_data = Biller::find($lims_sale_data->biller_id);
        $lims_warehouse_data = Warehouse::find($lims_sale_data->warehouse_id);
        $lims_customer_data = Customer::find($lims_sale_data->customer_id);
        $lims_payment_data = Payment::where('sale_id', $lims_sale_data->id)->get();
        $lims_payment_debit_data = Payment::where('debit_sale_id', $lims_sale_data->id)->get();
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

        return view('sale.qr-invoice', compact('header', 'footer', 'water_mark', 'lims_account_data_cradit', 'lims_account_data_debit', 'lims_sale_data', 'lims_product_sale_data', 'lims_biller_data', 'lims_warehouse_data', 'lims_customer_data', 'lims_payment_data', 'numberInWords'));
    }

    public function quotationScan($ref)
    {
        $lims_sale_data = Quotation::where('reference_no', $ref)->first();
        if (!$lims_sale_data) {
            return "no data found";
        }
        $lims_product_sale_data = ProductQuotation::where('quotation_id', $lims_sale_data->id)->get();
        $lims_biller_data = Biller::find($lims_sale_data->biller_id);
        $lims_warehouse_data = Warehouse::find($lims_sale_data->warehouse_id);
        $lims_customer_data = Customer::find($lims_sale_data->customer_id);


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

        return view('quotation.qr-invoice', compact('header', 'footer', 'water_mark', 'lims_sale_data', 'lims_product_sale_data', 'lims_biller_data', 'lims_warehouse_data', 'lims_customer_data', 'numberInWords'));
    }

    Public function bookingScan($ref) {
        $lims_sale_data = Booking::where('reference_no', $ref)->first();
        if (!$lims_sale_data) {
            return "no data found";
        }
        $lims_product_sale_data = BookingProduct::where('booking_id', $lims_sale_data->id)->get();
        $lims_biller_data = Biller::find($lims_sale_data->biller_id);
        $lims_warehouse_data = Warehouse::find($lims_sale_data->warehouse_id);
        $lims_customer_data = Customer::find($lims_sale_data->customer_id);
        $lims_payment_data = Payment::where('booking_id', $lims_sale_data->id)->get();
        $lims_payment_debit_data = Payment::where('debit_booking_id', $lims_sale_data->id)->get();
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

        return view('booking.qr-invoice', compact('header', 'footer', 'water_mark', 'lims_account_data_cradit', 'lims_account_data_debit', 'lims_sale_data', 'lims_product_sale_data', 'lims_biller_data', 'lims_warehouse_data', 'lims_customer_data', 'lims_payment_data', 'numberInWords'));

    }

    public function letterScan($id)
    {
        $data = Letter::with('category')->find($id);
        if (!$data) {
            abort(404, 'Letter not found');
        }

        $general_setting = GeneralSetting::first();

        return view('letter.public_view', compact('data', 'general_setting'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Account;
use App\Biller;
use App\Booking;
use App\BookingProduct;
use App\Customer;
use App\Employee;
use App\GeneralSetting;
use App\Letter;
use App\Support\LetterRecipients;
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
        $letter = Letter::find($id);
        $error = null;
        $payload = null;

        if (! $letter || $letter->is_rejected) {
            $error = $letter
                ? 'This letter is no longer valid.'
                : 'This letter could not be verified. The link may be invalid or incomplete.';
        } else {
            $issuedTo = $this->letterIssuedTo($letter);
            $subject = trim(preg_replace('/^Subject:\s*/i', '', (string) $letter->subject));
            if (count($issuedTo) === 1) {
                $subject = str_replace('[name]', $issuedTo[0], $subject);
            }
            $when = $letter->sign_signed_at ?: $letter->created_at;
            $payload = [
                'valid' => true,
                'reference' => $letter->reference,
                'subject' => $subject !== '' ? $subject : '—',
                'issued_to' => $issuedTo ? implode(', ', $issuedTo) : '—',
                'date' => $when ? \Carbon\Carbon::parse($when)->format('d M Y') : '—',
            ];
        }

        return view('letter.verify', [
            'error' => $error,
            'data' => $payload,
        ]);
    }

    private function letterIssuedTo(Letter $letter)
    {
        $names = [];
        if ($letter->people_type === 'directory') {
            foreach (LetterRecipients::decodePeopleJson($letter->recipients_json) as $person) {
                if (! empty($person['name'])) {
                    $names[] = $person['name'];
                }
            }
        } elseif ($letter->people_type === 'all') {
            if (preg_match('/c:([^|]*)/', (string) $letter->to, $customerMatch)) {
                foreach (array_filter(explode(',', $customerMatch[1])) as $id) {
                    $person = Customer::find($id);
                    if ($person && $person->name) {
                        $names[] = $person->name;
                    }
                }
            }
            if (preg_match('/e:([^|]*)/', (string) $letter->to, $employeeMatch)) {
                foreach (array_filter(explode(',', $employeeMatch[1])) as $id) {
                    $person = Employee::find($id);
                    if ($person && $person->name) {
                        $names[] = $person->name;
                    }
                }
            }
        } elseif ($letter->people_type !== 'csv') {
            $model = $letter->people_type === 'customer' ? Customer::class : Employee::class;
            foreach (explode(',', (string) $letter->to) as $id) {
                $person = $model::find(trim($id));
                if ($person && $person->name) {
                    $names[] = $person->name;
                }
            }
        }

        return array_values(array_unique($names));
    }
}

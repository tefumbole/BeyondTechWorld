<?php

namespace App\Http\Controllers;

use App\Deposit;
use App\GeneralSetting;
use App\Payment;
use App\Sale;
use App\User;
use Illuminate\Http\Request;
use App\CustomerGroup;
use Illuminate\Validation\Rule;
use NumberToWords\NumberToWords;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Auth;
use Twilio\TwiML\Voice\Pay;

class CustomerGroupController extends Controller
{
    public function index()
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('customer_group')) {
            $depositors = User::where('role_id', 14)->where('is_active', true)->get();
            $lims_customer_group_all = CustomerGroup::where('is_active', true)->get();
            return view('customer_group.create',compact('lims_customer_group_all', 'depositors'));
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => [
                'max:255',
                Rule::unique('customer_groups')->where(function ($query) {
                    return $query->where('is_active', 1);
                }),
            ],
        ]);
        $lims_customer_group_data = $request->all();
        $lims_customer_group_data['is_active'] = true;
        CustomerGroup::create($lims_customer_group_data);
        return redirect('customer_group')->with('message', 'Data inserted successfully');
    }

    public function edit($id)
    {
        $lims_customer_group_data = CustomerGroup::find($id);
        return $lims_customer_group_data;
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'name' => [
                'max:255',
                Rule::unique('customer_groups')->ignore($request->customer_group_id)->where(function ($query) {
                    return $query->where('is_active', 1);
                }),
            ],
        ]);

        $input = $request->all();
        $lims_customer_group_data = CustomerGroup::find($input['customer_group_id']);

        $lims_customer_group_data->update($input);
        return redirect('customer_group')->with('message', 'Data updated successfully');
    }

    public function importCustomerGroup(Request $request)
    {
        //get file
        $upload=$request->file('file');
        $ext = pathinfo($upload->getClientOriginalName(), PATHINFO_EXTENSION);
        if($ext != 'csv')
            return redirect()->back()->with('not_permitted', 'Please upload a CSV file');
        $filename =  $upload->getClientOriginalName();
        $upload=$request->file('file');
        $filePath=$upload->getRealPath();
        //open and read
        $file=fopen($filePath, 'r');
        $header= fgetcsv($file);
        $escapedHeader=[];
        //validate
        foreach ($header as $key => $value) {
            $lheader=strtolower($value);
            $escapedItem=preg_replace('/[^a-z]/', '', $lheader);
            array_push($escapedHeader, $escapedItem);
        }
        //looping through othe columns
        while($columns=fgetcsv($file))
        {
            if($columns[0]=="")
                continue;
            foreach ($columns as $key => $value) {
                $value=preg_replace('/\D/','',$value);
            }
            $data= array_combine($escapedHeader, $columns);

            $customer_group = CustomerGroup::firstOrNew([ 'name'=>$data['name'], 'is_active'=>true ]);
            $customer_group->name = $data['name'];
            $customer_group->percentage = $data['percentage'];
            $customer_group->is_active = true;
            $customer_group->save();
        }
        return redirect('customer_group')->with('message', 'Customer Group imported successfully');

    }

    public function exportCustomerGroup(Request $request)
    {
        $lims_customer_group_data = $request['customer_groupArray'];
        $csvData=array('name, percentage');
        foreach ($lims_customer_group_data as $customer_group) {
            if($customer_group > 0) {
                $data = CustomerGroup::where('id', $customer_group)->first();
                $csvData[]=$data->name. ',' . $data->percentage;
            }
        }
        $filename="customer_group- " .date('d-m-Y').".csv";
        $file_path=public_path().'/downloads/'.$filename;
        $file_url=url('/').'/downloads/'.$filename;
        $file = fopen($file_path,"w+");
        foreach ($csvData as $exp_data){
            fputcsv($file,explode(',',$exp_data));
        }
        fclose($file);
        return $file_url;
    }

    public function deleteBySelection(Request $request)
    {
        $customer_group_id = $request['customer_groupIdArray'];
        foreach ($customer_group_id as $id) {
            $lims_customer_group_data = CustomerGroup::find($id);
            $lims_customer_group_data->is_active = false;
            $lims_customer_group_data->save();
        }
        return 'Customer Group deleted successfully!';
    }

    public function destroy($id)
    {
        $lims_customer_group_data = CustomerGroup::find($id);
        $lims_customer_group_data->is_active = false;
        $lims_customer_group_data->save();
        return redirect('customer_group')->with('not_permitted', 'Data deleted successfully');
    }

    public function addDeposit(Request $request)
    {
        $data = $request->all();
        $data['user_id'] = Auth::id();
        $lims_customer_data = CustomerGroup::find($data['customer_group_id']);

        if($data['payment_method'] == 1) {
            $lims_customer_data->deposit += $data['amount'];
            $lims_customer_data->save();
            $data['payment_reference'] = rand();
            $data['status'] = 1;
            $deposit = Deposit::create($data);
            $message = 'Deposit added successfully';
        }

//        if($data['payment_method'] == 3) {
//            $data['status'] = 0;
//            Deposit::create($data);
//
//            $token = getenv("MOMO_TOKEN");
//            $route = route('customer.payment_check');
//            $mtn_number = $data['mtn_number'];
//            $doctor_fee = $data['amount'];
//            $link = $this->mobileMoneyRequestLink($token, $doctor_fee, $route, $lims_customer_data->id, $mtn_number);
//            if ($link == false) {
//                $message = 'There is issue in payment method';
//                return redirect('customer')->with('create_message', $message);
//            }
//
//            header("Location: $link");
//            die();
//        }
        return redirect('/customer_group/gen_payment_invoice/' . $deposit->id);
    }

    public function genInvoice($id)
    {
        $role = Role::find(Auth::user()->role_id);
        $permissions = Role::findByName($role->name)->permissions;

        foreach ($permissions as $permission) {
            $all_permission[] = $permission->name;
        }
        $deposit = Deposit::find($id);
        $lims_customer_data = CustomerGroup::find($deposit->customer_group_id);

        $setting = GeneralSetting::first();
        $header = $setting->email_header;
        $footer = $setting->email_footer;
        $water_mark = $setting->email_water_mark;

        $numberToWords = new NumberToWords();
        if(\App::getLocale() == 'ar' || \App::getLocale() == 'hi' || \App::getLocale() == 'vi' || \App::getLocale() == 'en-gb')
            $numberTransformer = $numberToWords->getNumberTransformer('en');
        else
            $numberTransformer = $numberToWords->getNumberTransformer(\App::getLocale());
        $numberInWords = $numberTransformer->toWords($deposit->amount);


        return view('customer_group.invoice', compact('header', 'footer', 'water_mark', 'all_permission', 'deposit', 'lims_customer_data', 'numberInWords'));
    }

    public function Deposits($id) {
        $deposits = Deposit::where('customer_group_id', $id)->get();
        return view('customer_group.deposits', compact('deposits'));
    }

    public function Payments($id) {
        $payments = Sale::where('customer_group_id', $id)->get();
        return view('customer_group.payments', compact('payments'));
    }
}

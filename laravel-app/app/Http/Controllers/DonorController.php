<?php

namespace App\Http\Controllers;

use App\Donor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;

class DonorController extends Controller
{
    public function __construct() {

        $this->middleware(function ($request, $next) {
            $role = Role::find(Auth::user()->role_id);
            $permissions = Role::findByName($role->name)->permissions;

            foreach ($permissions as $permission) {
                $all_permission[] = $permission->name;
            }
            View::share ( 'all_permission', $all_permission);

            return $next($request);
        });
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $data = Donor::where('is_active', true)->get();
        return view('fixed_asset.donor.index', compact('data'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('fixed_asset.donor.create');

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'image' => 'image|mimes:jpg,jpeg,png,gif|max:10000',
        ]);

        $data = $request->except('image');
        $data['is_active'] = true;
        $image = $request->image;
        if ($image) {
            $ext = pathinfo($image->getClientOriginalName(), PATHINFO_EXTENSION);
            $imageName = preg_replace('/[^a-zA-Z0-9]/', '', $request['company_name']);
            $imageName = $imageName . '.' . $ext;
            $image->move('public/images/donor', $imageName);

            $data['image'] = $imageName;
        }
        Donor::create($data);
        $message = 'Data inserted successfully';
        try{
            Mail::send( 'mail.biller_create', $data, function( $message ) use ($data)
            {
                $message->to( $data['email'] )->subject( 'New Donor' );
            });
        }
        catch(\Exception $e){
            $message = 'Data inserted successfully. Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
        }
        return redirect('donor')->with('message', $message);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Donor  $donor
     * @return \Illuminate\Http\Response
     */
    public function show(Donor $donor)
    {
        //
    }

    public function edit($id)
    {
        $lims_biller_data = Donor::where('id',$id)->first();
        return view('fixed_asset.donor.edit',compact('lims_biller_data'));
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'image' => 'image|mimes:jpg,jpeg,png,gif|max:100000',
        ]);

        $input = $request->except('image');
        $image = $request->image;

        if ($image) {
            $ext = pathinfo($image->getClientOriginalName(), PATHINFO_EXTENSION);
            $imageName = preg_replace('/[^a-zA-Z0-9]/', '', $request['company_name']);
            $imageName = $imageName . '.' . $ext;
            $image->move('public/images/donor', $imageName);
            $input['image'] = $imageName;
        }

        $data = Donor::findOrFail($id);
        $data->update($input);
        return redirect('donor')->with('message','Data updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Donor  $donor
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $lims_donor_data = Donor::find($id);
        $lims_donor_data->is_active = false;
        $lims_donor_data->save();
        return redirect('donor')->with('not_permitted','Data deleted successfully');
    }
}

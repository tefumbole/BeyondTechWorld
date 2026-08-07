<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Application;
use App\BeyondUser;
use App\User;
use App\Roles;
use App\Biller;
use App\Warehouse;
use App\CustomerGroup;
use App\Customer;
use App\Services\ApplicationService;
use Hash;
use Illuminate\Support\Facades\Auth;
use Keygen;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Mail\UserNotification;
use Illuminate\Support\Facades\Mail;

class UserController extends Controller
{

    public function index(Request $request)
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('users-index')){
            $permissions = Role::findByName($role->name)->permissions;
            foreach ($permissions as $permission)
                $all_permission[] = $permission->name;
            $category = $request->get('category', 'all');
            if ($category === 'applicants') {
                return $this->applicantsIndex($request, $all_permission);
            }
            $lims_user_list = User::where('is_deleted', false)->whereRaw('COALESCE(is_active, 0) = 1')->get();
            return view('user.index', compact('lims_user_list', 'all_permission', 'category'));
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    /**
     * People → Users → Applicants: portal applicants + application contacts.
     */
    protected function applicantsIndex(Request $request, array $all_permission)
    {
        $q = $request->get('q');
        $portalApplicants = BeyondUser::query()
            ->where('role', 'applicant')
            ->when($q, function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(function ($w) use ($like) {
                    $w->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                });
            })
            ->orderBy('name')
            ->get();

        $directory = app(ApplicationService::class)->applicantDirectory($q);
        $category = 'applicants';

        return view('user.applicants', compact(
            'portalApplicants', 'directory', 'all_permission', 'category', 'q'
        ));
    }

    /**
     * Delete one or many applications from People → Applicants.
     */
    public function deleteApplicants(Request $request)
    {
        $role = Role::find(Auth::user()->role_id);
        if (! $role || (! $role->hasPermissionTo('users-delete') && ! $role->hasPermissionTo('users-index'))) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to delete applicants.');
        }

        $ids = $request->input('application_ids', []);
        if (! is_array($ids)) {
            $ids = array_filter(explode(',', (string) $ids));
        }
        // Flatten checkbox groups that may submit comma-joined ID lists per row.
        $flat = [];
        foreach ($ids as $raw) {
            foreach (preg_split('/\s*,\s*/', (string) $raw) as $id) {
                $id = trim($id);
                if ($id !== '') {
                    $flat[] = $id;
                }
            }
        }

        $deleted = app(ApplicationService::class)->deleteApplications($flat);
        if ($deleted < 1) {
            return redirect()->route('user.index', ['category' => 'applicants'])
                ->with('not_permitted', 'No applications were selected or found to delete.');
        }

        return redirect()->route('user.index', ['category' => 'applicants'])
            ->with('message', $deleted === 1
                ? '1 application deleted.'
                : $deleted.' applications deleted.');
    }

    public function create()
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('users-add')){
            $lims_role_list = Roles::where('is_active', true)->get();
            $lims_biller_list = Biller::where('is_active', true)->get();
            $lims_warehouse_list = Warehouse::where('is_active', true)->get();
            $lims_customer_group_list = CustomerGroup::where('is_active', true)->get();
            return view('user.create', compact('lims_role_list', 'lims_biller_list', 'lims_warehouse_list', 'lims_customer_group_list'));
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    public function generatePassword()
    {
        $id = Keygen::numeric(6)->generate();
        return $id;
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => [
                'max:255',
                Rule::unique('users')->where(function ($query) {
                    return $query->where('is_deleted', false);
                }),
            ],
            'email' => [
                'email',
                'max:255',
                Rule::unique('users')->where(function ($query) {
                    return $query->where('is_deleted', false);
                }),
            ],
        ]);

//        if($request->role_id == 5) {
//            $this->validate($request, [
//                'phone_number' => [
//                    'max:255',
//                    Rule::unique('customers')->where(function ($query) {
//                        return $query->where('is_active', 1);
//                    }),
//                ],
//            ]);
//        }
        $this->validate($request, [
            'sign' => 'image|mimes:jpg,jpeg,png,gif,svg|max:10000',
        ]);

        $this->validate($request, [
            'stemp' => 'image|mimes:jpg,jpeg,png,gif,svg|max:10000',
        ]);

        $this->validate($request, [
            'approve' => 'image|mimes:jpg,jpeg,png,gif,svg|max:10000',
        ]);

        $data = $request->except('sign', 'stemp', 'approve');
        $sign = $request->sign;
        $stemp = $request->stemp;
        $approve = $request->approve;
        if ($sign) {
            $ext = pathinfo($sign->getClientOriginalName(), PATHINFO_EXTENSION);
            $imageName = preg_replace('/[^a-zA-Z0-9]/', '', $request['sign']);
            $imageName = $imageName . '.' . $ext;
            $sign->move('public/images/user', $imageName);

            $data['sign'] = $imageName;
        }
        if ($stemp) {
            $ext = pathinfo($stemp->getClientOriginalName(), PATHINFO_EXTENSION);
            $imageName = preg_replace('/[^a-zA-Z0-9]/', '', $request['stemp']);
            $imageName = $imageName . '.' . $ext;
            $stemp->move('public/images/user', $imageName);

            $data['stemp'] = $imageName;
        }

        if ($approve) {
            $ext = pathinfo($approve->getClientOriginalName(), PATHINFO_EXTENSION);
            $imageName = preg_replace('/[^a-zA-Z0-9]/', '', $request['approve']);
            $imageName = $imageName . '.' . $ext;
            $approve->move('public/images/user', $imageName);

            $data['approve'] = $imageName;
        }
        $message = 'User created successfully';
        try {
            Mail::send( 'mail.user_details', $data, function( $message ) use ($data)
            {
                $message->to( $data['email'] )->subject( 'User Account Details' );
            });
        }
        catch(\Exception $e){
            $message = 'User created successfully. Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
        }
        if(!isset($data['is_active']))
            $data['is_active'] = false;
        $data['is_deleted'] = false;
        $data['password'] = bcrypt($data['password']);
        $data['phone'] = $data['phone_number'];
        $user = User::create($data);
        if($data['role_id'] == 5 || $data['role_id'] == 12) {
            $data['user_id'] = $user->id;
            $data['name'] = $data['customer_name'];
            $data['phone_number'] = $data['phone'];
            $data['is_active'] = true;
            Customer::create($data);
        }
        return redirect('user')->with('message1', $message);
    }

    public function edit($id)
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('users-edit')){
            $lims_user_data = User::find($id);
            $lims_role_list = Roles::where('is_active', true)->get();
            $lims_biller_list = Biller::where('is_active', true)->get();
            $lims_warehouse_list = Warehouse::where('is_active', true)->get();
            return view('user.edit', compact('lims_user_data', 'lims_role_list', 'lims_biller_list', 'lims_warehouse_list'));
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    public function update(Request $request, $id)
    {
        if(!env('USER_VERIFIED'))
            return redirect()->back()->with('not_permitted', 'This feature is disable for demo!');

        $this->validate($request, [
            'name' => [
                'max:255',
                Rule::unique('users')->ignore($id)->where(function ($query) {
                    return $query->where('is_deleted', false);
                }),
            ],
            'email' => [
                'email',
                'max:255',
                Rule::unique('users')->ignore($id)->where(function ($query) {
                    return $query->where('is_deleted', false);
                }),
            ],
        ]);

        $this->validate($request, [
            'sign' => 'image|mimes:jpg,jpeg,png,gif,svg|max:10000',
        ]);

        $this->validate($request, [
            'stemp' => 'image|mimes:jpg,jpeg,png,gif,svg|max:10000',
        ]);

        $this->validate($request, [
            'approve' => 'image|mimes:jpg,jpeg,png,gif,svg|max:10000',
        ]);

        $input = $request->except('sign', 'stemp', 'password', 'approve');
        $sign = $request->sign;
        $stemp = $request->stemp;
        $approve = $request->approve;
        if ($sign) {
            $ext = pathinfo($sign->getClientOriginalName(), PATHINFO_EXTENSION);
            $imageName = preg_replace('/[^a-zA-Z0-9]/', '', $request['sign']);
            $imageName = $imageName . '.' . $ext;
            $sign->move('public/images/user', $imageName);

            $input['sign'] = $imageName;
        }
        if ($stemp) {
            $ext = pathinfo($stemp->getClientOriginalName(), PATHINFO_EXTENSION);
            $imageName = preg_replace('/[^a-zA-Z0-9]/', '', $request['stemp']);
            $imageName = $imageName . '.' . $ext;
            $stemp->move('public/images/user', $imageName);

            $input['stemp'] = $imageName;
        }

        if ($approve) {
            $ext = pathinfo($approve->getClientOriginalName(), PATHINFO_EXTENSION);
            $imageName = preg_replace('/[^a-zA-Z0-9]/', '', $request['approve']);
            $imageName = $imageName . '.' . $ext;
            $approve->move('public/images/user', $imageName);

            $input['approve'] = $imageName;
        }

        if(!isset($input['is_active']))
            $input['is_active'] = false;
        if(!empty($request['password']))
            $input['password'] = bcrypt($request['password']);
        $lims_user_data = User::find($id);
        if($lims_user_data->sign) {
            $file = public_path('public/images/user/'.$lims_user_data->sign);
            if(file_exists($file)){
                unlink($file);
            }
        }
        if($lims_user_data->stemp) {
            $file = public_path('public/images/user/'.$lims_user_data->stemp);
            if(file_exists($file)){
                unlink($file);
            }
        }
        $lims_user_data->update($input);
        return redirect('user')->with('message2', 'Data updated successfullly');
    }

    public function profile($id)
    {
        $lims_user_data = User::find($id);
        return view('user.profile', compact('lims_user_data'));
    }

    public function profileUpdate(Request $request, $id)
    {
        if(!env('USER_VERIFIED'))
            return redirect()->back()->with('not_permitted', 'This feature is disable for demo!');


        $input = $request->all();
        if(Auth::user()->role_id == 12) {
            $sign = $request->sign;
            if ($sign) {
                $ext = pathinfo($sign->getClientOriginalName(), PATHINFO_EXTENSION);
                $imageName = preg_replace('/[^a-zA-Z0-9]/', '', $request['sign']);
                $imageName = $imageName . '.' . $ext;
                $sign->move('public/images/user', $imageName);

                $input['sign'] = $imageName;
            }
            $stemp = $request->stemp;
            if ($stemp) {
                $ext = pathinfo($stemp->getClientOriginalName(), PATHINFO_EXTENSION);
                $imageName = preg_replace('/[^a-zA-Z0-9]/', '', $request['sign']);
                $imageName = $imageName . '.' . $ext;
                $stemp->move('public/images/user', $imageName);

                $input['stemp'] = $imageName;
            }
        }
        $lims_user_data = User::find($id);
        $lims_customer_data = Customer::where('user_id', $lims_user_data->id)->first();
        $lims_user_data->update($input);
        if($lims_customer_data) {
            $input['phone_number'] = $input['phone'];
            $lims_customer_data->update($input);
        }
        return redirect()->back()->with('message3', 'Data updated successfullly');
    }

    public function frontendUserAccount()
    {
        $id = Auth::User()->id;
        $lims_user_data = User::find($id);
        return view('frontend.profile', compact('lims_user_data'));
    }

    public function frontendUserAccountUpdate(Request $request)
    {
        if(!env('USER_VERIFIED'))
            return redirect()->back()->with('not_permitted', 'This feature is disable for demo!');

        $input = $request->all();
        $lims_user_data = User::find($request->id);
        $lims_user_data->update($input);

        $lims_customer_data = Customer::where('user_id', $lims_user_data->id)->first();
        if($lims_customer_data) {
            $lims_customer_data->update($input);
        } else {
            $input['user_id'] = $lims_user_data->id;
            $input['customer_group_id'] = 1;
            $input['phone_number'] = $lims_user_data->phone;
            Customer::create($input);
        }
        return redirect()->back()->with('success1', 'Data updated successfullly');
    }

    public function frontendChangePassword(Request $request)
    {
        $id = Auth::id();
        $input = $request->all();
        $lims_user_data = User::findOrFail($id);
//        dd(Hash::check($input['current_pass'], $lims_user_data->password));
        if (Hash::check($input['current_pass'], $lims_user_data->password)) {
            if($input['new_pass'] != $input['confirm_pass']) {
                return back()->with('not_permitted', "Please Confirm your new password");
            }
            $lims_user_data->password = bcrypt($input['new_pass']);
            $lims_user_data->save();
            return back()->with('message', "Your password has been changed");
        }
        else {
            return back()->with('not_permitted', "Current Password doesn't match");
        }
    }

    public function changePassword(Request $request, $id)
    {
        if(!env('USER_VERIFIED'))
            return redirect()->back()->with('not_permitted', 'This feature is disable for demo!');

        $input = $request->all();
        $lims_user_data = User::find($id);
        if($input['new_pass'] != $input['confirm_pass'])
            return redirect("user/" .  "profile/" . $id )->with('message2', "Please Confirm your new password");

        if (Hash::check($input['current_pass'], $lims_user_data->password)) {
            $lims_user_data->password = bcrypt($input['new_pass']);
            $lims_user_data->save();
        }
        else {
            return redirect("user/" .  "profile/" . $id )->with('message1', "Current Password doesn't match");
        }
        auth()->logout();
        return redirect('/');
    }

    public function deleteBySelection(Request $request)
    {
        $user_id = $request['userIdArray'];
        foreach ($user_id as $id) {
            $lims_user_data = User::find($id);
            $lims_user_data->is_deleted = true;
            $lims_user_data->is_active = false;
            $lims_user_data->save();
        }
        return 'User deleted successfully!';
    }

    public function destroy($id)
    {
        if(!env('USER_VERIFIED'))
            return redirect()->back()->with('not_permitted', 'This feature is disable for demo!');

        if(Auth::id() == $id){
            return redirect('user')->with('message3', 'User cannot delete itself');
        }

        $lims_user_data = User::find($id);
        $lims_user_data->is_deleted = true;
        $lims_user_data->name = 'deleted';
        $lims_user_data->password = 'deleted';
        $lims_user_data->is_active = false;
        $lims_user_data->save();

        return redirect('user')->with('message3', 'Data deleted successfullly');
    }
}

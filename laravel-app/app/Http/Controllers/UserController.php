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
use Illuminate\Support\Facades\DB;
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
        if($role && $role->hasPermissionTo('users-index')){
            $all_permission = $role->permissions->pluck('name')->all();
            $category = $request->get('category', 'all');
            if ($category === 'applicants') {
                return $this->applicantsIndex($request, $all_permission);
            }
            // Lean query + role map (avoids N+1 and loading unused columns).
            $lims_user_list = User::query()
                ->select([
                    'id', 'name', 'email', 'company_name', 'phone', 'additional_phone',
                    'role_id', 'is_active', 'sign', 'stemp', 'approve',
                    'sign_request_token', 'sign_request_type', 'sign_request_expires_at',
                ])
                ->where(function ($q) {
                    $q->where('is_deleted', false)->orWhereNull('is_deleted');
                })
                ->whereRaw('COALESCE(is_active, 0) = 1')
                ->orderBy('name')
                ->get();
            $rolesById = DB::table('roles')
                ->whereIn('id', $lims_user_list->pluck('role_id')->filter()->unique()->values())
                ->pluck('name', 'id');

            return view('user.index', compact('lims_user_list', 'all_permission', 'category', 'rolesById'));
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    /**
     * People → Users → Applicants: Job Board / Internship applications only.
     * Optional ?open={applicationId} opens that applicant as a sub-tab.
     */
    protected function applicantsIndex(Request $request, array $all_permission)
    {
        $q = $request->get('q');
        $directory = app(ApplicationService::class)->applicantDirectory($q);
        $category = 'applicants';
        $openId = (string) $request->get('open', '');

        $application = null;
        $linkedUser = null;
        $assignableRoles = collect();
        $warehouses = collect();
        $billers = collect();
        $enrolment = null;

        if ($openId !== '') {
            $application = Application::with(['job', 'internshipProgram'])->find($openId);
            if ($application) {
                $detail = $this->applicantDetailPayload($application);
                $linkedUser = $detail['linkedUser'];
                $assignableRoles = $detail['assignableRoles'];
                $warehouses = $detail['warehouses'];
                $billers = $detail['billers'];
                $enrolment = $detail['enrolment'];
            } else {
                $openId = '';
            }
        }

        return view('user.applicants', compact(
            'directory', 'all_permission', 'category', 'q', 'openId',
            'application', 'linkedUser', 'assignableRoles', 'warehouses', 'billers', 'enrolment'
        ));
    }

    /**
     * Edit applicant — opens Applicants sub-tab for this person.
     */
    public function editApplicant($applicationId)
    {
        $role = Role::find(Auth::user()->role_id);
        if (! $role || (! $role->hasPermissionTo('users-edit') && ! $role->hasPermissionTo('users-add'))) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to edit interns.');
        }

        Application::findOrFail($applicationId);

        return redirect()->route('user.index', [
            'category' => 'applicants',
            'open' => $applicationId,
        ]);
    }

    protected function applicantDetailPayload(Application $application)
    {
        $assignableRoles = Roles::where('is_active', true)
            ->whereIn('name', ['Intern', 'staff', 'Internship Supervisor', 'Internship Administrator'])
            ->orderBy('name')
            ->get();
        $linkedUser = null;
        if ($application->user_id) {
            $linkedUser = User::where('is_deleted', false)->find($application->user_id);
        }
        if (! $linkedUser && $application->email) {
            $linkedUser = User::where('is_deleted', false)
                ->whereRaw('LOWER(email) = ?', [strtolower(trim($application->email))])
                ->first();
        }
        $enrolment = null;
        if (class_exists(\App\InternshipEnrolment::class)) {
            $enrolment = \App\InternshipEnrolment::with(['program', 'supervisor'])
                ->where('application_id', $application->id)
                ->orderByDesc('id')
                ->first();
            if (! $enrolment && $linkedUser) {
                $enrolment = \App\InternshipEnrolment::with(['program', 'supervisor'])
                    ->where('student_user_id', $linkedUser->id)
                    ->whereIn('status', ['pending', 'active', 'paused'])
                    ->orderByDesc('id')
                    ->first();
            }
        }

        return [
            'assignableRoles' => $assignableRoles,
            'linkedUser' => $linkedUser,
            'warehouses' => Warehouse::where('is_active', true)->get(),
            'billers' => Biller::where('is_active', true)->get(),
            'enrolment' => $enrolment,
        ];
    }

    public function updateApplicant(Request $request, $applicationId)
    {
        $role = Role::find(Auth::user()->role_id);
        if (! $role || (! $role->hasPermissionTo('users-edit') && ! $role->hasPermissionTo('users-add'))) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to edit interns.');
        }

        $application = Application::findOrFail($applicationId);
        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'role_id' => 'nullable|integer|exists:roles,id',
            'warehouse_id' => 'nullable|integer',
            'biller_id' => 'nullable|integer',
            'password' => 'nullable|string|min:6|max:64',
            'is_active' => 'nullable|boolean',
        ]);

        // Update all applications for this person (same email).
        $emailKey = strtolower(trim((string) $application->email));
        $relatedIds = $emailKey !== ''
            ? Application::whereRaw('LOWER(email) = ?', [$emailKey])->pluck('id')->all()
            : [$application->id];
        if (empty($relatedIds)) {
            $relatedIds = [$application->id];
        }

        Application::whereIn('id', $relatedIds)->update([
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'whatsapp_number' => $data['phone'] ?? null,
        ]);

        $message = 'Intern details updated.';
        $plainPassword = null;

        if (! empty($data['role_id'])) {
            $assignable = Roles::where('is_active', true)
                ->whereIn('name', ['Intern', 'staff', 'Internship Supervisor', 'Internship Administrator'])
                ->where('id', $data['role_id'])
                ->first();
            if (! $assignable) {
                return back()->withInput()->with('not_permitted', 'Selected role cannot be assigned from Interns.');
            }

            $user = null;
            if ($application->user_id) {
                $user = User::where('is_deleted', false)->find($application->user_id);
            }
            if (! $user) {
                $user = User::where('is_deleted', false)
                    ->whereRaw('LOWER(email) = ?', [strtolower(trim($data['email']))])
                    ->first();
            }

            $warehouseId = $data['warehouse_id'] ?: optional(Warehouse::where('is_active', true)->first())->id;
            $billerId = $data['biller_id'] ?: optional(Biller::where('is_active', true)->first())->id;

            if ($user) {
                $user->name = $data['full_name'];
                $user->email = $data['email'];
                $user->phone = $data['phone'] ?? $user->phone;
                $user->role_id = $assignable->id;
                $user->is_active = $request->has('is_active') ? 1 : (int) $user->is_active;
                if ($warehouseId) {
                    $user->warehouse_id = $warehouseId;
                }
                if ($billerId) {
                    $user->biller_id = $billerId;
                }
                if (! empty($data['password'])) {
                    $user->password = bcrypt($data['password']);
                    $plainPassword = $data['password'];
                }
                $user->save();
                $message = 'Intern updated and role set to '.$assignable->name.'.';
            } else {
                $plainPassword = $data['password'] ?: ('Bt@'.rand(100000, 999999));
                $user = User::create([
                    'name' => $data['full_name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? null,
                    'password' => bcrypt($plainPassword),
                    'role_id' => $assignable->id,
                    'warehouse_id' => $warehouseId,
                    'biller_id' => $billerId,
                    'is_active' => 1,
                    'is_deleted' => 0,
                ]);
                $message = 'Intern updated. ERP user created as '.$assignable->name.'.';
            }

            Application::whereIn('id', $relatedIds)->update(['user_id' => $user->id]);

            // Keep Beyond portal role in sync when present.
            try {
                $beyond = BeyondUser::whereRaw('LOWER(email) = ?', [strtolower(trim($data['email']))])->first();
                if ($beyond) {
                    $map = [
                        'Intern' => 'student',
                        'staff' => 'staff',
                        'Internship Supervisor' => 'staff',
                        'Internship Administrator' => 'staff',
                    ];
                    $beyond->role = $map[$assignable->name] ?? $beyond->role;
                    $beyond->name = $data['full_name'];
                    $beyond->phone = $data['phone'] ?? $beyond->phone;
                    $beyond->save();
                }
            } catch (\Throwable $e) {
                // non-fatal
            }

            if ($plainPassword) {
                $message .= ' Temporary password: '.$plainPassword;
            }
        }

        return redirect()->route('user.index', [
            'category' => 'applicants',
            'open' => $application->id,
        ])->with('message', $message);
    }

    /**
     * Delete one or many applications from People → Applicants.
     */
    public function deleteApplicants(Request $request)
    {
        $role = Role::find(Auth::user()->role_id);
        if (! $role || (! $role->hasPermissionTo('users-delete') && ! $role->hasPermissionTo('users-index'))) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to delete interns.');
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
        $lims_user_data = User::find($id);

        if ($sign) {
            $ext = pathinfo($sign->getClientOriginalName(), PATHINFO_EXTENSION);
            $imageName = preg_replace('/[^a-zA-Z0-9]/', '', pathinfo($sign->getClientOriginalName(), PATHINFO_FILENAME));
            $imageName = ($imageName ?: 'sign') . '_' . time() . '.' . $ext;
            if ($lims_user_data->sign) {
                $old = public_path('images/user/'.$lims_user_data->sign);
                if (is_file($old)) {
                    @unlink($old);
                }
            }
            $sign->move('public/images/user', $imageName);
            $input['sign'] = $imageName;
        }
        if ($stemp) {
            $ext = pathinfo($stemp->getClientOriginalName(), PATHINFO_EXTENSION);
            $imageName = preg_replace('/[^a-zA-Z0-9]/', '', pathinfo($stemp->getClientOriginalName(), PATHINFO_FILENAME));
            $imageName = ($imageName ?: 'stemp') . '_' . time() . '.' . $ext;
            if ($lims_user_data->stemp) {
                $old = public_path('images/user/'.$lims_user_data->stemp);
                if (is_file($old)) {
                    @unlink($old);
                }
            }
            $stemp->move('public/images/user', $imageName);
            $input['stemp'] = $imageName;
        }

        if ($approve) {
            $ext = pathinfo($approve->getClientOriginalName(), PATHINFO_EXTENSION);
            $imageName = preg_replace('/[^a-zA-Z0-9]/', '', pathinfo($approve->getClientOriginalName(), PATHINFO_FILENAME));
            $imageName = ($imageName ?: 'approve') . '_' . time() . '.' . $ext;
            if ($lims_user_data->approve) {
                $old = public_path('images/user/'.$lims_user_data->approve);
                if (is_file($old)) {
                    @unlink($old);
                }
            }
            $approve->move('public/images/user', $imageName);
            $input['approve'] = $imageName;
        }

        if(!isset($input['is_active']))
            $input['is_active'] = false;
        if(!empty($request['password']))
            $input['password'] = bcrypt($request['password']);
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

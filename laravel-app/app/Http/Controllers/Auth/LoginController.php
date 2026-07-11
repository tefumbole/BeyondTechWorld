<?php



namespace App\Http\Controllers\Auth;



use App\Http\Controllers\Controller;

use Illuminate\Foundation\Auth\AuthenticatesUsers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Spatie\Permission\Models\Role;


class LoginController extends Controller

{



    use AuthenticatesUsers;



    protected $redirectTo = '/';

    /**

     * Create a new controller instance.

     *

     * @return void

     */

    public function __construct()

    {

        $this->middleware('guest')->except('logout');

    }



    /**

     * Create a new controller instance.

     *

     * @return void

     */

    public function login(Request $request)

    {

        $input = $request->all();

        $this->validate($request, [

            'name' => 'required',

            'password' => 'required',

        ]);



        $fieldType = filter_var($request->name, FILTER_VALIDATE_EMAIL) ? 'email' : 'name';

        if(auth()->attempt(array($fieldType => $input['name'], 'password' => $input['password'], 'is_active' => 1)))

        {
            $role = Role::find(Auth::user()->role_id);

            if( $role->id != 5 ) {

                if($role->hasPermissionTo('one_time_otp')){
                    Auth::user()->update(['otp_verify' => 0]);
                    return redirect()->route('check.otp');
                }

                return redirect('/admin');
            } else {
                    Auth::user()->update(['otp_verify' => 0]);
                    $otp = $this->sendOTP(Auth::user()->phone);
                    Session::put('otp', $otp);
                    return redirect()->route('otp_screen');
            }

        }else{

            return redirect()->back()->with('not_permitted','username Or Password Are Wrong.');

        }



    }

    public function sendOTP($phone) {
        $otp = rand(1, 999999);
        $msg = "Your OTP is: " . $otp . "\n That will be expired after 5 minutes";
        try {
            $this->wpMessage($phone, $msg);
        } catch (\Exception $e) {
            return $otp;
        }
        return $otp;
    }

}

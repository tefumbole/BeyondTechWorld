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
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $msg = \App\Support\WhatsAppMessage::otpMessage($otp);
        try {
            $this->wpMessage($phone, $msg);
        } catch (\Exception $e) {
            return $otp;
        }
        return $otp;
    }

    /**
     * Admin/POS logout. Clears web OTP flag and any bridged Beyond portal session.
     * Portal users must POST to /portal/logout (beyond.logout) instead.
     */
    public function logout(Request $request)
    {
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->user()->update(['otp_verify' => '0']);
            Auth::guard('web')->logout();
        }
        if (Auth::guard('beyond')->check()) {
            Auth::guard('beyond')->logout();
        }
        $request->session()->forget(['beyond_otp_verified', 'beyond_masked_phone', 'password_reset_phone']);

        return redirect()->route('login');
    }

}

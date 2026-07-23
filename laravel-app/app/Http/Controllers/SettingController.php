<?php

namespace App\Http\Controllers;

use App\Category;
use App\Unit;
use Illuminate\Http\Request;
use App\Customer;
use App\CustomerGroup;
use App\Warehouse;
use App\Biller;
use App\Account;
use App\Currency;
use App\PosSetting;
use App\GeneralSetting;
use App\HrmSetting;
use App\RewardPointSetting;
use DB;
use ZipArchive;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use App\Support\EnvFile;
use App\Services\Messaging\NotificationRouter;

class SettingController extends Controller
{
    public function emptyDatabase()
    {
        if(!env('USER_VERIFIED'))
            return redirect()->back()->with('not_permitted', 'This feature is disable for demo!');
        $tables = DB::select('SHOW TABLES');
        $str = 'Tables_in_' . env('DB_DATABASE');
        foreach ($tables as $table) {
            if($table->$str != 'accounts' && $table->$str != 'general_settings' && $table->$str != 'hrm_settings' && $table->$str != 'languages' && $table->$str != 'migrations' && $table->$str != 'password_resets' && $table->$str != 'permissions' && $table->$str != 'pos_setting' && $table->$str != 'roles' && $table->$str != 'role_has_permissions' && $table->$str != 'users' && $table->$str != 'currencies' && $table->$str != 'reward_point_settings') {
                DB::table($table->$str)->truncate();
            }
        }
        return redirect()->back()->with('message', 'Database cleared successfully');
    }
    public function generalSetting()
    {
        \App\Support\AppVersion::syncToSettings();
        $lims_general_setting_data = GeneralSetting::latest()->first();
        $lims_account_list = Account::where('is_active', true)->get();
        $lims_unit_list = Unit::where('is_active', true)->get();
        $lims_category_list = Category::where('is_active', true)->get();
        $lims_currency_list = Currency::get();
        $zones_array = array();
        $timestamp = time();
        foreach(timezone_identifiers_list() as $key => $zone) {
            date_default_timezone_set($zone);
            $zones_array[$key]['zone'] = $zone;
            $zones_array[$key]['diff_from_GMT'] = 'UTC/GMT ' . date('P', $timestamp);
        }
        return view('setting.general_setting', compact('lims_general_setting_data', 'lims_account_list', 'zones_array', 'lims_currency_list', 'lims_category_list', 'lims_unit_list'));
    }

    public function envSetting()
    {
        $role = \Spatie\Permission\Models\Role::find(Auth::user()->role_id);
        if (Auth::user()->role_id > 2 && (!$role || !$role->hasPermissionTo('env_setting'))) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $envPath = base_path('.env');
        $envContent = is_file($envPath) ? file_get_contents($envPath) : '';

        return view('setting.env_setting', compact('envContent', 'envPath'));
    }

    public function envSettingStore(Request $request)
    {
        $role = \Spatie\Permission\Models\Role::find(Auth::user()->role_id);
        if (Auth::user()->role_id > 2 && (!$role || !$role->hasPermissionTo('env_setting'))) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        if(!env('USER_VERIFIED'))
            return redirect()->back()->with('not_permitted', 'This feature is disable for demo!');

        $request->validate([
            'env_content' => 'required|string',
        ]);

        $path = base_path('.env');

        if (!file_exists($path) || !is_writable($path)) {
            return redirect()->back()->with('not_permitted', '.env file is missing or not writable.');
        }

        if (is_file($path)) {
            @copy($path, $path . '.backup.' . date('Ymd_His'));
        }

        file_put_contents($path, rtrim($request->input('env_content')) . PHP_EOL);

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        return redirect()->route('setting.env')->with('message', 'Environment file saved. Run config:clear on the server if values do not apply immediately.');
    }

    public function generalSettingStore(Request $request)
    {
        if(!env('USER_VERIFIED'))
            return redirect()->back()->with('not_permitted', 'This feature is disable for demo!');

        $this->validate($request, [
            'site_logo' => 'image|mimes:jpg,jpeg,png,gif|max:100000',
            'email_header' => 'image|mimes:jpg,jpeg,png,gif|max:100000',
            'email_footer' => 'image|mimes:jpg,jpeg,png,gif|max:100000',
            'email_water_mark' => 'image|mimes:jpg,jpeg,png,gif|max:100000',
        ]);

        $data = $request->except('site_logo');
        //return $data;
        //writting timezone info in .env file
        $path = '.env';
        $searchArray = array('APP_TIMEZONE='.env('APP_TIMEZONE'));
        $replaceArray = array('APP_TIMEZONE='.$data['timezone']);

//        file_put_contents($path, str_replace($searchArray, $replaceArray, file_get_contents($path)));

        $general_setting = GeneralSetting::latest()->first();
        $general_setting->id = 1;
        $general_setting->site_title = $data['site_title'];
        // Always mirror laravel-app/VERSION (bumped on commit/push); ignore form edits.
        $general_setting->app_version = \App\Support\AppVersion::erp();
        $general_setting->currency = $data['currency'];
        $general_setting->currency_position = $data['currency_position'];
        $general_setting->staff_access = $data['staff_access'];
        $general_setting->date_format = $data['date_format'];
        $general_setting->developed_by = $data['developed_by'];
        $general_setting->invoice_format = $data['invoice_format'];
        $general_setting->state = $data['state'];
        $general_setting->unit = $data['unit'];
        $general_setting->category = $data['category'];
        $general_setting->profit_percentage = $data['profit_percentage'];
        $general_setting->letter_serial_no = $data['letter_serial_no'];
        $general_setting->commission = $data['commission'];
        $logoDir = base_path('public/logo');
        if (! is_dir($logoDir)) {
            if (! @mkdir($logoDir, 0775, true) && ! is_dir($logoDir)) {
                return redirect()->back()->with('not_permitted', 'Could not create public/logo directory. Check server permissions.');
            }
        }
        if (! is_writable($logoDir)) {
            @chmod($logoDir, 0775);
        }

        $logo = $request->site_logo;
        $email_header = $request->email_header;
        $email_footer = $request->email_footer;
        $email_water_mark = $request->email_water_mark;
        if ($logo) {
            $ext = pathinfo($logo->getClientOriginalName(), PATHINFO_EXTENSION);
            $logoName = date('Ymdhis').'.'.$ext;
            $logo->move($logoDir, $logoName);
            $general_setting->site_logo = $logoName;
        }
        if ($email_header) {
            $ext = pathinfo($email_header->getClientOriginalName(), PATHINFO_EXTENSION);
            $headerName = date('Ymdhi').'.'.$ext;
            $email_header->move($logoDir, $headerName);
            $general_setting->email_header = $headerName;
        }
        if ($email_footer) {
            $ext = pathinfo($email_footer->getClientOriginalName(), PATHINFO_EXTENSION);
            $footerName = date('Ymdis').'.'.$ext;
            $email_footer->move($logoDir, $footerName);
            $general_setting->email_footer = $footerName;
        }
        if ($email_water_mark) {
            $ext = pathinfo($email_water_mark->getClientOriginalName(), PATHINFO_EXTENSION);
            $waterMarkName = date('Ymdhs').'.'.$ext;
            $email_water_mark->move($logoDir, $waterMarkName);
            $general_setting->email_water_mark = $waterMarkName;
        }
        $general_setting->save();
        return redirect()->back()->with('message', 'Data updated successfully');
    }

    public function rewardPointSetting()
    {
        $lims_reward_point_setting_data = RewardPointSetting::latest()->first();
        return view('setting.reward_point_setting', compact('lims_reward_point_setting_data'));
    }

    public function rewardPointSettingStore(Request $request)
    {
        $data = $request->all();
        if(isset($data['is_active']))
            $data['is_active'] = true;
        else
            $data['is_active'] = false;
        RewardPointSetting::latest()->first()->update($data);
        return redirect()->back()->with('message', 'Reward point setting updated successfully');
    }

    public function backup()
    {
        if(!env('USER_VERIFIED'))
            return redirect()->back()->with('not_permitted', 'This feature is disable for demo!');

        // Database configuration
        $host = env('DB_HOST');
        $username = env('DB_USERNAME');
        $password = env('DB_PASSWORD');
        $database_name = env('DB_DATABASE');

        // Get connection object and set the charset
        $conn = mysqli_connect($host, $username, $password, $database_name);
        $conn->set_charset("utf8");


        // Get All Table Names From the Database
        $tables = array();
        $sql = "SHOW TABLES";
        $result = mysqli_query($conn, $sql);

        while ($row = mysqli_fetch_row($result)) {
            $tables[] = $row[0];
        }

        $sqlScript = "";
        foreach ($tables as $table) {

            // Prepare SQLscript for creating table structure
            $query = "SHOW CREATE TABLE $table";
            $result = mysqli_query($conn, $query);
            $row = mysqli_fetch_row($result);

            $sqlScript .= "\n\n" . $row[1] . ";\n\n";


            $query = "SELECT * FROM $table";
            $result = mysqli_query($conn, $query);

            $columnCount = mysqli_num_fields($result);

            // Prepare SQLscript for dumping data for each table
            for ($i = 0; $i < $columnCount; $i ++) {
                while ($row = mysqli_fetch_row($result)) {
                    $sqlScript .= "INSERT INTO $table VALUES(";
                    for ($j = 0; $j < $columnCount; $j ++) {
                        $row[$j] = $row[$j];

                        if (isset($row[$j])) {
                            $sqlScript .= '"' . $row[$j] . '"';
                        } else {
                            $sqlScript .= '""';
                        }
                        if ($j < ($columnCount - 1)) {
                            $sqlScript .= ',';
                        }
                    }
                    $sqlScript .= ");\n";
                }
            }

            $sqlScript .= "\n";
        }

        if(!empty($sqlScript))
        {
            // Save the SQL script to a backup file
            $backup_file_name = public_path().'/'.$database_name . '_backup_' . time() . '.sql';
            //return $backup_file_name;
            $fileHandler = fopen($backup_file_name, 'w+');
            $number_of_lines = fwrite($fileHandler, $sqlScript);
            fclose($fileHandler);

            $zip = new ZipArchive();
            $zipFileName = $database_name . '_backup_' . time() . '.zip';
            $zip->open(public_path() . '/' . $zipFileName, ZipArchive::CREATE);
            $zip->addFile($backup_file_name, $database_name . '_backup_' . time() . '.sql');
            $zip->close();

            // Download the SQL backup file to the browser
            /*header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=' . basename($backup_file_name));
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($backup_file_name));
            ob_clean();
            flush();
            readfile($backup_file_name);
            exec('rm ' . $backup_file_name); */
        }
        return redirect('public/' . $zipFileName);
    }

    public function changeTheme($theme)
    {
        $lims_general_setting_data = GeneralSetting::latest()->first();
        $lims_general_setting_data->theme = $theme;
        $lims_general_setting_data->save();
    }

    public function mailSetting()
    {
        return view('setting.mail_setting');
    }

    public function mailSettingStore(Request $request)
    {
        if(!env('USER_VERIFIED'))
            return redirect()->back()->with('not_permitted', 'This feature is disable for demo!');

        $data = $request->all();
        //writting mail info in .env file
        $path = '.env';
        $searchArray = array('MAIL_HOST="'.env('MAIL_HOST').'"', 'MAIL_PORT='.env('MAIL_PORT'), 'MAIL_FROM_ADDRESS="'.env('MAIL_FROM_ADDRESS').'"', 'MAIL_FROM_NAME="'.env('MAIL_FROM_NAME').'"', 'MAIL_USERNAME="'.env('MAIL_USERNAME').'"', 'MAIL_PASSWORD="'.env('MAIL_PASSWORD').'"', 'MAIL_ENCRYPTION="'.env('MAIL_ENCRYPTION').'"');
        //return $searchArray;

        $replaceArray = array('MAIL_HOST="'.$data['mail_host'].'"', 'MAIL_PORT='.$data['port'], 'MAIL_FROM_ADDRESS="'.$data['mail_address'].'"', 'MAIL_FROM_NAME="'.$data['mail_name'].'"', 'MAIL_USERNAME="'.$data['mail_address'].'"', 'MAIL_PASSWORD="'.$data['password'].'"', 'MAIL_ENCRYPTION="'.$data['encryption'].'"');

        file_put_contents($path, str_replace($searchArray, $replaceArray, file_get_contents($path)));

        return redirect()->back()->with('message', 'Data updated successfully');
    }

    public function smsSetting()
    {
        return redirect()->route('setting.messaging');
    }

    public function smsSettingStore(Request $request)
    {
        return redirect()->route('setting.messaging');
    }

    public function messagingSetting()
    {
        $role = \Spatie\Permission\Models\Role::find(Auth::user()->role_id);
        if (Auth::user()->role_id > 2 && (!$role || (!$role->hasPermissionTo('sms_setting') && !$role->hasPermissionTo('env_setting')))) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $bool = function ($key, $default = true) {
            $raw = EnvFile::get($key, $default ? 'true' : 'false');
            return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
        };

        return view('setting.messaging_setting', [
            'whatsappEnabled' => $bool('MESSAGING_WHATSAPP_ENABLED', true),
            'smsEnabled' => $bool('MESSAGING_SMS_ENABLED', true),
            'whatsappService' => strtoupper((string) EnvFile::get('WHATSAPP_SERVICE', 'TWILIO')),
            'defaultCountryCode' => EnvFile::get('WHATSAPP_DEFAULT_COUNTRY_CODE', '237'),
            'companyName' => EnvFile::get('COMPANY_NAME', 'Beyond Enterprise'),
            'twilioFallback' => $bool('WHATSAPP_TWILIO_FALLBACK_WASENDER', false),
            'wasenderApiKey' => EnvFile::get('WASENDER_API_KEY', ''),
            'wasenderSessionId' => EnvFile::get('WASENDER_SESSION_ID', ''),
            'wasenderBaseUrl' => EnvFile::get('WASENDER_BASE_URL', 'https://wasenderapi.com/api'),
            'wasenderMinInterval' => EnvFile::get('WASENDER_MIN_SEND_INTERVAL_MS', '6000'),
            'wasenderDocDelay' => EnvFile::get('WASENDER_TEXT_TO_DOCUMENT_DELAY_MS', '6000'),
            'twilioSid' => EnvFile::get('TWILIO_SID', EnvFile::get('ACCOUNT_SID', '')),
            'twilioAuthToken' => EnvFile::get('TWILIO_AUTH_TOKEN', EnvFile::get('AUTH_TOKEN', '')),
            'twilioWhatsappFrom' => EnvFile::get('TWILIO_WHATSAPP_FROM', ''),
            'contentSidAdmission' => EnvFile::get(
                'TWILIO_WHATSAPP_CONTENT_SID_ADMISSION',
                'HX47150e179fdbab79738d060fb0ac6415'
            ),
            'contentSidOtp' => EnvFile::get('TWILIO_WHATSAPP_CONTENT_SID_OTP', ''),
            'contentSidStatus' => EnvFile::get(
                'TWILIO_WHATSAPP_CONTENT_SID_STATUS',
                'HX47150e179fdbab79738d060fb0ac6415'
            ),
            'smsGateway' => strtolower((string) EnvFile::get('SMS_GATEWAY', 'twilio')),
            'accountSid' => EnvFile::get('ACCOUNT_SID', EnvFile::get('TWILIO_SID', '')),
            'authToken' => EnvFile::get('AUTH_TOKEN', EnvFile::get('TWILIO_AUTH_TOKEN', '')),
            'twilioNumber' => EnvFile::get('TWILIO_NUMBER', EnvFile::get('Twilio_Number', '')),
            'clickatellApiKey' => EnvFile::get('CLICKATELL_API_KEY', ''),
        ]);
    }

    public function messagingSettingStore(Request $request)
    {
        $role = \Spatie\Permission\Models\Role::find(Auth::user()->role_id);
        if (Auth::user()->role_id > 2 && (!$role || (!$role->hasPermissionTo('sms_setting') && !$role->hasPermissionTo('env_setting')))) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        if (! env('USER_VERIFIED')) {
            return redirect()->back()->with('not_permitted', 'This feature is disable for demo!');
        }

        $whatsappService = strtoupper((string) $request->input('whatsapp_service', 'TWILIO'));
        if (! in_array($whatsappService, ['WASENDER', 'TWILIO'], true)) {
            $whatsappService = 'TWILIO';
        }

        $smsGateway = strtolower((string) $request->input('sms_gateway', 'twilio'));
        if (! in_array($smsGateway, ['twilio', 'clickatell'], true)) {
            $smsGateway = 'twilio';
        }

        $accountSid = trim((string) $request->input('account_sid', ''));
        $authToken = trim((string) $request->input('auth_token', ''));
        $twilioSid = trim((string) $request->input('twilio_sid', ''));
        $twilioAuthToken = trim((string) $request->input('twilio_auth_token', ''));

        // Keep SMS and WhatsApp Twilio account keys in sync when one side is blank.
        if ($twilioSid === '' && $accountSid !== '') {
            $twilioSid = $accountSid;
        }
        if ($accountSid === '' && $twilioSid !== '') {
            $accountSid = $twilioSid;
        }
        if ($twilioAuthToken === '' && $authToken !== '') {
            $twilioAuthToken = $authToken;
        }
        if ($authToken === '' && $twilioAuthToken !== '') {
            $authToken = $twilioAuthToken;
        }

        $beyondNoticeSid = 'HX47150e179fdbab79738d060fb0ac6415';
        $admissionSid = trim((string) $request->input('twilio_content_sid_admission', $beyondNoticeSid));
        if ($admissionSid === '') {
            $admissionSid = $beyondNoticeSid;
        }
        $statusSid = trim((string) $request->input('twilio_content_sid_status', $beyondNoticeSid));
        if ($statusSid === '') {
            $statusSid = $beyondNoticeSid;
        }

        $pairs = [
            'MESSAGING_WHATSAPP_ENABLED' => $request->input('whatsapp_enabled') === 'true' ? 'true' : 'false',
            'MESSAGING_SMS_ENABLED' => $request->input('sms_enabled') === 'true' ? 'true' : 'false',
            'WHATSAPP_SERVICE' => $whatsappService,
            'WHATSAPP_DEFAULT_COUNTRY_CODE' => trim((string) $request->input('whatsapp_default_country_code', '237')),
            'COMPANY_NAME' => trim((string) $request->input('company_name', 'Beyond Enterprise')),
            'WHATSAPP_TWILIO_FALLBACK_WASENDER' => $request->input('twilio_fallback_wasender') === 'true' ? 'true' : 'false',
            'WASENDER_API_KEY' => trim((string) $request->input('wasender_api_key', '')),
            'WASENDER_SESSION_ID' => trim((string) $request->input('wasender_session_id', '')),
            'WASENDER_BASE_URL' => trim((string) $request->input('wasender_base_url', 'https://wasenderapi.com/api')),
            'WASENDER_MIN_SEND_INTERVAL_MS' => (string) (int) $request->input('wasender_min_send_interval_ms', 6000),
            'WASENDER_TEXT_TO_DOCUMENT_DELAY_MS' => (string) (int) $request->input('wasender_text_to_document_delay_ms', 6000),
            'TWILIO_SID' => $twilioSid,
            'TWILIO_AUTH_TOKEN' => $twilioAuthToken,
            'TWILIO_WHATSAPP_FROM' => trim((string) $request->input('twilio_whatsapp_from', '')),
            'TWILIO_WHATSAPP_CONTENT_SID_ADMISSION' => $admissionSid,
            'TWILIO_WHATSAPP_CONTENT_SID_OTP' => trim((string) $request->input('twilio_content_sid_otp', '')),
            'TWILIO_WHATSAPP_CONTENT_SID_STATUS' => $statusSid,
            'SMS_GATEWAY' => $smsGateway,
            'ACCOUNT_SID' => $accountSid,
            'AUTH_TOKEN' => $authToken,
            'TWILIO_NUMBER' => trim((string) $request->input('twilio_number', '')),
            'CLICKATELL_API_KEY' => trim((string) $request->input('clickatell_api_key', '')),
        ];

        if (! EnvFile::upsert($pairs)) {
            return redirect()->back()->with('not_permitted', '.env file is missing or not writable.');
        }

        try {
            Artisan::call('config:clear');
        } catch (\Throwable $e) {
            // ignore
        }

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        return redirect()->route('setting.messaging')->with('message', 'Messaging settings saved successfully.');
    }

    public function createSms()
    {
        $lims_customer_list = Customer::where('is_active', true)->get();
        return view('setting.create_sms', compact('lims_customer_list'));
    }

    public function sendSms(Request $request)
    {
        $data = $request->all();
        $numbers = explode(',', $data['mobile']);
        $router = app(NotificationRouter::class);

        if (! $router->smsEnabled()) {
            return redirect()->back()->with('not_permitted', 'SMS sending is disabled in <a href="'.route('setting.messaging').'">Messaging Settings</a>.');
        }

        $failed = [];
        foreach ($numbers as $number) {
            $number = trim($number);
            if ($number === '') {
                continue;
            }
            $result = $router->sendSms($number, $data['message']);
            if (empty($result['success'])) {
                $failed[] = $number.': '.($result['error'] ?? 'failed');
            }
        }

        if (! empty($failed)) {
            return redirect()->back()->with(
                'not_permitted',
                'Some SMS failed. Configure <a href="'.route('setting.messaging').'">Messaging Settings</a>. '.implode('; ', $failed)
            );
        }

        return redirect()->back()->with('message', 'SMS sent successfully');
    }

    public function hrmSetting()
    {
        $lims_hrm_setting_data = HrmSetting::latest()->first();
        return view('setting.hrm_setting', compact('lims_hrm_setting_data'));
    }

    public function hrmSettingStore(Request $request)
    {
        $data = $request->all();
        $lims_hrm_setting_data = HrmSetting::firstOrNew(['id' => 1]);
        $lims_hrm_setting_data->checkin = $data['checkin'];
        $lims_hrm_setting_data->checkout = $data['checkout'];
        $lims_hrm_setting_data->save();
        return redirect()->back()->with('message', 'Data updated successfully');

    }
    public function posSetting()
    {
    	$lims_customer_list = Customer::where('is_active', true)->get();
        $lims_warehouse_list = Warehouse::where('is_active', true)->get();
        $lims_biller_list = Biller::where('is_active', true)->get();
        $lims_pos_setting_data = PosSetting::latest()->first();
        $lims_account_all = Account::where('is_active', true)->get();
        $lims_account_default = Account::where('is_default', true)->first();
        $lims_account_default_debit = Account::where('is_default_debit', true)->first();

    	return view('setting.pos_setting', compact('lims_customer_list', 'lims_warehouse_list', 'lims_biller_list', 'lims_pos_setting_data', 'lims_account_all', 'lims_account_default', 'lims_account_default_debit'));
    }

    public function posSettingStore(Request $request)
    {
        if(!env('USER_VERIFIED'))
            return redirect()->back()->with('not_permitted', 'This feature is disable for demo!');

    	$data = $request->all();
        //writting paypal info in .env file
        $path = '.env';
        $searchArray = array('PAYPAL_LIVE_API_USERNAME='.env('PAYPAL_LIVE_API_USERNAME'), 'PAYPAL_LIVE_API_PASSWORD='.env('PAYPAL_LIVE_API_PASSWORD'), 'PAYPAL_LIVE_API_SECRET='.env('PAYPAL_LIVE_API_SECRET') );

        $replaceArray = array('PAYPAL_LIVE_API_USERNAME='.$data['paypal_username'], 'PAYPAL_LIVE_API_PASSWORD='.$data['paypal_password'], 'PAYPAL_LIVE_API_SECRET='.$data['paypal_signature'] );

        file_put_contents($path, str_replace($searchArray, $replaceArray, file_get_contents($path)));

    	$pos_setting = PosSetting::firstOrNew(['id' => 1]);
    	$pos_setting->id = 1;
    	$pos_setting->customer_id = $data['customer_id'];
    	$pos_setting->warehouse_id = $data['warehouse_id'];
    	$pos_setting->biller_id = $data['biller_id'];
    	$pos_setting->product_number = $data['product_number'];
    	$pos_setting->stripe_public_key = $data['stripe_public_key'];
    	$pos_setting->stripe_secret_key = $data['stripe_secret_key'];

        $lims_account_data = Account::where('is_default', true)->first();
        $lims_account_data->is_default = false;
        $lims_account_data->save();

        $lims_account_data = Account::find($data['account_id']);
        $lims_account_data->is_default = true;
        $lims_account_data->save();

        $lims_account_data = Account::where('is_default_debit', true)->first();
        $lims_account_data->is_default_debit = false;
        $lims_account_data->save();

        $lims_account_data = Account::find($data['debit_account_id']);
        $lims_account_data->is_default_debit = true;
        $lims_account_data->save();

        if(!isset($data['keybord_active']))
            $pos_setting->keybord_active = false;
        else
            $pos_setting->keybord_active = true;
    	$pos_setting->save();
    	return redirect()->back()->with('message', 'POS setting updated successfully');
    }
}

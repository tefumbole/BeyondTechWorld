<?php

namespace App\Http\Controllers;

use App\BookingProduct;
use App\Customer;
use App\GeneralSetting;
use App\StockDuration;
use App\User;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;

class Controller extends BaseController
{


    private $user;

    public function __construct() {


        $this->middleware(function ($request, $next) {
            $this->user = \Illuminate\Support\Facades\Auth::user();
            if ($this->user && $this->user->role_id != 5) {
                $role = Role::find($this->user->role_id);
                $permissions = Role::findByName($role->name)->permissions;

                foreach ($permissions as $permission) {
                    $all_permission[] = $permission->name;
                }
                View::share ('all_permission', $all_permission);
            }
            return $next($request);
        });
    }

    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    private function whatsappConfig($key, $default = null)
    {
        return config('services.whatsapp.' . $key, $default);
    }

    private function whatsappServiceName()
    {
        return strtoupper((string) $this->whatsappConfig('service', 'WASENDER'));
    }

    private function usesWasender()
    {
        $service = $this->whatsappServiceName();
        if (in_array($service, ['ULTRAMSG', 'ULTRA'], true)) {
            return false;
        }

        return $service === 'WASENDER' || !empty($this->whatsappConfig('wasender_api_key'));
    }

    private function assertWasenderConfigured()
    {
        if (empty($this->whatsappConfig('wasender_api_key'))) {
            throw new \Exception('WasenderAPI is not configured. Set WASENDER_API_KEY in Settings > .env Settings.');
        }

        if (empty($this->whatsappConfig('wasender_session_id'))) {
            throw new \Exception('WasenderAPI session is not configured. Set WASENDER_SESSION_ID (from Wasender dashboard → Sessions) in Settings > .env Settings.');
        }
    }

    private function wasenderBaseUrl()
    {
        return rtrim((string) $this->whatsappConfig('wasender_base_url', 'https://wasenderapi.com/api'), '/');
    }

    private function wasenderHttpHeaders()
    {
        return [
            'Authorization: Bearer ' . $this->whatsappConfig('wasender_api_key'),
            'Content-Type: application/json',
            'Accept: application/json',
        ];
    }

    private function wasenderApiRequest($method, $path, $payload = null)
    {
        $curl = curl_init();
        $options = [
            CURLOPT_URL => $this->wasenderBaseUrl() . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $this->wasenderHttpHeaders(),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
        ];

        if (strtoupper($method) === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($payload ?: []);
        }

        curl_setopt_array($curl, $options);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($response === false || !empty($err)) {
            throw new \Exception('WasenderAPI request failed: ' . $err);
        }

        return json_decode($response, true);
    }

    private function wasenderRawRequest($path, $body, array $headers = [])
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->wasenderBaseUrl() . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => array_merge([
                'Authorization: Bearer ' . $this->whatsappConfig('wasender_api_key'),
                'Accept: application/json',
            ], $headers),
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($response === false || !empty($err)) {
            throw new \Exception('WasenderAPI upload failed: ' . $err);
        }

        $decoded = json_decode($response, true);

        return is_array($decoded) ? $decoded : ['raw' => $response];
    }

    protected function resolveWhatsAppDocumentUrl($path)
    {
        $path = realpath($path) ?: $path;

        if (!is_file($path)) {
            throw new \Exception('Document file not found.');
        }

        if ($this->usesWasender()) {
            return $this->wasenderUploadLocalFile($path);
        }

        $storagePublic = realpath(storage_path('app/public'));
        if ($storagePublic && strpos($path, $storagePublic) === 0) {
            $relative = 'storage/' . ltrim(str_replace('\\', '/', substr($path, strlen($storagePublic))), '/');
            return url($relative);
        }

        $publicRoot = realpath(public_path());
        if ($publicRoot && strpos($path, $publicRoot) === 0) {
            $relative = ltrim(str_replace('\\', '/', substr($path, strlen($publicRoot))), '/');
            return url($relative);
        }

        throw new \Exception('Could not build a public URL for the document.');
    }

    protected function wasenderUploadLocalFile($path)
    {
        $this->assertWasenderConfigured();

        if (!is_file($path)) {
            throw new \Exception('Document file not found for upload.');
        }

        $mime = function_exists('mime_content_type') ? @mime_content_type($path) : null;
        if (empty($mime)) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mime = $ext === 'pdf' ? 'application/pdf' : 'application/octet-stream';
        }

        $decoded = $this->wasenderRawRequest('/upload', file_get_contents($path), [
            'Content-Type: ' . $mime,
        ]);

        if (!empty($decoded['publicUrl'])) {
            return $decoded['publicUrl'];
        }

        $message = $decoded['message'] ?? $decoded['error'] ?? 'Wasender upload did not return a public URL.';
        throw new \Exception($message);
    }

    protected function assertWasenderAttachmentSuccess($result, $phone = null)
    {
        if (!is_array($result)) {
            return;
        }

        if (isset($result['success']) && $result['success'] !== true) {
            $message = $result['message'] ?? $result['error'] ?? 'Wasender rejected the attachment';
            if ($phone && (stripos($message, 'JID') !== false || stripos($message, 'not exist on WhatsApp') !== false)) {
                $message = 'This phone number is not on WhatsApp or is invalid (' . $phone . '). Update the customer phone number and try again.';
            }
            throw new \Exception($message);
        }
    }

    private static $lastWhatsAppSendAt = 0.0;

    private static $lastWhatsAppSendType = null;

    protected function whatsappCompanyName()
    {
        $name = trim((string) $this->whatsappConfig('company_name', ''));
        if ($name !== '') {
            return $name;
        }

        $general = GeneralSetting::first();

        return $general->site_title ?? config('app.name', 'Application');
    }

    private function throttleWhatsAppSend()
    {
        $intervalMs = max(1000, (int) $this->whatsappConfig('min_send_interval_ms', 6000));
        $interval = $intervalMs / 1000;
        $now = microtime(true);

        if (self::$lastWhatsAppSendAt > 0) {
            $wait = $interval - ($now - self::$lastWhatsAppSendAt);
            if ($wait > 0) {
                usleep((int) round($wait * 1000000));
            }
        }

        self::$lastWhatsAppSendAt = microtime(true);
    }

    private function delayWasenderTextToDocument()
    {
        if (self::$lastWhatsAppSendType !== 'text') {
            return;
        }

        $delayMs = max(0, (int) $this->whatsappConfig('text_to_document_delay_ms', 6000));
        if ($delayMs > 0) {
            usleep($delayMs * 1000);
            self::$lastWhatsAppSendAt = microtime(true);
        }
    }

    protected function normalizeWhatsAppPhone($number)
    {
        try {
            return \App\Support\WhatsAppPhone::forWasender($number);
        } catch (\InvalidArgumentException $e) {
            throw new \Exception($e->getMessage());
        }
    }

    private function sendWasenderTextMessage($number, $msg)
    {
        $this->throttleWhatsAppSend();
        $this->assertWasenderConfigured();

        $payload = [
            'to' => $this->normalizeWhatsAppPhone($number),
            'text' => $msg,
        ];

        $decoded = $this->wasenderApiRequest('POST', '/send-message', $payload);

        if (is_array($decoded) && isset($decoded['success']) && $decoded['success'] !== true) {
            $message = $decoded['message'] ?? $decoded['error'] ?? 'WasenderAPI rejected the message';
            if (stripos($message, 'JID') !== false || stripos($message, 'not exist on WhatsApp') !== false) {
                $message = 'This phone number is not on WhatsApp or is invalid (' . $payload['to'] . '). Open the customer record, set the full mobile number (e.g. 675321739), and ensure it is registered on WhatsApp.';
            }
            throw new \Exception($message);
        }

        self::$lastWhatsAppSendType = 'text';

        return json_encode($decoded);
    }

    public function detectFileType($fileUrl)
    {
        $ext = strtolower(pathinfo(parse_url($fileUrl, PHP_URL_PATH), PATHINFO_EXTENSION));

        $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
        $videoExts = ['mp4', 'mov', 'avi', 'mkv'];
        $audioExts = ['mp3', 'ogg', 'wav', 'aac'];
        $docExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'];

        if (in_array($ext, $imageExts)) {
            return 'image';
        }
        if (in_array($ext, $videoExts)) {
            return 'video';
        }
        if (in_array($ext, $audioExts)) {
            return 'audio';
        }
        if (in_array($ext, $docExts)) {
            return 'document';
        }

        return 'document';
    }

    public function wasenderAttachment($path, $lims_customer_data, $wa_path, $fileName)
    {
        $this->delayWasenderTextToDocument();
        $this->throttleWhatsAppSend();
        $this->assertWasenderConfigured();

        if (empty($wa_path)) {
            $wa_path = $this->resolveWhatsAppDocumentUrl($path);
        }

        $type = $this->detectFileType($path);
        $phone = $this->normalizeWhatsAppPhone($lims_customer_data->phone_number ?? $lims_customer_data->phone);
        $payload = [
            'to' => $phone,
            'fileName' => $fileName,
            'text' => $fileName,
        ];

        switch ($type) {
            case 'image':
                $payload['imageUrl'] = $wa_path;
                break;
            case 'video':
                $payload['videoUrl'] = $wa_path;
                break;
            case 'audio':
                $payload['audioUrl'] = $wa_path;
                break;
            default:
                $payload['documentUrl'] = $wa_path;
                break;
        }

        $result = $this->wasenderApiRequest('POST', '/send-message', $payload);
        $this->assertWasenderAttachmentSuccess($result, $phone);
        self::$lastWhatsAppSendType = 'document';

        return $result;
    }

    private function getUltraMsgConfig()
    {
        $instance = $this->whatsappConfig('ultramsg_instance');
        $token = $this->whatsappConfig('ultramsg_token');

        if (empty($instance) || empty($token)) {
            throw new \Exception('ULTRAMSG credentials are missing');
        }

        return [$instance, $token];
    }

    private function getCustomerPhoneNumber($customer)
    {
        $phone = $customer->phone_number ?? $customer->phone ?? null;
        if (empty($phone)) {
            throw new \Exception('Customer phone number is missing');
        }

        return $phone;
    }

    private function sendUltraMsgDocumentRequest($path, $to, $filename)
    {
        if (!file_exists($path)) {
            throw new \Exception('Attachment file not found');
        }

        list($instance, $token) = $this->getUltraMsgConfig();
        $data = file_get_contents($path);
        if ($data === false) {
            throw new \Exception('Unable to read attachment file');
        }

        $img_base64 = urlencode(base64_encode($data));
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.ultramsg.com/$instance/messages/document",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "token=$token&to=$to&document=$img_base64&filename=$filename",
            CURLOPT_HTTPHEADER => array(
                "content-type: application/x-www-form-urlencoded"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($response === false || !empty($err)) {
            throw new \Exception('WhatsApp API request failed');
        }

        $decodedResponse = json_decode($response, true);
        if (is_array($decodedResponse)) {
            if (!empty($decodedResponse['error'])) {
                throw new \Exception('WhatsApp API returned an error');
            }
            if (isset($decodedResponse['sent']) && (string) $decodedResponse['sent'] !== 'true') {
                throw new \Exception('WhatsApp API rejected the document');
            }
            if (isset($decodedResponse['status']) && in_array($decodedResponse['status'], array('error', 'failed'))) {
                throw new \Exception('WhatsApp API returned failed status');
            }
        }

        return true;
    }

    public function mobileMoneyRequestLink($token, $amount, $route, $patient_id, $number){

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://www.campay.net/api/get_payment_link/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>'{
                "amount": "'.$amount.'",
                "from": "'.$number.'",
                "currency": "XAF",
                "external_reference": "'.$patient_id.'",
                "redirect_url": "'.$route.'",
                "payment_options":"MOMO,CARD",
                "failure_redirect_url": "'.$route.'"
            }',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Token ' . $token,
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);
        $response_decode = json_decode($response, true);

        curl_close($curl);


        if($response_decode && isset($response_decode['link'])) {
            return $response_decode['link'];
        }
        return false;
    }

    public function mobileMoneyOrderRequestLink($token, $amount, $route, $orders, $failure_route, $number){

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://www.campay.net/api/get_payment_link/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>'{
                "amount": "'.$amount.'",
                "from": "'.$number.'",
                "currency": "XAF",
                "external_reference": "'.$failure_route.',' .$orders.'",
                "redirect_url": "'.$route.'",
                "payment_options":"MOMO,CARD",
                "failure_redirect_url": "'.$route.'"
            }',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Token ' . $token,
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);
        $response_decode = json_decode($response, true);

        curl_close($curl);

        if($response_decode && isset($response_decode['link'])) {
            return $response_decode['link'];
        }
        return false;
    }

    public function wpMessage($number, $msg){
        if ($this->usesWasender()) {
            $this->assertWasenderConfigured();
            return $this->sendWasenderTextMessage($number, $msg);
        }

        $this->throttleWhatsAppSend();

        $service = $this->whatsappServiceName();
        if ($service !== 'ULTRAMSG' && $service !== 'ULTRA') {
            throw new \Exception('WhatsApp messaging uses WasenderAPI. Set WHATSAPP_SERVICE=WASENDER, WASENDER_API_KEY, and WASENDER_SESSION_ID in your environment settings.');
        }

        list($instance, $token) = $this->getUltraMsgConfig();
        $params= [
            'token' => $token,
            'to' => $number,
            'body' => $msg
        ];
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.ultramsg.com/".$instance."/messages/chat",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_HTTPHEADER => array(
                "content-type: application/x-www-form-urlencoded"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($response === false || !empty($err)) {
            throw new \Exception('WhatsApp message sending failed');
        }
    }

    protected function sendWhatsAppToCustomer($customer, $msg)
    {
        $phone = $this->getCustomerPhoneNumber($customer);
        return $this->wpMessage($phone, $msg);
    }

    protected function sendWhatsAppToPhone($phone, $msg)
    {
        if (empty(trim((string) $phone))) {
            throw new \Exception('Phone number is missing');
        }

        return $this->wpMessage($phone, $msg);
    }

    protected function sendWhatsAppDocumentToCustomer($customer, $path, $filename, $publicUrl = null)
    {
        if ($this->usesWasender()) {
            $this->assertWasenderConfigured();
            if (empty($publicUrl)) {
                $publicUrl = $this->resolveWhatsAppDocumentUrl($path);
            }

            return $this->wasenderAttachment($path, $customer, $publicUrl, $filename);
        }

        $service = $this->whatsappServiceName();
        if ($service !== 'ULTRAMSG' && $service !== 'ULTRA') {
            throw new \Exception('WhatsApp attachments use WasenderAPI. Set WHATSAPP_SERVICE=WASENDER, WASENDER_API_KEY, and WASENDER_SESSION_ID in your environment settings.');
        }

        $phone = $this->getCustomerPhoneNumber($customer);
        return $this->sendUltraMsgDocumentRequest($path, $phone, $filename);
    }

    protected function sendWhatsAppDocumentToPhone($phone, $path, $filename, $publicUrl = null)
    {
        if ($this->usesWasender()) {
            $this->assertWasenderConfigured();
            if (empty($publicUrl)) {
                $publicUrl = $this->resolveWhatsAppDocumentUrl($path);
            }

            $recipient = (object) ['phone_number' => $phone];
            return $this->wasenderAttachment($path, $recipient, $publicUrl, $filename);
        }

        $service = $this->whatsappServiceName();
        if ($service !== 'ULTRAMSG' && $service !== 'ULTRA') {
            throw new \Exception('WhatsApp attachments use WasenderAPI. Set WHATSAPP_SERVICE=WASENDER, WASENDER_API_KEY, and WASENDER_SESSION_ID in your environment settings.');
        }

        return $this->sendUltraMsgDocumentRequest($path, $phone, $filename);
    }


    public function wpAttachMessage($path, $number, $filename='compile_result.pdf', $wa_path = null){
        if ($this->usesWasender()) {
            $this->assertWasenderConfigured();
            if (empty($wa_path)) {
                $wa_path = $this->resolveWhatsAppDocumentUrl($path);
            }
            $customer = (object) ['phone_number' => $number];
            return $this->wasenderAttachment($path, $customer, $wa_path, $filename);
        }

        $service = $this->whatsappServiceName();
        if ($service !== 'ULTRAMSG' && $service !== 'ULTRA') {
            throw new \Exception('WhatsApp attachments use WasenderAPI. Set WHATSAPP_SERVICE=WASENDER, WASENDER_API_KEY, and WASENDER_SESSION_ID in your environment settings.');
        }

        return $this->sendUltraMsgDocumentRequest($path, $number, $filename);
    }


    public function wpPDFMessage($path, $lims_customer_data, $filename='invoice.pdf', $wa_path = null){
        if ($this->usesWasender()) {
            $this->assertWasenderConfigured();
            if (empty($wa_path)) {
                $wa_path = $this->resolveWhatsAppDocumentUrl($path);
            }

            $customerName = $lims_customer_data->name ?? 'Customer';
            $caption = \App\Support\WhatsAppMessage::statusBlock('📄', 'Document Attached')
                . \App\Support\WhatsAppMessage::greeting($customerName)
                . 'Please find your *' . $filename . '* attached.'
                . \App\Support\WhatsAppMessage::footer();

            try {
                $this->sendWasenderTextMessage(
                    $lims_customer_data->phone_number ?? $lims_customer_data->phone,
                    $caption
                );
            } catch (\Exception $e) {
            }

            return $this->wasenderAttachment($path, $lims_customer_data, $wa_path, $filename);
        }

        $service = $this->whatsappServiceName();
        if ($service !== 'ULTRAMSG' && $service !== 'ULTRA') {
            throw new \Exception('WhatsApp attachments use WasenderAPI. Set WHATSAPP_SERVICE=WASENDER, WASENDER_API_KEY, and WASENDER_SESSION_ID in your environment settings.');
        }

        $to = $this->getCustomerPhoneNumber($lims_customer_data);
        return $this->sendUltraMsgDocumentRequest($path, $to, $filename);
    }

    public function wpPDFAnnouncement($path, $lims_customer_data, $filename='invoice.pdf', $wa_path = null){
        if ($this->usesWasender()) {
            $this->assertWasenderConfigured();
            if (empty($wa_path)) {
                $wa_path = $this->resolveWhatsAppDocumentUrl($path);
            }
            return $this->wasenderAttachment($path, $lims_customer_data, $wa_path, $filename);
        }

        $service = $this->whatsappServiceName();
        if ($service !== 'ULTRAMSG' && $service !== 'ULTRA') {
            throw new \Exception('WhatsApp attachments use WasenderAPI. Set WHATSAPP_SERVICE=WASENDER, WASENDER_API_KEY, and WASENDER_SESSION_ID in your environment settings.');
        }

        $to = $this->getCustomerPhoneNumber($lims_customer_data);
        return $this->sendUltraMsgDocumentRequest($path, $to, $filename);
    }

    public function sendWhatsappMsgForPlacingOrderToBuyer($order){

        $general_setting = GeneralSetting::first();

        $msg = '*Subject:* Order Confirmation for '. $order->name . '\n\n';
        $msg .= 'Dear '. $order->name . '\n\n';
        $msg .= 'Thank you for choosing '.$general_setting->site_title.' as your preferred supplier. We are pleased to confirm the availability of the products requested.\n\n\n';

        $msg .= '*Order Details:*\n';
        $msg .= 'Order Number: '.$order->id.'\n';
        $msg .=  'Order Date: '.$order->created_at.'\n\n';

        if ($order->payment_method == 'COD') {
            $msg .= '*Note:* Your payment status is cash on delivery. Admin will approve order.\n\n';
        }

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

        return true;
    }

    public function sendWhatsappMsgForPlacingServiceToBuyer($order){

        $general_setting = GeneralSetting::first();

        $msg = '*Subject:* Service order Confirmation for '. $order->name . '\n\n';
        $msg .= 'Dear '. $order->name . '\n\n';
        $msg .= 'Thank you for choosing '.$general_setting->site_title.' as your preferred supplier. We are pleased to confirm the availability of the service requested.\n\n\n';

        $msg .= '*Service Order Details:*\n';
        $msg .= 'Order Number: '.$order->id.'\n';
        $msg .=  'Order Date: '.$order->created_at.'\n\n';

        if ($order->payment_method == 'COD') {
            $msg .= '*Note:* Your payment status is cash on delivery. Admin will approve order.\n\n';
        }

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

        return true;
    }

    public function sendWhatsappMsgForPlacingServiceToSaller($order){

        $general_setting = GeneralSetting::first();

        $msg = '*Subject:* Service order Confirmation for '. $order->name . '\n\n';
        $msg .= 'A new Service order is placed. \n\n';

        $msg .= '*Service Order Details:*\n';
        $msg .= 'Order Number: '.$order->id.'\n';
        $msg .=  'Order Date: '.$order->created_at.'\n\n';

        if ($order->payment_method == 'COD') {
            $msg .= '*Note:* This service order payment status is cash on delivery. Please have look and approve service order.\n\n';
        }

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
            $this->wpMessage(getenv('ADMIN_NUMBER'), $msg);
        }
        catch(\Exception $e){

        }

        return true;
    }


    public function sendWhatsappMsgMomoPaymentSuccess($number, $total)
    {
        $msg = '*Thank you for your Order,*  \n\n';
        $msg .= 'You have payed ' . $total;

        try{
            $this->wpMessage($number, $msg);
        }
        catch(\Exception $e){

        }

        return true;
    }

    public function sendWhatsappMsgForPlacingOrderToBuyerBooking($order){

        $general_setting = GeneralSetting::first();
        $customer = User::where('id', $order->user_id)->first();

        $msg = '*Subject:* Order Confirmation for '. $customer->name . '\n\n';
        $msg .= 'Dear '. $customer->name . '\n\n';
        $msg .= 'Thank you for choosing '.$general_setting->site_title.' as your preferred supplier. We are pleased to confirm the availability of the products requested.\n\n\n';

        $msg .= '*Order Details:*\n';
        $msg .= 'Order Number: '.$order->id.'\n';
        $msg .=  'Order Date: '.$order->created_at.'\n\n';

        if ($order->payment_method == 'COD') {
            $msg .= '*Note:* Your payment status is cash on delivery. Admin will approve order.\n\n';
        }

        $msg .= '*Product Detail:*\n';
        $bookingProducts = BookingProduct::with('product')->where('booking_id', $order->id)->get();
        foreach ($bookingProducts as $key => $product) {
            $msg .= $key+1 .') ['. $product->product->name . '] [' . $product->qty . '] x [' . $product->number_duration . '] x [ '. number_format($product->net_unit_price, 2) .'] = ['. number_format($product->qty * $product->number_duration * $product->net_unit_price, 2) .']\n';
            $msg .= "*Start* : " . $product->start . " *Return* : " . $product->end . '\n';
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
            $this->wpMessage($customer->phone, $msg);
        }
        catch(\Exception $e){

        }

        return true;
    }

    public function sendWhatsappMsgForPlacingOrderToAdminBooking($order){

        $general_setting = GeneralSetting::first();
        $customer = Customer::where('id', $order->customer_id)->first();

        $msg = '*Subject:* Order Confirmation for '. $customer->name . '\n\n';
        $msg .= 'Dear Admin \n\n';
        $msg .= 'You have received a booking order.\n\n\n';

        $msg .= '*Order Details:*\n';
        $msg .= 'Order Number: '.$order->id.'\n';
        $msg .=  'Order Date: '.$order->created_at.'\n\n';

        if ($order->payment_method == 'COD') {
            $msg .= '*Note:* Your payment status is cash on delivery. Admin will approve order.\n\n';
        }

        $msg .= '*Product Detail:*\n';
        $bookingProducts = BookingProduct::with('product')->where('booking_id', $order->id)->get();
        foreach ($bookingProducts as $key => $product) {
            $msg .= $key+1 .') ['. $product->product->name . '] [' . $product->qty . '] x [' . $product->number_duration . '] x [ '. number_format($product->net_unit_price, 2) .'] = ['. number_format($product->qty * $product->number_duration * $product->net_unit_price, 2) .']\n';
            $msg .= "*Start* : " . $product->start . " *Return* : " . $product->end . '\n';
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
            $this->wpMessage(getenv('ADMIN_NUMBER'), $msg);
        }
        catch(\Exception $e){

        }

        return true;
    }

    public function sendWhatsappMsgForPlacingOrderToSaller($order){

        $general_setting = GeneralSetting::first();
        $vendor = User::where('id', $order->vendor_id)->first();

        $msg = '*Subject:* Order Confirmation for '. $order->name . '\n\n';
        $msg .= 'Dear Seller \n\n';
        $msg .= '*Congrats* You have received an order from '. $order->name .'('.$order->phone.') of '. $order->grand_total . ' CFA' .'\n\n';

        $msg .= '*Order Details:*\n';
        $msg .= 'Order Number: '.$order->id.'\n';
        $msg .=  'Order Date: '.$order->created_at.'\n\n';

        if ($order->payment_method == 'COD') {
            $msg .= '*Note:* Payment status is cash on delivery. Please check and verify and approve order.\n\n';
        }

        $msg .= '*Product Detail:*\n';
        foreach ($order->orderProducts as $key => $product) {
            $msg .= $key+1 .') ['. $product->product->name . '] [' . $product->quantity . '] x [ '. number_format($product->price, 2) .'] = ['. number_format($product->sub_total, 2) .']\n';
        }

        $msg .= 'Total Amount: ' . number_format($order->grand_total, 2) . '\n\n';

        $msg .= '*Payment Information:*\n';
        $msg .= 'Payment Method: ' . $order->payment_method . '\n';
        $msg .= 'Delivery Information: ' . $order->address . '\n';

        $msg .= 'Best regards,\n';
        $msg .= @$general_setting->develoled_by. '\n';
        $msg .= $general_setting->site_title. '\n\n';
        $msg .= request()->getHost();

        try{
            $this->wpMessage($vendor->phone, $msg);
            $this->wpMessage(getenv('ADMIN_NUMBER'), $msg);
        }
        catch(\Exception $e){

        }

        return true;
    }

    public function sendWhatsappMsgMomoPaymentSuccessDonation($general_setting, $order, $total)
    {
        $user = User::select('name', 'id', 'phone')->find($order->user_id);

        $msg = '*Subject:* Donation Confirmation for '. $order->name . '\n\n';
        $msg .= '*Thank you for your Donation,*  \n\n';
        $msg .= 'You have payed *' . $total . '* CFA' .'\n\n';

        $msg .= 'Best regards,\n';
        $msg .= @$general_setting->develoled_by. '\n';
        $msg .= @$general_setting->site_title. '\n\n';

        $msg .= request()->getHost();

        try{
            $this->wpMessage($user->phone, $msg);
        }
        catch(\Exception $e){

        }

        return true;
    }

    public function sendWhatsappMsgMomoPaymentSuccessDonationSeller($general_setting, $order)
    {
        $user = User::select('name', 'id', 'phone')->find($order->vendor_id);

        $msg = '*Subject:* Donation Confirmation for '. $order->name . '\n\n';

        $msg .= 'Dear '. $user->name .' \n\n';
        $msg .= '*Congrats* You have received a donation from '. $order->name .'('.$order->phone.') of *'. $order->grand_total . '* CFA' .'\n\n';

        $msg .= 'Best regards,\n';
        $msg .= @$general_setting->develoled_by. '\n';
        $msg .= @$general_setting->site_title. '\n\n';

        $msg .= request()->getHost();

        try{
            $this->wpMessage($user->phone, $msg);
        }
        catch(\Exception $e){

        }

        $msg = '*Subject:* Donation Confirmation for '. $order->name . '\n\n';

        $msg .= 'Dear Admin \n\n';
        $msg .= '*Congrats* Your Vendor ('. $user->name .') have received a donation from '. $order->name .'('.$order->phone.') of *'. $order->grand_total . '* CFA' .'\n\n';

        $msg .= 'Best regards,\n';
        $msg .= @$general_setting->develoled_by. '\n';
        $msg .= @$general_setting->site_title. '\n\n';

        $msg .= request()->getHost();

        try{
            $this->wpMessage(getenv('ADMIN_NUMBER'), $msg);
        }
        catch(\Exception $e){

        }

        return true;
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


    public function sendWhatsappMsgForAccount($user, $password){

        $msg = '*Congrats:* Your account has been created' . '\n\n';
        $msg .= '*User name:* '. $user->name . '\n\n';
        $msg .= '*Phone number:* '. $user->phone . '\n\n';
        $msg .= '*Password:* '. $password . '\n\n';
        $msg .= request()->getHost() . '\n\n';


        try{
            $this->wpMessage($user->phone, $msg);
        }
        catch(\Exception $e){

        }

        return true;
    }

    public function sendWhatsappMsgForVendorAccount($user, $password){

        $general_setting = GeneralSetting::first();

        $msg = '*Congrats:* Your account has been created' . '\n\n';
        $msg .= '*Vendor name:* '. $user->name . '\n\n';
        $msg .= '*Phone number:* '. $user->phone . '\n\n';
        $msg .= '*Password:* '. $password . '\n\n';
        $msg .= '\n\n';
        $msg .= '*Note:* Your Account is under review, Admin will review and approve your account soon. After approval you can sale your products. On every sale you will charged ' .$user->commission. '% \n\n';
        $msg .= request()->getHost() . '\n\n';


        try{
            $this->wpMessage($user->phone, $msg);
        }
        catch(\Exception $e){

        }

        return true;
    }

    public function sendWhatsappMsgForVendorAccountToAdmin($user, $password){

        $general_setting = GeneralSetting::first();

        $msg = '*Congrats:* A new account has been created' . '\n\n';
        $msg .= '*Vendor name:* '. $user->name . '\n\n';
        $msg .= '*Phone number:* '. $user->phone . '\n\n';
        $msg .= '*Password:* '. $password . '\n\n';
        $msg .= '\n\n';
        $msg .= '*Note:* Please review and active this shop, so vendor can sale his products. \n\n';
        $msg .= request()->getHost() . '\n\n';


        try{
            $this->wpMessage(getenv('ADMIN_NUMBER'), $msg);
        }
        catch(\Exception $e){

        }

        return true;
    }

    public function stockDurationSave($id, $qty) {
        $stockDuration = StockDuration::where([
            'product_id' => $id,
            'restock' => null
        ])->first();
        if ($qty == 0.0) {
            if(!$stockDuration) {
                StockDuration::create([
                    'product_id' => $id,
                    'out_of_stock' => date('Y-m-d')
                ]);
            }
        } else {
            if ($stockDuration) {
                $stockDuration->update(['restock' => date('Y-m-d')]);
            }
        }
    }

}

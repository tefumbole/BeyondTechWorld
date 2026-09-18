<?php

namespace App\Http\Controllers;

use App\BirthdayFlyer;
use App\Services\BirthdayFlyerService;
use App\Services\BeyondWasenderService;
use App\Support\CountryDialCodes;
use App\Support\WhatsAppPhone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BirthdayFlyerController extends Controller
{
    protected $flyers;
    protected $whatsapp;

    public function __construct(BirthdayFlyerService $flyers, BeyondWasenderService $whatsapp)
    {
        $this->flyers = $flyers;
        $this->whatsapp = $whatsapp;
    }

    public function index()
    {
        $template = $this->flyers->randomTemplate();

        return view('beyond.birthday.mambole', [
            'template' => $template,
            'countries' => CountryDialCodes::list(),
            'lookupUrl' => url('/mambole/lookup'),
            'submitUrl' => url('/mambole/submit'),
        ]);
    }

    public function lookup(Request $request)
    {
        return app(PublicPhoneLookupController::class)->lookup($request);
    }

    public function submit(Request $request)
    {
        $data = $request->validate([
            'country_code' => 'required|string|max:10',
            'phone' => 'required|string|max:40',
            'display_name' => 'required|string|max:80',
            'call_name' => 'required|string|max:80',
            'template' => 'required|string|max:32',
            'selfie' => 'nullable|file|max:8192',
        ]);

        try {
            $phone = WhatsAppPhone::combine($data['country_code'], $data['phone']);
        } catch (\Throwable $e) {
            return $this->fail($request, 'Enter a valid WhatsApp number.', 422);
        }
        if (strlen(preg_replace('/\D/', '', $phone)) < 8) {
            return $this->fail($request, 'Enter a valid WhatsApp number.', 422);
        }

        $template = $data['template'];
        if (! $this->flyers->isTemplate($template)) {
            $template = $this->flyers->randomTemplate();
        }

        $selfieBin = $this->readSelfie($request);
        try {
            $row = $this->flyers->createFlyer(
                $phone,
                trim($data['display_name']),
                trim($data['call_name']),
                $template,
                $selfieBin
            );
        } catch (\Throwable $e) {
            Log::warning('mambole flyer compose failed: '.$e->getMessage());

            return $this->fail($request, 'Could not build the flyer. Please try again.', 500);
        }

        $send = $this->whatsapp->sendImage($phone, $row->absolutePath());
        $waOk = ! empty($send['success']);
        if (! $waOk) {
            Log::info('mambole WhatsApp send failed', ['error' => $send['error'] ?? 'unknown']);
        }

        $url = url('/mambole/flyer/'.$row->id).($waOk ? '' : '?sent=0');
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'redirect' => $url, 'whatsapp' => $waOk]);
        }

        return redirect($url);
    }

    public function show($id)
    {
        $row = BirthdayFlyer::find($id);
        if (! $row || ! is_file($row->absolutePath())) {
            abort(404);
        }

        return view('beyond.birthday.result', [
            'flyer' => $row,
            'imageUrl' => $row->publicUrl(),
            'sent' => request('sent') !== '0',
        ]);
    }

    protected function readSelfie(Request $request)
    {
        $file = $request->file('selfie');
        if (! $file || ! $file->isValid()) {
            return null;
        }
        $mime = strtolower((string) $file->getMimeType());
        if ($mime !== '' && strpos($mime, 'image/') !== 0 && $mime !== 'application/octet-stream') {
            return null;
        }
        $bin = @file_get_contents($file->getRealPath());
        if ($bin === false || strlen($bin) < 80) {
            return null;
        }

        return $bin;
    }

    protected function fail(Request $request, $message, $status)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => false, 'message' => $message], $status);
        }

        return back()->withInput()->withErrors(['phone' => $message]);
    }
}

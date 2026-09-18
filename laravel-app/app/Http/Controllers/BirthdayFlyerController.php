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
    const HONOREE_WHATSAPP = '+237670778788';
    const TESTER_WHATSAPP = '+237675321739';

    protected $flyers;
    protected $whatsapp;

    public function __construct(BirthdayFlyerService $flyers, BeyondWasenderService $whatsapp)
    {
        $this->flyers = $flyers;
        $this->whatsapp = $whatsapp;
    }

    public function index()
    {
        $template = $this->flyers->nextTemplate();

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
            'mark' => 'nullable|in:picture,sign,none',
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
        if ($this->alreadySubmitted($phone)) {
            return $this->fail($request, 'This number already sent a birthday wish.', 409);
        }

        $template = $this->flyers->nextTemplate();

        $mark = isset($data['mark']) ? $data['mark'] : 'none';
        $selfieBin = null;
        if ($mark === 'picture' || $mark === 'sign') {
            $selfieBin = $this->readSelfie($request);
        }
        try {
            $row = $this->flyers->createFlyer(
                $phone,
                trim($data['display_name']),
                trim($data['call_name']),
                $template,
                $selfieBin,
                $mark
            );
        } catch (\Throwable $e) {
            Log::warning('mambole flyer compose failed: '.$e->getMessage());

            return $this->fail($request, 'Could not build the flyer. Please try again.', 500);
        }

        $path = $row->absolutePath();
        $send = $this->whatsapp->sendImage($phone, $path);
        $waOk = ! empty($send['success']);
        if (! $waOk) {
            Log::info('mambole WhatsApp send failed', ['error' => $send['error'] ?? 'unknown']);
        }

        $this->queueHonoreeCopy($phone, $path);

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

    protected function alreadySubmitted($phone)
    {
        if ($this->sameNumber($phone, self::TESTER_WHATSAPP)) {
            return false;
        }
        $digits = preg_replace('/\D/', '', (string) $phone);
        if ($digits === '') {
            return false;
        }

        return BirthdayFlyer::whereRaw(
            "REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', '') = ?",
            [$digits]
        )->exists();
    }

    protected function sameNumber($a, $b)
    {
        try {
            return $this->whatsapp->formatPhone($a) === $this->whatsapp->formatPhone($b);
        } catch (\Throwable $e) {
            return preg_replace('/\D/', '', (string) $a) === preg_replace('/\D/', '', (string) $b);
        }
    }

    protected function queueHonoreeCopy($guestPhone, $path)
    {
        $guestTo = $this->whatsapp->formatPhone($guestPhone);
        $herTo = $this->whatsapp->formatPhone(self::HONOREE_WHATSAPP);
        if (! $herTo || $guestTo === $herTo) {
            return;
        }

        app()->terminating(function () use ($path) {
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            ignore_user_abort(true);
            @set_time_limit(90);
            try {
                $copy = app(BeyondWasenderService::class)->sendImage(self::HONOREE_WHATSAPP, $path);
                if (empty($copy['success'])) {
                    Log::info('mambole honoree copy failed', ['error' => $copy['error'] ?? 'unknown']);
                }
            } catch (\Throwable $e) {
                Log::warning('mambole honoree copy exception: '.$e->getMessage());
            }
        });
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

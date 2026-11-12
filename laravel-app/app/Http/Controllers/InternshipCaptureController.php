<?php

namespace App\Http\Controllers;

use App\JobPosting;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;

class InternshipCaptureController extends Controller
{
    public function handoff(Request $request, $id)
    {
        $job = JobPosting::find($id);
        if (! $job || ! $job->isInternship()) {
            abort(404);
        }
        $focus = $this->focus($request->input('focus'));
        $token = (string) session('apply_handoff_token', '');
        $row = $token !== '' ? Cache::get($this->key($token)) : null;
        if (! is_array($row) || (string) ($row['job_id'] ?? '') !== (string) $job->id) {
            $token = bin2hex(random_bytes(16));
            $row = [
                'job_id' => (string) $job->id,
                'id_front' => null,
                'id_back' => null,
                'selfie' => null,
            ];
        }
        Cache::put($this->key($token), $row, now()->addMinutes(30));
        session([
            'apply_handoff_token' => $token,
            'apply_handoff_job' => (string) $job->id,
        ]);
        $link = route('apply.capture', ['token' => $token]).'?focus='.$focus;
        try {
            $qr = (string) \QrCode::size(180)->margin(1)->generate($link);
        } catch (\Throwable $e) {
            $qr = '';
        }
        if ($qr === '') {
            return response()->json(['message' => 'The QR code could not be created. Try again.'], 500);
        }

        return response()->json([
            'qr' => $qr,
            'status_url' => route('apply.capture.status'),
            'token' => $token,
        ]);
    }

    public function status()
    {
        $token = (string) session('apply_handoff_token', '');
        $row = $token !== '' ? Cache::get($this->key($token)) : null;
        if (! is_array($row)) {
            return response()->json(['ready' => false]);
        }

        return response()->json([
            'ready' => true,
            'id_front' => $this->publicUrl(isset($row['id_front']) ? $row['id_front'] : ''),
            'id_back' => $this->publicUrl(isset($row['id_back']) ? $row['id_back'] : ''),
            'selfie' => $this->publicUrl(isset($row['selfie']) ? $row['selfie'] : ''),
            'token' => $token,
        ]);
    }

    public function show($token)
    {
        $row = $this->open($token);
        if (! $row) {
            return response()->view('beyond.apply.capture', [
                'token' => '',
                'expired' => true,
                'photos' => [],
            ], 410);
        }

        return view('beyond.apply.capture', [
            'token' => $this->cleanToken($token),
            'expired' => false,
            'photos' => [
                'id_front' => $this->publicUrl(isset($row['id_front']) ? $row['id_front'] : ''),
                'id_back' => $this->publicUrl(isset($row['id_back']) ? $row['id_back'] : ''),
                'selfie' => $this->publicUrl(isset($row['selfie']) ? $row['selfie'] : ''),
            ],
        ]);
    }

    public function store(Request $request, $token)
    {
        $row = $this->open($token);
        if (! $row) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['message' => 'This phone link has expired. Generate a new QR code.'], 410);
            }

            return back()->withErrors(['link' => 'This phone link has expired. Generate a new QR code.']);
        }
        $slot = $this->focus($request->input('slot'));
        $request->validate([
            'photo' => 'required|file|mimes:jpeg,jpg,png,webp|max:5120',
        ]);
        $path = $this->storePhoto($request->file('photo'), isset($row[$slot]) ? $row[$slot] : null);
        $latest = Cache::get($this->key($this->cleanToken($token)));
        if (is_array($latest)) {
            $row = array_merge($row, $latest);
        }
        $row[$slot] = $path;
        Cache::put($this->key($this->cleanToken($token)), $row, now()->addMinutes(30));

        return response()->json([
            'ok' => true,
            'slot' => $slot,
            'url' => $this->publicUrl($path),
        ]);
    }

    public static function fileFromSession($slot)
    {
        $token = (string) session('apply_handoff_token', '');
        if ($token === '') {
            return null;
        }
        $row = Cache::get('apply-handoff:'.hash('sha256', $token));
        if (! is_array($row) || empty($row[$slot])) {
            return null;
        }
        $relative = ltrim(str_replace('public/', '', (string) $row[$slot]), '/');
        $full = public_path($relative);
        if (! is_file($full)) {
            return null;
        }
        $mime = function_exists('mime_content_type') ? mime_content_type($full) : 'image/jpeg';

        return new UploadedFile($full, basename($full), $mime ?: 'image/jpeg', null, true);
    }

    protected function storePhoto(UploadedFile $file, $oldPath)
    {
        $dir = public_path('images/apply-handoff');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = 'id_'.time().'_'.bin2hex(random_bytes(4)).'.jpg';
        $full = $dir.'/'.$name;
        try {
            $img = \Image::make($file->getRealPath());
            $img->resize(1400, 1400, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            $img->encode('jpg', 80)->save($full);
        } catch (\Throwable $e) {
            $file->move($dir, $name);
        }
        $this->deleteLocal($oldPath);

        return 'images/apply-handoff/'.$name;
    }

    protected function deleteLocal($path)
    {
        $path = ltrim(str_replace('public/', '', (string) $path), '/');
        if ($path === '' || strpos($path, 'images/apply-handoff/') !== 0) {
            return;
        }
        $full = public_path($path);
        if (is_file($full)) {
            @unlink($full);
        }
    }

    protected function open($token)
    {
        $token = $this->cleanToken($token);
        if (strlen($token) !== 32) {
            return null;
        }
        $row = Cache::get($this->key($token));

        return is_array($row) ? $row : null;
    }

    protected function key($token)
    {
        return 'apply-handoff:'.hash('sha256', (string) $token);
    }

    protected function cleanToken($token)
    {
        return strtolower(preg_replace('/[^a-f0-9]/', '', (string) $token));
    }

    protected function focus($value)
    {
        $value = (string) $value;

        return in_array($value, ['id_front', 'id_back', 'selfie'], true) ? $value : 'id_front';
    }

    protected function publicUrl($path)
    {
        $path = trim((string) $path);
        if ($path === '') {
            return '';
        }

        return url('public/'.ltrim(str_replace('public/', '', $path), '/'));
    }
}

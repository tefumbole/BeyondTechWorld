<?php

namespace App\Http\Controllers;

use App\GeneralSetting;
use App\Services\BeyondWasenderService;
use App\Support\LetterSignature;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserSignatureController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! Auth::check()) {
                return $next($request);
            }
            $role = Role::find(Auth::user()->role_id);
            if ($role) {
                $permissions = Role::findByName($role->name)->permissions;
                foreach ($permissions as $permission) {
                    $all_permission[] = $permission->name;
                }
                if (empty($all_permission)) {
                    $all_permission[] = 'dummy text';
                }
                \Illuminate\Support\Facades\View::share('all_permission', $all_permission);
            }

            return $next($request);
        });
    }

    /**
     * Admin: save signature from pad onto the user account immediately.
     */
    public function savePad(Request $request, $id)
    {
        if (! Auth::check()) {
            abort(403);
        }

        $request->validate([
            'signature_image' => 'required|string',
        ]);

        $user = User::findOrFail($id);
        $filename = $this->persistUserSign($user, $request->signature_image);
        if (! $filename) {
            return $this->signatureResponse($request, false, 'Could not save signature. Please try again.', 422);
        }

        return $this->signatureResponse($request, true, 'Signature saved successfully.');
    }

    /**
     * Admin: WhatsApp a one-time signing link to the user.
     */
    public function requestLink(Request $request, $id)
    {
        if (! Auth::check()) {
            abort(403);
        }

        $user = User::findOrFail($id);
        $phone = trim((string) ($user->phone ?: $user->additional_phone));
        if ($phone === '') {
            return $this->signatureResponse($request, false, 'This user has no phone number for WhatsApp.', 422);
        }

        $token = Str::random(48);
        $user->sign_request_token = $token;
        $user->sign_request_expires_at = now()->addDays(3);
        $user->save();

        $link = url('/user-sign/'.$token);
        $company = optional(GeneralSetting::first())->site_title ?: 'Beyond Enterprise';
        $msg = "{$company}: Please add your signature using this secure link:\n{$link}\n\nThis link expires in 3 days.";

        // Brief pause helps Wasender account-protection (1 msg / 5s).
        usleep(1200000);

        $result = app(BeyondWasenderService::class)->sendText($phone, $msg);
        \Log::info('[user-signature] WhatsApp request result', [
            'user_id' => $user->id,
            'phone' => $phone,
            'link' => $link,
            'result' => $result,
        ]);

        if (empty($result['success']) || ! empty($result['skipped'])) {
            return $this->signatureResponse(
                $request,
                false,
                'WhatsApp did not deliver: '.($result['error'] ?? 'messaging skipped or failed').'. Use the link below.',
                422,
                $link
            );
        }

        $displayPhone = \App\Support\WhatsAppPhone::display($phone);

        return $this->signatureResponse(
            $request,
            true,
            'Signature request sent to WhatsApp ('.$displayPhone.'). If it does not arrive, use Open link below.',
            200,
            $link
        );
    }

    protected function signatureResponse(Request $request, bool $success, string $message, int $status = 200, $link = null)
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => $success,
                'message' => $message,
                'link' => $link,
            ], $status);
        }

        if ($success) {
            $redirect = back()->with('message2', $message);
        } else {
            $redirect = back()->with('not_permitted', $message);
        }
        if ($link) {
            $redirect->with('signature_request_link', $link);
        }

        return $redirect;
    }

    /**
     * Public: show pad for token link.
     */
    public function publicShow($token)
    {
        $user = $this->findValidRequest($token);
        if (! $user) {
            return response()->view('user.public_sign_expired', [], 410);
        }

        $general_setting = GeneralSetting::first();

        return view('user.public_sign', compact('user', 'token', 'general_setting'));
    }

    /**
     * Public: store signature from token link.
     */
    public function publicStore(Request $request, $token)
    {
        $user = $this->findValidRequest($token);
        if (! $user) {
            return response()->view('user.public_sign_expired', [], 410);
        }

        $request->validate([
            'signature_image' => 'required|string',
        ]);

        try {
            $filename = $this->persistUserSign($user, $request->signature_image);
        } catch (\Throwable $e) {
            \Log::error('UserSignature publicStore failed: '.$e->getMessage());
            $filename = null;
        }

        if (! $filename) {
            return back()->with('not_permitted', 'Could not save signature. Please try again.');
        }

        $user->sign_request_token = null;
        $user->sign_request_expires_at = null;
        $user->save();

        return view('user.public_sign_done', [
            'user' => $user,
            'general_setting' => GeneralSetting::first(),
        ]);
    }

    protected function findValidRequest($token)
    {
        $user = User::where('sign_request_token', $token)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('is_deleted', false)->orWhereNull('is_deleted');
            })
            ->first();

        if (! $user) {
            return null;
        }
        if ($user->sign_request_expires_at && $user->sign_request_expires_at->isPast()) {
            return null;
        }

        return $user;
    }

    protected function persistUserSign(User $user, $dataUrl)
    {
        try {
            $stored = LetterSignature::storeFromDataUrl($dataUrl, 'user_sign');
            $source = $stored ? LetterSignature::absolutePath($stored) : null;

            $dir = LetterSignature::ensureWritableDir([
                public_path('images/user'),
                storage_path('app/public/images/user'),
            ]);
            if (! $dir) {
                \Log::error('UserSignature: images/user is not writable');

                return null;
            }

            $accountFile = 'sign_'.$user->id.'_'.date('YmdHis').'_'.Str::random(4).'.png';
            $dest = $dir.'/'.$accountFile;

            if ($source && is_file($source)) {
                if (! @copy($source, $dest)) {
                    return null;
                }
            } else {
                // Fallback: write data URL straight into images/user
                if (! preg_match('/^data:image\/png;base64,/', (string) $dataUrl)) {
                    return null;
                }
                $raw = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1));
                if ($raw === false || @file_put_contents($dest, $raw) === false) {
                    return null;
                }
            }

            if ($user->sign) {
                foreach ([
                    public_path('images/user/'.$user->sign),
                    storage_path('app/public/images/user/'.$user->sign),
                ] as $old) {
                    if (is_file($old)) {
                        @unlink($old);
                    }
                }
            }

            $user->sign = $accountFile;
            $user->save();

            return $accountFile;
        } catch (\Throwable $e) {
            \Log::error('UserSignature persist failed: '.$e->getMessage());

            return null;
        }
    }
}

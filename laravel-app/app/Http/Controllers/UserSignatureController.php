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
            return back()->with('not_permitted', 'Could not save signature. Please try again.');
        }

        return back()->with('message2', 'Signature saved successfully.');
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
            return back()->with('not_permitted', 'This user has no phone number for WhatsApp.');
        }

        $token = Str::random(48);
        $user->sign_request_token = $token;
        $user->sign_request_expires_at = now()->addDays(3);
        $user->save();

        $link = url('/user-sign/'.$token);
        $company = optional(GeneralSetting::first())->site_title ?: 'Beyond Enterprise';
        $msg = "{$company}: Please add your signature using this secure link:\n{$link}\n\nThis link expires in 3 days.";

        // Brief pause helps Wasender account-protection (1 msg / 5s).
        usleep(550000);

        $result = app(BeyondWasenderService::class)->sendText($phone, $msg);
        \Log::info('[user-signature] WhatsApp request result', [
            'user_id' => $user->id,
            'phone' => $phone,
            'link' => $link,
            'result' => $result,
        ]);

        // Always expose the link — WhatsApp delivery can lag/fail even when API says OK.
        $flashLink = $link;

        if (empty($result['success']) || ! empty($result['skipped'])) {
            return back()
                ->with('not_permitted', 'WhatsApp did not deliver: '.($result['error'] ?? 'messaging skipped or failed').'. Use the link below.')
                ->with('signature_request_link', $flashLink);
        }

        return back()
            ->with('message2', 'Signature request queued to WhatsApp ('.$phone.'). If it does not arrive within a minute, open or copy the link below.')
            ->with('signature_request_link', $flashLink);
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

        $filename = $this->persistUserSign($user, $request->signature_image);
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
        $stored = LetterSignature::storeFromDataUrl($dataUrl, 'user_sign');
        if (! $stored) {
            return null;
        }

        $source = public_path('letter/signatures/'.$stored);
        if (! is_file($source)) {
            return null;
        }

        $dir = public_path('images/user');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $accountFile = 'sign_'.$user->id.'_'.date('YmdHis').'_'.Str::random(4).'.png';
        if (! @copy($source, $dir.'/'.$accountFile)) {
            return null;
        }

        if ($user->sign) {
            $old = public_path('images/user/'.$user->sign);
            if (is_file($old)) {
                @unlink($old);
            }
        }

        $user->sign = $accountFile;
        $user->save();

        return $accountFile;
    }
}

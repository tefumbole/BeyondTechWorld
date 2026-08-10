<?php

namespace App\Http\Controllers;

use App\GeneralSetting;
use App\Services\BeyondWasenderService;
use App\Support\LetterSignature;
use App\Support\WhatsAppPhone;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserSignatureController extends Controller
{
    /** @var array column => UI label */
    const TYPES = [
        'sign' => 'Signature',
        'stemp' => 'Comment',
        'approve' => 'Approver',
    ];

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

    public function savePad(Request $request, $id)
    {
        if (! Auth::check()) {
            abort(403);
        }

        $type = $this->resolveType($request);
        $request->validate([
            'signature_image' => 'required|string',
        ]);

        $user = User::findOrFail($id);
        $filename = $this->persistUserImage($user, $request->signature_image, $type);
        if (! $filename) {
            return $this->signatureResponse($request, false, 'Could not save '.strtolower(self::TYPES[$type]).'. Please try again.', 422);
        }

        return $this->signatureResponse($request, true, self::TYPES[$type].' saved successfully.');
    }

    public function requestLink(Request $request, $id)
    {
        if (! Auth::check()) {
            abort(403);
        }

        $type = $this->resolveType($request);
        $user = User::findOrFail($id);
        $phone = trim((string) ($user->phone ?: $user->additional_phone));
        if ($phone === '') {
            return $this->signatureResponse($request, false, 'This user has no phone number for WhatsApp.', 422);
        }

        $token = Str::random(48);
        $user->sign_request_token = $token;
        $user->sign_request_type = $type;
        $user->sign_request_expires_at = now()->addDays(3);
        $user->save();

        $link = url('/user-sign/'.$token);
        $label = self::TYPES[$type];
        $company = optional(GeneralSetting::first())->site_title ?: 'Beyond Enterprise';
        $msg = "{$company}: Please add your {$label} using this secure link:\n{$link}\n\nThis link expires in 3 days.";

        usleep(1200000);

        $result = app(BeyondWasenderService::class)->sendText($phone, $msg);
        \Log::info('[user-signature] WhatsApp request result', [
            'user_id' => $user->id,
            'type' => $type,
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

        return $this->signatureResponse(
            $request,
            true,
            $label.' request sent to WhatsApp ('.WhatsAppPhone::display($phone).'). If it does not arrive, use Open link below.',
            200,
            $link
        );
    }

    public function destroy(Request $request, $id)
    {
        if (! Auth::check()) {
            abort(403);
        }

        $type = $this->resolveType($request);
        $user = User::findOrFail($id);
        $this->deleteUserImage($user, $type);

        return $this->signatureResponse($request, true, self::TYPES[$type].' deleted.');
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

        $redirect = $success
            ? back()->with('message2', $message)
            : back()->with('not_permitted', $message);
        if ($link) {
            $redirect->with('signature_request_link', $link);
        }

        return $redirect;
    }

    public function publicShow($token)
    {
        $user = $this->findValidRequest($token);
        if (! $user) {
            return response()->view('user.public_sign_expired', [], 410);
        }

        $type = $this->normalizeType($user->sign_request_type ?: 'sign');
        $label = self::TYPES[$type];
        $general_setting = GeneralSetting::first();

        return view('user.public_sign', compact('user', 'token', 'general_setting', 'type', 'label'));
    }

    public function publicStore(Request $request, $token)
    {
        $user = $this->findValidRequest($token);
        if (! $user) {
            return response()->view('user.public_sign_expired', [], 410);
        }

        $request->validate([
            'signature_image' => 'required|string',
        ]);

        $type = $this->normalizeType($user->sign_request_type ?: 'sign');

        try {
            $filename = $this->persistUserImage($user, $request->signature_image, $type);
        } catch (\Throwable $e) {
            \Log::error('UserSignature publicStore failed: '.$e->getMessage());
            $filename = null;
        }

        if (! $filename) {
            return back()->with('not_permitted', 'Could not save. Please try again.');
        }

        $user->sign_request_token = null;
        $user->sign_request_type = null;
        $user->sign_request_expires_at = null;
        $user->save();

        return view('user.public_sign_done', [
            'user' => $user,
            'general_setting' => GeneralSetting::first(),
            'label' => self::TYPES[$type],
        ]);
    }

    protected function resolveType(Request $request)
    {
        return $this->normalizeType($request->input('type', 'sign'));
    }

    protected function normalizeType($type)
    {
        $type = strtolower(trim((string) $type));
        if (! isset(self::TYPES[$type])) {
            abort(422, 'Invalid signature type.');
        }

        return $type;
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

    protected function persistUserImage(User $user, $dataUrl, $type)
    {
        try {
            $stored = LetterSignature::storeFromDataUrl($dataUrl, 'user_'.$type);
            $source = $stored ? LetterSignature::absolutePath($stored) : null;

            $dir = LetterSignature::ensureWritableDir([
                public_path('images/user'),
                storage_path('app/public/images/user'),
            ]);
            if (! $dir) {
                \Log::error('UserSignature: images/user is not writable');

                return null;
            }

            $accountFile = $type.'_'.$user->id.'_'.date('YmdHis').'_'.Str::random(4).'.png';
            $dest = $dir.'/'.$accountFile;

            if ($source && is_file($source)) {
                if (! @copy($source, $dest)) {
                    return null;
                }
            } else {
                if (! preg_match('/^data:image\/png;base64,/', (string) $dataUrl)) {
                    return null;
                }
                $raw = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1));
                if ($raw === false || @file_put_contents($dest, $raw) === false) {
                    return null;
                }
            }

            $this->deleteUserImageFile($user->{$type});

            $user->{$type} = $accountFile;
            $user->save();

            return $accountFile;
        } catch (\Throwable $e) {
            \Log::error('UserSignature persist failed: '.$e->getMessage());

            return null;
        }
    }

    protected function deleteUserImage(User $user, $type)
    {
        $this->deleteUserImageFile($user->{$type});
        $user->{$type} = null;
        if ($user->sign_request_type === $type) {
            $user->sign_request_token = null;
            $user->sign_request_type = null;
            $user->sign_request_expires_at = null;
        }
        $user->save();
    }

    protected function deleteUserImageFile($filename)
    {
        if (! $filename) {
            return;
        }
        foreach ([
            public_path('images/user/'.$filename),
            storage_path('app/public/images/user/'.$filename),
        ] as $old) {
            if (is_file($old)) {
                @unlink($old);
            }
        }
    }
}

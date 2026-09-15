<?php

namespace App\Http\Controllers;

use App\BeyondProfile;
use App\BeyondUser;
use App\Services\BeyondAuthService;
use App\Services\BeyondWasenderService;
use App\Services\TaskService;
use App\Support\CountryDialCodes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskInviteController extends Controller
{
    protected $tasks;
    protected $auth;
    protected $whatsapp;

    public function __construct(TaskService $tasks, BeyondAuthService $auth, BeyondWasenderService $whatsapp)
    {
        $this->tasks = $tasks;
        $this->auth = $auth;
        $this->whatsapp = $whatsapp;
    }

    public function show(Request $request, $token)
    {
        $assignment = $this->tasks->findByInviteToken($token);
        if (! $assignment || ! $assignment->task) {
            return view('beyond.tasks.invite', ['assignment' => null, 'token' => $token]);
        }

        $request->session()->put('beyond_intended', '/task-invite/'.$token);

        $user = Auth::guard('beyond')->user();
        $isOwner = $user && $user->id === $assignment->user_id;
        $assignee = BeyondUser::find($assignment->user_id);
        $phone = $assignee ? $this->assigneePhone($assignee) : '';
        $sessionPhone = $request->session()->get('task_invite_otp_'.$token);
        $masked = $request->session()->get('task_invite_masked_'.$token);
        if (! $masked && $phone) {
            $masked = $this->whatsapp->maskPhone($phone);
        }

        return view('beyond.tasks.invite', [
            'assignment' => $assignment,
            'task' => $assignment->task,
            'token' => $token,
            'isOwner' => $isOwner,
            'loggedIn' => (bool) $user,
            'assignee' => $assignee,
            'maskedPhone' => $masked ?: '',
            'otpSent' => (bool) $sessionPhone,
            'countryCodes' => CountryDialCodes::all(),
            'needsCredentials' => $isOwner && $user && $user->must_change_credentials,
        ]);
    }

    public function sendSetupOtp(Request $request, $token)
    {
        $assignment = $this->tasks->findByInviteToken($token);
        if (! $assignment || ! $assignment->task) {
            return redirect()->route('task.invite', $token);
        }

        $assignee = BeyondUser::find($assignment->user_id);
        $onFile = $assignee ? $this->assigneePhone($assignee) : '';
        $typed = trim((string) $request->get('phone', ''));
        $code = trim((string) $request->get('country_code', ''));
        if ($typed !== '') {
            $phone = $code !== '' ? CountryDialCodes::combine($code, $typed) : $typed;
        } else {
            $phone = (string) $request->session()->get('task_invite_otp_'.$token, $onFile);
        }

        if ($phone === '') {
            return back()->withErrors(['setup' => 'Enter the WhatsApp number already in the system.']);
        }

        try {
            $formatted = $this->whatsapp->formatPhone($phone);
        } catch (\Throwable $e) {
            return back()->withErrors(['setup' => 'Enter a valid WhatsApp number.']);
        }

        if ($onFile !== '' && ! $this->phonesMatch($formatted, $onFile)) {
            return back()->withInput()->withErrors([
                'setup' => 'That number does not match this assignment. Use the WhatsApp number already in the system.',
            ]);
        }

        if ($onFile === '' && $assignee) {
            $assignee->phone = $formatted;
            $assignee->save();
            $this->auth->syncProfile($assignee);
        }

        $otp = $this->auth->createOtp($formatted, 'task_invite');
        $send = $this->whatsapp->sendOtp($formatted, $otp['code'], 'login');
        if (empty($send['success'])) {
            return back()->withErrors(['setup' => $send['error'] ?? 'Failed to send the WhatsApp code.']);
        }

        $request->session()->put('task_invite_otp_'.$token, $otp['phone']);
        $request->session()->put('task_invite_masked_'.$token, $this->whatsapp->maskPhone($otp['phone']));
        $request->session()->put('beyond_intended', '/task-invite/'.$token);

        return redirect()->route('task.invite', $token)
            ->with('success', 'A verification code was sent to '.$this->whatsapp->maskPhone($otp['phone']).'.');
    }

    public function verifyAccess(Request $request, $token)
    {
        $request->validate([
            'otp' => 'required|string|size:6',
        ]);

        $assignment = $this->tasks->findByInviteToken($token);
        if (! $assignment || ! $assignment->task) {
            return redirect()->route('task.invite', $token);
        }

        $assignee = BeyondUser::find($assignment->user_id);
        if (! $assignee) {
            return back()->withErrors(['otp' => 'The invited account could not be found.']);
        }

        $phone = $request->session()->get('task_invite_otp_'.$token);
        if (! $phone) {
            return back()->withErrors(['otp' => 'Request a WhatsApp code first.']);
        }

        $result = $this->auth->verifyOtp($phone, $request->otp, 'task_invite');
        if (empty($result['success'])) {
            return back()->withInput()->withErrors(['otp' => $result['error'] ?? 'Invalid or expired verification code.']);
        }

        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }
        Auth::guard('beyond')->login($assignee);
        $request->session()->put('beyond_otp_verified', true);
        $request->session()->put('beyond_masked_phone', $this->whatsapp->maskPhone($phone));
        $request->session()->forget('task_invite_otp_'.$token);

        return redirect()->route('task.invite', $token)
            ->with('status', 'Signed in with WhatsApp. You can accept this task now. A username and password are optional.');
    }

    public function storeSetup(Request $request, $token)
    {
        $request->validate([
            'username' => 'required|string|min:3|max:100',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $assignment = $this->tasks->findByInviteToken($token);
        if (! $assignment || ! $assignment->task) {
            return redirect()->route('task.invite', $token);
        }

        $assignee = BeyondUser::find($assignment->user_id);
        if (! $assignee) {
            return back()->withErrors(['setup' => 'The invited account could not be found.']);
        }

        $user = Auth::guard('beyond')->user();
        $alreadyIn = $user && $user->id === $assignee->id && $request->session()->get('beyond_otp_verified');
        if (! $alreadyIn) {
            return back()->withErrors(['setup' => 'Verify the WhatsApp code first, then you can create a username and password if you want.']);
        }

        $username = $this->auth->normalizeUsername($request->username);
        if (strlen($username) < 3) {
            return back()->withInput()->withErrors([
                'username' => 'Use at least 3 letters or numbers. Spaces become dots.',
            ]);
        }

        $taken = BeyondUser::whereRaw('LOWER(username) = ?', [$username])
            ->where('id', '!=', $assignee->id)
            ->exists();
        if ($taken) {
            return back()->withInput()->withErrors(['username' => 'That username is already taken. Choose another.']);
        }

        $plain = $request->password;
        $assignee->username = $username;
        $assignee->password_hash = $this->auth->hashPassword($plain);
        $assignee->must_change_credentials = false;
        $assignee->status = 'active';
        $assignee->save();
        $this->auth->syncProfile($assignee);

        $phone = $this->assigneePhone($assignee);
        $this->auth->sendLoginDetails($phone, $assignee->name, $username, $plain);

        return redirect()->route('task.invite', $token)
            ->with('status', 'Username and password saved and sent to your WhatsApp. You can still sign in with phone + OTP.');
    }

    public function accept(Request $request, $token)
    {
        $request->validate(['signature' => 'required|string']);

        $assignment = $this->guardOwnership($token);
        if ($assignment instanceof \Illuminate\Http\RedirectResponse) {
            return $assignment;
        }

        $this->tasks->accept($assignment, $request->input('signature'));

        return redirect()->route('user.tasks')->with('status', 'Task accepted — your signature was recorded.');
    }

    public function decline(Request $request, $token)
    {
        $assignment = $this->guardOwnership($token);
        if ($assignment instanceof \Illuminate\Http\RedirectResponse) {
            return $assignment;
        }

        $this->tasks->decline($assignment);

        return redirect()->route('user.tasks')->with('status', 'Task declined.');
    }

    protected function guardOwnership($token)
    {
        $assignment = $this->tasks->findByInviteToken($token);
        if (! $assignment || ! $assignment->task) {
            return redirect()->route('beyond.home')->withErrors(['task' => 'This task invite is invalid or has expired.']);
        }

        $user = Auth::guard('beyond')->user();
        if (! $user) {
            return redirect('/task-invite/'.$token)->withErrors(['setup' => 'Enter your WhatsApp number and the OTP to respond.']);
        }

        if ($user->id !== $assignment->user_id) {
            return redirect()->route('beyond.home')->withErrors(['task' => 'This task invite belongs to a different account.']);
        }

        return $assignment;
    }

    protected function phonesMatch($a, $b)
    {
        $ta = substr(preg_replace('/\D/', '', (string) $a), -9);
        $tb = substr(preg_replace('/\D/', '', (string) $b), -9);

        return strlen($ta) >= 8 && $ta === $tb;
    }

    protected function assigneePhone(BeyondUser $user)
    {
        $phone = trim((string) ($user->phone ?? ''));
        if ($phone !== '') {
            return $phone;
        }
        $profile = BeyondProfile::find($user->id);
        if ($profile && trim((string) $profile->phone) !== '') {
            return trim((string) $profile->phone);
        }

        return '';
    }
}

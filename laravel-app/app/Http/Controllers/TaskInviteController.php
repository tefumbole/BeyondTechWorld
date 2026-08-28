<?php

namespace App\Http\Controllers;

use App\BeyondProfile;
use App\BeyondUser;
use App\Services\BeyondAuthService;
use App\Services\BeyondWasenderService;
use App\Services\TaskService;
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

        return view('beyond.tasks.invite', [
            'assignment' => $assignment,
            'task' => $assignment->task,
            'token' => $token,
            'isOwner' => $isOwner,
            'loggedIn' => (bool) $user,
            'assignee' => $assignee,
            'maskedPhone' => $phone ? $this->whatsapp->maskPhone($phone) : '',
            'otpSent' => (bool) $request->session()->get('task_invite_otp_'.$token),
        ]);
    }

    public function sendSetupOtp(Request $request, $token)
    {
        $assignment = $this->tasks->findByInviteToken($token);
        if (! $assignment || ! $assignment->task) {
            return redirect()->route('task.invite', $token);
        }

        $assignee = BeyondUser::find($assignment->user_id);
        $phone = $assignee ? $this->assigneePhone($assignee) : '';
        if ($phone === '') {
            return back()->withErrors(['setup' => 'This task has no WhatsApp number on file. Ask the sender to update your phone, then try again.']);
        }

        try {
            $formatted = $this->whatsapp->formatPhone($phone);
        } catch (\Throwable $e) {
            return back()->withErrors(['setup' => 'The WhatsApp number on this task is invalid.']);
        }

        $otp = $this->auth->createOtp($formatted, 'task_invite');
        $send = $this->whatsapp->sendOtp($formatted, $otp['code'], 'login');
        if (empty($send['success'])) {
            return back()->withErrors(['setup' => $send['error'] ?? 'Failed to send the WhatsApp code.']);
        }

        $request->session()->put('task_invite_otp_'.$token, $otp['phone']);
        $request->session()->put('beyond_intended', '/task-invite/'.$token);

        return redirect()->route('task.invite', $token)->with('success', 'A verification code was sent to your WhatsApp.');
    }

    public function storeSetup(Request $request, $token)
    {
        $request->validate([
            'otp' => 'required|string|size:6',
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

        $sessionPhone = $request->session()->get('task_invite_otp_'.$token);
        $phone = $sessionPhone ?: $this->assigneePhone($assignee);
        if (! $phone) {
            return back()->withErrors(['otp' => 'Request a WhatsApp code first.']);
        }

        $result = $this->auth->verifyOtp($phone, $request->otp, 'task_invite');
        if (empty($result['success'])) {
            return back()->withInput()->withErrors(['otp' => $result['error'] ?? 'Invalid or expired verification code.']);
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

        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }
        Auth::guard('beyond')->login($assignee);
        $request->session()->put('beyond_otp_verified', true);
        $request->session()->forget('task_invite_otp_'.$token);

        $this->auth->sendLoginDetails($this->assigneePhone($assignee) ?: $phone, $assignee->name, $username, $plain);

        return redirect()->route('task.invite', $token)
            ->with('status', 'Your username and password were saved and sent to your WhatsApp. You can now accept or decline this task.');
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
            return redirect('/task-invite/'.$token)->withErrors(['setup' => 'Create a username and password (or sign in) to respond.']);
        }

        if ($user->id !== $assignment->user_id) {
            return redirect()->route('beyond.home')->withErrors(['task' => 'This task invite belongs to a different account.']);
        }

        return $assignment;
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

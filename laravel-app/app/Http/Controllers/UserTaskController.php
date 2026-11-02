<?php

namespace App\Http\Controllers;

use App\BeyondProfile;
use App\Services\BeyondAuthService;
use App\Services\BeyondWasenderService;
use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserTaskController extends Controller
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

    public function index(Request $request)
    {
        $user = Auth::guard('beyond')->user();
        $statusFilter = $request->query('status', 'All');
        $categoryFilter = $request->query('category', 'All');

        $assignments = $this->tasks->myTasks($user->id, $statusFilter, $categoryFilter);
        $categories = $this->tasks->categories();
        $statuses = ['All', 'Pending', 'Accepted', 'In Progress', 'Completed', 'Overdue'];

        return view('beyond.tasks.my-tasks', [
            'user' => $user,
            'assignments' => $assignments,
            'categories' => $categories,
            'statuses' => $statuses,
            'statusFilter' => $statusFilter,
            'categoryFilter' => $categoryFilter,
            'service' => $this->tasks,
            'needsCredentials' => (bool) $user->must_change_credentials,
            'acceptWaiting' => (string) $request->session()->get('task_accept_waiting', ''),
            'acceptMasked' => (string) $request->session()->get('task_accept_masked', ''),
        ]);
    }

    public function pending(Request $request)
    {
        $user = Auth::guard('beyond')->user();
        $assignments = $this->tasks->pendingAcceptances($user->id);

        return view('beyond.tasks.pending', [
            'user' => $user,
            'assignments' => $assignments,
            'service' => $this->tasks,
            'acceptWaiting' => (string) $request->session()->get('task_accept_waiting', ''),
            'acceptMasked' => (string) $request->session()->get('task_accept_masked', ''),
        ]);
    }

    public function sendAcceptCode(Request $request, $assignmentId)
    {
        $user = Auth::guard('beyond')->user();
        $assignment = $this->tasks->findAssignmentForUser($assignmentId, $user->id);
        if (! $assignment || ! $assignment->task) {
            return back()->withErrors(['task' => 'Task assignment not found.']);
        }
        if ($assignment->status !== 'Pending') {
            return back()->with('status', 'This task is already '.$assignment->status.'.');
        }

        $phone = $this->userPhone($user);
        if ($phone === '') {
            return back()->withErrors(['otp' => 'This account has no WhatsApp number for a code.']);
        }

        try {
            $formatted = $this->whatsapp->formatPhone($phone);
        } catch (\Throwable $e) {
            return back()->withErrors(['otp' => 'The WhatsApp number on this account is not valid.']);
        }

        $otp = $this->auth->createOtp($formatted, 'task_accept');
        $send = $this->whatsapp->sendOtp($formatted, $otp['code'], 'login');
        if (empty($send['success'])) {
            return back()->withErrors(['otp' => isset($send['error']) ? $send['error'] : 'Failed to send the WhatsApp code.']);
        }

        $request->session()->put('task_accept_waiting', (string) $assignment->id);
        $request->session()->put('task_accept_phone', $otp['phone']);
        $request->session()->put('task_accept_masked', $this->whatsapp->maskPhone($otp['phone']));

        return back()->with('status', 'Enter the code sent to '.$this->whatsapp->maskPhone($otp['phone']).'.');
    }

    public function accept(Request $request, $assignmentId)
    {
        $user = Auth::guard('beyond')->user();
        $assignment = $this->tasks->findAssignmentForUser($assignmentId, $user->id);
        if (! $assignment) {
            return back()->withErrors(['task' => 'Task assignment not found.']);
        }
        if ($assignment->status !== 'Pending') {
            return back()->with('status', 'This task is already '.$assignment->status.'.');
        }
        if ((string) $request->session()->get('task_accept_waiting') !== (string) $assignment->id) {
            return back()->withErrors(['otp' => 'Tap Accept so we can send the WhatsApp code.']);
        }

        $otp = preg_replace('/\D+/', '', (string) $request->input('otp'));
        if (strlen($otp) !== 6) {
            return back()->withErrors(['otp' => 'Enter the 6-digit WhatsApp code.']);
        }

        $phone = $request->session()->get('task_accept_phone');
        $result = $phone ? $this->auth->verifyOtp($phone, $otp, 'task_accept') : ['success' => false];
        if (empty($result['success'])) {
            return back()->withErrors(['otp' => isset($result['error']) ? $result['error'] : 'Invalid or expired verification code.']);
        }

        $request->session()->forget('task_accept_waiting');
        $request->session()->forget('task_accept_phone');
        $request->session()->forget('task_accept_masked');
        $this->tasks->accept($assignment, null);

        return back()->with('status', 'Task accepted.');
    }

    protected function userPhone($user)
    {
        $phone = trim((string) (isset($user->phone) ? $user->phone : ''));
        if ($phone !== '') {
            return $phone;
        }
        $profile = BeyondProfile::find($user->id);
        if ($profile && trim((string) $profile->phone) !== '') {
            return trim((string) $profile->phone);
        }

        return '';
    }

    public function decline($assignmentId)
    {
        $user = Auth::guard('beyond')->user();
        $assignment = $this->tasks->findAssignmentForUser($assignmentId, $user->id);
        if (! $assignment) {
            return back()->withErrors(['task' => 'Task assignment not found.']);
        }

        $this->tasks->decline($assignment);

        return back()->with('status', 'Task declined.');
    }

    public function update(Request $request, $assignmentId)
    {
        $validated = $request->validate([
            'progress' => 'required|integer|min:0|max:100',
            'status' => 'required|string',
        ]);

        $user = Auth::guard('beyond')->user();
        $assignment = $this->tasks->findAssignmentForUser($assignmentId, $user->id);
        if (! $assignment) {
            return back()->withErrors(['task' => 'Task assignment not found.']);
        }

        $this->tasks->updateProgress($assignment, $validated['progress'], $validated['status']);

        return back()->with('status', 'Progress saved.');
    }

    public function remove(Request $request, $assignmentId)
    {
        $user = Auth::guard('beyond')->user();
        $this->tasks->removeAssignments([$assignmentId], $user->id);

        return back()->with('status', 'Task removed from your list.');
    }
}

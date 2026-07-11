<?php

namespace App\Http\Controllers;

use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskInviteController extends Controller
{
    protected $tasks;

    public function __construct(TaskService $tasks)
    {
        $this->tasks = $tasks;
    }

    public function show(Request $request, $token)
    {
        $assignment = $this->tasks->findByInviteToken($token);
        if (! $assignment || ! $assignment->task) {
            return view('beyond.tasks.invite', ['assignment' => null, 'token' => $token]);
        }

        $user = Auth::guard('beyond')->user();
        $isOwner = $user && $user->id === $assignment->user_id;

        return view('beyond.tasks.invite', [
            'assignment' => $assignment,
            'task' => $assignment->task,
            'token' => $token,
            'isOwner' => $isOwner,
            'loggedIn' => (bool) $user,
        ]);
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
            return redirect('/login?redirect='.urlencode('/task-invite/'.$token));
        }

        if ($user->id !== $assignment->user_id) {
            return redirect()->route('beyond.home')->withErrors(['task' => 'This task invite belongs to a different account.']);
        }

        return $assignment;
    }
}

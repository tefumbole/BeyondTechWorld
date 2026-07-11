<?php

namespace App\Http\Controllers;

use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserTaskController extends Controller
{
    protected $tasks;

    public function __construct(TaskService $tasks)
    {
        $this->tasks = $tasks;
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
        ]);
    }

    public function pending()
    {
        $user = Auth::guard('beyond')->user();
        $assignments = $this->tasks->pendingAcceptances($user->id);

        return view('beyond.tasks.pending', [
            'user' => $user,
            'assignments' => $assignments,
            'service' => $this->tasks,
        ]);
    }

    public function accept(Request $request, $assignmentId)
    {
        $request->validate(['signature' => 'required|string']);

        $user = Auth::guard('beyond')->user();
        $assignment = $this->tasks->findAssignmentForUser($assignmentId, $user->id);
        if (! $assignment) {
            return back()->withErrors(['task' => 'Task assignment not found.']);
        }

        $this->tasks->accept($assignment, $request->input('signature'));

        return back()->with('status', 'Task accepted — your signature was recorded.');
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

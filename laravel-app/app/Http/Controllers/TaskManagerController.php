<?php

namespace App\Http\Controllers;

use App\BeyondUser;
use App\Task;
use App\TaskCategory;
use App\TaskMessageTemplate;
use App\TaskReminder;
use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class TaskManagerController extends Controller
{
    protected $tasks;
    protected $all_permission = [];

    public function __construct(TaskService $tasks)
    {
        $this->tasks = $tasks;
        $this->middleware(function ($request, $next) {
            if (Auth::check()) {
                $role = Role::find(Auth::user()->role_id);
                if ($role) {
                    foreach (Role::findByName($role->name)->permissions as $permission) {
                        $this->all_permission[] = $permission->name;
                    }
                }
            }
            View::share('all_permission', $this->all_permission);

            return $next($request);
        });
    }

    protected function authorizeTasks($permission = 'tasks.view')
    {
        if (in_array('tasks_module', $this->all_permission, true)
            || in_array($permission, $this->all_permission, true)) {
            return;
        }
        abort(403, 'You are not allowed to access Task Manager.');
    }

    public function dashboard()
    {
        $this->authorizeTasks('tasks.view');
        $stats = $this->tasks->dashboardStats();

        return view('task_manager.dashboard', compact('stats'));
    }

    public function create()
    {
        $this->authorizeTasks('tasks.create');
        $users = collect($this->tasks->eligibleUsers())->map(function ($u) {
            return is_array($u) ? $u : [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'address' => $u->address ?? '',
                'role' => $u->role ?? '',
                'source' => $u->source ?? 'Portal',
            ];
        })->values();
        $categories = $this->tasks->categories();

        return view('task_manager.create', compact('users', 'categories'));
    }

    public function store(Request $request)
    {
        $this->authorizeTasks('tasks.create');

        $payload = $request->input('tasks', []);
        if (! is_array($payload) || ! count($payload)) {
            return back()->withInput()->with('not_permitted', 'Add at least one task.');
        }

        $rows = [];
        foreach ($payload as $index => $row) {
            $pdfPath = null;
            $pdfName = null;
            $fileKey = 'tasks.' . $index . '.pdf';
            if ($request->hasFile($fileKey)) {
                $file = $request->file($fileKey);
                $dir = public_path('images/task');
                if (! is_dir($dir)) {
                    @mkdir($dir, 0775, true);
                }
                $name = 'task_' . time() . '_' . Str::random(6) . '.' . $file->getClientOriginalExtension();
                $file->move($dir, $name);
                $pdfPath = 'images/task/' . $name;
                $pdfName = $file->getClientOriginalName();
            }

            $rows[] = [
                'subject' => $row['subject'] ?? '',
                'description' => $row['description'] ?? '',
                'priority' => $row['priority'] ?? 'Medium',
                'color' => $row['color'] ?? '#0b3f90',
                'start_date' => $row['start_date'] ?? null,
                'start_time' => $row['start_time'] ?? null,
                'end_date' => $row['end_date'] ?? null,
                'end_time' => $row['end_time'] ?? null,
                'category_id' => $row['category_id'] ?? null,
                'assignee_ids' => $row['assignee_ids'] ?? [],
                'cc_ids' => $row['cc_ids'] ?? [],
                'reminders' => $row['reminders'] ?? [],
                'send_mode' => $row['send_mode'] ?? 'now',
                'schedule_at' => $row['schedule_at'] ?? null,
                'pdf_path' => $pdfPath,
                'pdf_name' => $pdfName,
            ];
        }

        $created = $this->tasks->createTasks($rows, Auth::id());
        if (! count($created)) {
            return back()->withInput()->with('not_permitted', 'Could not create tasks. Ensure each task has a subject and at least one assignee.');
        }

        return redirect()->route('tasks.index')->with('message', count($created) . ' task(s) created successfully.');
    }

    public function index(Request $request)
    {
        $this->authorizeTasks('tasks.view');
        $tasks = $this->tasks->allTasks($request->query('status'), $request->query('q'));

        return view('task_manager.index', compact('tasks'));
    }

    public function scheduled()
    {
        $this->authorizeTasks('tasks.view');
        $tasks = $this->tasks->allTasks('scheduled');

        return view('task_manager.scheduled', compact('tasks'));
    }

    public function reminders()
    {
        $this->authorizeTasks('tasks.view');
        $reminders = TaskReminder::with('task')->orderByDesc('reminder_time')->paginate(40);

        return view('task_manager.reminders', compact('reminders'));
    }

    public function deleteReminder($id)
    {
        $this->authorizeTasks('tasks.update');
        TaskReminder::where('id', $id)->delete();

        return back()->with('message', 'Reminder deleted.');
    }

    public function pendingAcceptances()
    {
        $this->authorizeTasks('tasks.view');
        $assignments = $this->tasks->adminPendingAcceptances();
        $users = BeyondUser::whereIn('id', $assignments->pluck('user_id'))->get()->keyBy('id');

        return view('task_manager.pending', compact('assignments', 'users'));
    }

    public function destroy($id)
    {
        $this->authorizeTasks('tasks.delete');
        $this->tasks->deleteTask($id);

        return back()->with('message', 'Task deleted.');
    }

    public function settings()
    {
        $this->authorizeTasks('tasks.settings');
        $categories = $this->tasks->categories();
        $templates = TaskMessageTemplate::orderBy('name')->get();

        return view('task_manager.settings', compact('categories', 'templates'));
    }

    public function storeCategory(Request $request)
    {
        $this->authorizeTasks('tasks.settings');
        $request->validate(['name' => 'required|string|max:191']);
        TaskCategory::create([
            'id' => (string) Str::uuid(),
            'name' => $request->input('name'),
            'color' => $request->input('color', '#3B82F6'),
            'description' => $request->input('description'),
        ]);

        return back()->with('message', 'Category added.');
    }

    public function destroyCategory($id)
    {
        $this->authorizeTasks('tasks.settings');
        TaskCategory::where('id', $id)->delete();

        return back()->with('message', 'Category deleted.');
    }

    public function storeTemplate(Request $request)
    {
        $this->authorizeTasks('tasks.settings');
        $request->validate(['name' => 'required|string|max:191', 'body' => 'required|string']);
        TaskMessageTemplate::create([
            'id' => (string) Str::uuid(),
            'name' => $request->input('name'),
            'subject' => $request->input('subject'),
            'body' => $request->input('body'),
        ]);

        return back()->with('message', 'Template saved.');
    }

    public function destroyTemplate($id)
    {
        $this->authorizeTasks('tasks.settings');
        TaskMessageTemplate::where('id', $id)->delete();

        return back()->with('message', 'Template deleted.');
    }

    public function searchUsers(Request $request)
    {
        $this->authorizeTasks('tasks.create');
        $users = $this->tasks->eligibleUsers(
            $request->query('filter', 'all'),
            $request->query('q', '')
        );

        return response()->json($users);
    }
}

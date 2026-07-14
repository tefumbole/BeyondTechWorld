<?php

namespace App\Http\Controllers;

use App\EventWorkerCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;

class EventWorkerCategoryController extends Controller
{
    protected $all_permission = [];

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $role = Role::find(Auth::user()->role_id);
            foreach (Role::findByName($role->name)->permissions as $permission) {
                $this->all_permission[] = $permission->name;
            }
            View::share('all_permission', $this->all_permission);

            return $next($request);
        });
    }

    protected function authorizeSettings()
    {
        if (! in_array('events.settings', $this->all_permission, true)) {
            abort(403);
        }
    }

    public function index()
    {
        $this->authorizeSettings();
        $categories = EventWorkerCategory::orderBy('name')->get();

        return view('events.settings.categories', compact('categories'));
    }

    public function store(Request $request)
    {
        $this->authorizeSettings();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:32|unique:event_worker_categories,code',
            'description' => 'nullable|string',
            'default_daily_rate' => 'required|integer|min:0',
            'default_hourly_rate' => 'nullable|integer|min:0',
            'overtime_hourly_rate' => 'nullable|integer|min:0',
            'budget_weight' => 'nullable|integer|min:0|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        EventWorkerCategory::create($data);

        return back()->with('message', 'Category added.');
    }

    public function update(Request $request, $id)
    {
        $this->authorizeSettings();

        $category = EventWorkerCategory::findOrFail($id);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:32|unique:event_worker_categories,code,' . $id,
            'description' => 'nullable|string',
            'default_daily_rate' => 'required|integer|min:0',
            'default_hourly_rate' => 'nullable|integer|min:0',
            'overtime_hourly_rate' => 'nullable|integer|min:0',
            'budget_weight' => 'nullable|integer|min:0|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $category->update($data);

        return back()->with('message', 'Category updated.');
    }

    public function destroy($id)
    {
        $this->authorizeSettings();
        EventWorkerCategory::findOrFail($id)->delete();

        return back()->with('message', 'Category removed.');
    }
}

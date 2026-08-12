<?php

namespace App\Http\Controllers;

use App\OnlineInvitationCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\Rule;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Models\Role;

class OnlineInvitationCategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $role = Role::find(Auth::user()->role_id);
            $permissions = Role::findByName($role->name)->permissions;
            foreach ($permissions as $permission) {
                $all_permission[] = $permission->name;
            }
            View::share('all_permission', $all_permission ?? []);
            return $next($request);
        });
    }

    private function ensureAccess()
    {
        try {
            $role = Role::find(Auth::user()->role_id);
            return $role && $role->hasPermissionTo('online_invitation_category');
        } catch (PermissionDoesNotExist $e) {
            return false;
        }
    }

    public function index()
    {
        if (!$this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $data = OnlineInvitationCategory::where('is_active', 1)->orderBy('id', 'desc')->get();
        return view('online_invitation.category.index', compact('data'));
    }

    public function create()
    {
        if (!$this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }
        return view('online_invitation.category.create');
    }

    public function store(Request $request)
    {
        if (!$this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $request->name = preg_replace('/\s+/', ' ', $request->name);
        $this->validate($request, [
            'name' => [
                'required',
                'max:255',
                Rule::unique('online_invitation_categories')->where(function ($query) {
                    return $query->where('is_active', 1);
                }),
            ],
        ]);

        OnlineInvitationCategory::create([
            'name' => $request->name,
            'is_active' => 1,
        ]);

        return redirect()->route('online_invitation.categories.index')->with('message', 'Category created successfully');
    }

    public function edit($id)
    {
        if (!$this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }
        $data = OnlineInvitationCategory::findOrFail($id);
        return view('online_invitation.category.edit', compact('data'));
    }

    public function update(Request $request, $id)
    {
        if (!$this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $request->name = preg_replace('/\s+/', ' ', $request->name);
        $this->validate($request, [
            'name' => [
                'required',
                'max:255',
                Rule::unique('online_invitation_categories')->ignore($id)->where(function ($query) {
                    return $query->where('is_active', 1);
                }),
            ],
        ]);

        $category = OnlineInvitationCategory::findOrFail($id);
        $category->update(['name' => $request->name]);

        return redirect()->route('online_invitation.categories.index')->with('message', 'Category updated successfully');
    }

    public function destroy($id)
    {
        if (!$this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $category = OnlineInvitationCategory::findOrFail($id);
        $category->update(['is_active' => 0]);

        return redirect()->route('online_invitation.categories.index')->with('message', 'Category deleted successfully');
    }
}


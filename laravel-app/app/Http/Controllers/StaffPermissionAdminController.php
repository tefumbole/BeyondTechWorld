<?php

namespace App\Http\Controllers;

use App\Services\StaffPermissionLetterService;
use App\StaffPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;

class StaffPermissionAdminController extends Controller
{
    protected $all_permission = [];

    public function __construct()
    {
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

    protected function authorizePerms()
    {
        if (in_array('permissions_module', $this->all_permission, true)
            || in_array('permissions.view', $this->all_permission, true)
            || in_array('permissions.manage', $this->all_permission, true)
            || in_array('hrm', $this->all_permission, true)) {
            return;
        }
        abort(403, 'You are not allowed to access Permissions.');
    }

    protected function canManage()
    {
        return in_array('permissions.manage', $this->all_permission, true)
            || in_array('permissions_module', $this->all_permission, true)
            || in_array('hrm', $this->all_permission, true);
    }

    public function requests(Request $request)
    {
        return $this->list($request, StaffPermission::STATUS_PENDING, 'Awaiting Approval', 'permissions.requests');
    }

    public function approved(Request $request)
    {
        return $this->list($request, StaffPermission::STATUS_APPROVED, 'Approved Permissions', 'permissions.approved');
    }

    public function denied(Request $request)
    {
        return $this->list($request, StaffPermission::STATUS_REJECTED, 'Denied Permissions', 'permissions.denied');
    }

    public function index(Request $request)
    {
        return $this->list($request, 'all', 'Permissions Listings', 'permissions.index');
    }

    public function show($id)
    {
        $this->authorizePerms();
        $item = StaffPermission::with('reviewer', 'letter')->findOrFail($id);

        return view('staff_permissions.show', [
            'item' => $item,
            'pageTitle' => $item->isPending() ? 'Review permission' : 'Permission '.$item->statusLabel(),
            'permTab' => $item->status === StaffPermission::STATUS_PENDING
                ? 'permissions.requests'
                : ($item->status === StaffPermission::STATUS_APPROVED
                    ? 'permissions.approved'
                    : ($item->status === StaffPermission::STATUS_REJECTED
                        ? 'permissions.denied'
                        : 'permissions.index')),
            'defaultFooter' => StaffPermissionLetterService::defaultFooter(Auth::user()),
            'canManage' => $this->canManage(),
        ]);
    }

    protected function list(Request $request, $status, $title, $tab)
    {
        $this->authorizePerms();
        $q = StaffPermission::orderByDesc('created_at');
        if ($status !== 'all') {
            $q->where('status', $status);
        }
        if ($request->get('q')) {
            $search = $request->get('q');
            $q->where(function ($w) use ($search) {
                $w->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('company_role', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%");
            });
        }

        return view('staff_permissions.index', [
            'items' => $q->paginate(40),
            'pageTitle' => $title,
            'statusFilter' => $status,
            'q' => $request->get('q'),
            'permTab' => $tab,
            'canManage' => $this->canManage(),
        ]);
    }

    public function update(Request $request, $id, StaffPermissionLetterService $letters)
    {
        $this->authorizePerms();
        if (! $this->canManage()) {
            abort(403);
        }

        $data = $request->validate([
            'status' => 'required|in:approved,rejected',
            'admin_note' => 'nullable|string|max:2000',
            'instructions' => 'nullable|string|max:5000',
            'letter_footer' => 'nullable|string|max:2000',
        ]);

        $item = StaffPermission::findOrFail($id);
        if (! $item->isPending()) {
            return redirect()->route('permissions.show', $item->id)
                ->with('not_permitted', 'This request has already been reviewed.');
        }

        $approved = $data['status'] === StaffPermission::STATUS_APPROVED;
        $footer = trim((string) ($data['letter_footer'] ?? ''));
        if ($approved && $footer === '') {
            return back()->withInput()->withErrors([
                'letter_footer' => 'Add footer information (name, title, company) so it prints on the letter.',
            ]);
        }
        if ($footer === '') {
            $footer = StaffPermissionLetterService::defaultFooter(Auth::user());
        }

        $item->status = $data['status'];
        $item->admin_note = $data['admin_note'] ?? $item->admin_note;
        $item->instructions = $data['instructions'] ?? null;
        $item->letter_footer = $footer;
        $item->reviewed_by = Auth::id();
        $item->reviewed_at = now();
        $item->save();

        $issued = $letters->issueDecisionLetter($item, Auth::user(), $approved);
        $flash = $approved
            ? 'Permission approved. The letter is being sent.'
            : 'Permission denied. The letter is being sent.';
        if (! empty($issued['warnings'])) {
            $flash .= ' '.implode(' ', $issued['warnings']);
        }
        if (empty($issued['ok'])) {
            Log::warning('Permission letter was not queued', [
                'permission_id' => $item->id,
                'error' => $issued['error'] ?? 'unknown',
            ]);
            $flash = ($approved ? 'Permission approved' : 'Permission denied')
                .', but the letter could not be queued: '.($issued['error'] ?: 'unknown error');
        }

        $tab = $approved ? 'permissions.approved' : 'permissions.denied';

        return redirect()->route($tab)->with('message', $flash);
    }
}

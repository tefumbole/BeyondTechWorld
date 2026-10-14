<?php

namespace App\Http\Controllers;

use App\BeyondUser;
use App\CaroInvitation;
use App\Services\CaroInvitationService;
use App\Support\CaroSeats;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CaroAdminController extends Controller
{
    protected $invitations;

    public function __construct(CaroInvitationService $invitations)
    {
        $this->invitations = $invitations;
    }

    public function showLogin()
    {
        if ($this->signedIn()) {
            return redirect($this->invitations->pageUrl('admin'));
        }

        return view('beyond.caro.admin-login', [
            'error' => session('error'),
        ]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'identifier' => 'required|string|max:190',
            'password' => 'required|string|max:190',
        ]);
        $admin = $this->matchAdmin($data['identifier'], $data['password']);
        if (! $admin) {
            return back()->withInput()->with('error', 'Those details are not an admin login.');
        }
        $request->session()->regenerate();
        $request->session()->put('caro_admin_id', $admin['id']);
        $request->session()->put('caro_admin_name', $admin['name']);

        return redirect($this->invitations->pageUrl('admin'));
    }

    public function logout(Request $request)
    {
        $request->session()->forget(['caro_admin_id', 'caro_admin_name']);
        $request->session()->regenerate();

        return redirect($this->invitations->pageUrl('admin/login'));
    }

    public function index(Request $request)
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }
        $q = trim((string) $request->query('q', ''));
        $rows = CaroInvitation::query()->orderBy('created_at', 'desc');
        if ($q !== '') {
            $like = '%'.$q.'%';
            $rows->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('seat_code', 'like', $like);
            });
        }

        return view('beyond.caro.admin', [
            'rows' => $rows->get(),
            'q' => $q,
            'adminName' => session('caro_admin_name'),
            'logoutUrl' => $this->invitations->pageUrl('admin/logout'),
            'homeUrl' => $this->invitations->pageUrl(),
            'notice' => session('notice'),
        ]);
    }

    public function updateSeat(Request $request, $id)
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }
        $row = CaroInvitation::find($id);
        if (! $row) {
            abort(404);
        }
        try {
            $this->invitations->retakeSeat($row, $request->input('seat', ''));
        } catch (\InvalidArgumentException $e) {
            return back()->with('notice', $e->getMessage());
        } catch (\Throwable $e) {
            return back()->with('notice', 'The seat could not be changed.');
        }

        return back()->with('notice', $row->name.' is now in '.CaroSeats::label($row->seat_code).'.');
    }

    public function destroy(Request $request, $id)
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }
        $row = CaroInvitation::find($id);
        if ($row) {
            $path = $row->imagePath();
            if (is_file($path)) {
                @unlink($path);
            }
            $row->delete();
        }

        return back()->with('notice', 'Invitation removed. That number can book again.');
    }

    protected function guard()
    {
        if ($this->signedIn()) {
            return null;
        }

        return redirect($this->invitations->pageUrl('admin/login'));
    }

    protected function signedIn()
    {
        return session('caro_admin_id') !== null && session('caro_admin_id') !== '';
    }

    protected function matchAdmin($identifier, $password)
    {
        $identifier = trim($identifier);
        $staff = User::query()
            ->where('is_active', 1)
            ->where(function ($query) {
                $query->where('is_deleted', 0)->orWhereNull('is_deleted');
            })
            ->where(function ($query) use ($identifier) {
                $query->where('email', $identifier)->orWhere('username', $identifier);
            })
            ->first();
        if ($staff && Hash::check($password, $staff->password) && $this->staffIsAdmin($staff)) {
            return ['id' => 'staff:'.$staff->id, 'name' => $staff->name];
        }

        $beyond = BeyondUser::query()
            ->where(function ($query) use ($identifier) {
                $query->where('email', $identifier)->orWhere('username', $identifier);
            })
            ->first();
        if ($beyond && $this->beyondActive($beyond) && Hash::check($password, $beyond->password_hash) && $this->beyondIsAdmin($beyond)) {
            return ['id' => 'beyond:'.$beyond->id, 'name' => $beyond->name];
        }

        return null;
    }

    protected function staffIsAdmin($staff)
    {
        if (in_array((int) $staff->role_id, [1, 2], true)) {
            return true;
        }
        $role = \Spatie\Permission\Models\Role::find($staff->role_id);
        $name = $role ? strtolower((string) $role->name) : '';

        return strpos($name, 'admin') !== false;
    }

    protected function beyondActive($user)
    {
        $status = strtolower(trim((string) $user->status));

        return $status === '' || $status === 'active';
    }

    protected function beyondIsAdmin($user)
    {
        return in_array(strtolower((string) $user->role), ['admin', 'super_admin', 'director', 'manager'], true);
    }
}

<?php

namespace App\Http\Controllers\Mai;

use App\Http\Controllers\Controller;
use App\Mai\MaiApiKey;
use App\Services\Mai\MaiDashboardQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class MaiDashboardController extends Controller
{
    public function index(Request $request)
    {
        $this->guard();
        $query = app(MaiDashboardQuery::class);
        $account = null;
        if ($request->filled('account')) {
            $account = $query->account((int) $request->input('account'));
        }

        return view('mai.dashboard', [
            'summary' => $query->summary(),
            'list' => $query->accounts(
                trim((string) $request->input('q')),
                trim((string) $request->input('filter', 'all')),
                (int) $request->input('page', 1)
            ),
            'account' => $account,
            'filter' => trim((string) $request->input('filter', 'all')),
            'q' => trim((string) $request->input('q')),
        ]);
    }

    public function revoke(Request $request, $id)
    {
        $this->guard();
        $key = MaiApiKey::find($id);
        if (! $key) {
            return redirect()->route('whatsapp.mai.dashboard');
        }
        $key->revoked_at = now();
        $key->save();
        if (Schema::hasTable('mai_operator_actions')) {
            DB::table('mai_operator_actions')->insert([
                'operator_user_id' => Auth::id(),
                'action' => 'revoke_token',
                'target_type' => 'mai_api_key',
                'target_id' => $key->id,
                'note' => substr((string) $key->prefix, 0, 12),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return redirect()->route('whatsapp.mai.dashboard', ['account' => $key->user_id])
            ->with('message', 'Token revoked. The secret was not shown.');
    }

    public static function allows($user)
    {
        if (! $user) {
            return false;
        }
        if (in_array((int) $user->role_id, [1, 2], true)) {
            return true;
        }
        try {
            $role = Role::find($user->role_id);
            if ($role && $role->hasPermissionTo('whatsapp.manage')) {
                return true;
            }
        } catch (\Throwable $e) {
        }

        return false;
    }

    protected function guard()
    {
        if (! self::allows(Auth::user())) {
            abort(403);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\BtwContract;
use App\ContractSetting;
use App\ContractType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;

class ContractDashboardController extends Controller
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

    protected function gate($extra = [])
    {
        $need = array_merge(['contracts.dashboard', 'contracts.view', 'contracts_module'], (array) $extra);
        foreach ($need as $n) {
            if (in_array($n, $this->all_permission, true)) {
                return;
            }
        }
        abort(403);
    }

    public function index()
    {
        $this->gate();
        $days = (int) ContractSetting::getValue('expiry_alert_days', 30);
        $now = Carbon::today();
        $horizon = $now->copy()->addDays(max(1, $days));

        $byStatus = BtwContract::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')->pluck('total', 'status');

        $byType = BtwContract::query()
            ->join('contract_types', 'contracts.type_id', '=', 'contract_types.id')
            ->select('contract_types.name', DB::raw('count(*) as total'))
            ->groupBy('contract_types.name')
            ->orderByDesc('total')
            ->pluck('total', 'name');

        $awaitingClient = (int) ($byStatus[BtwContract::STATUS_AWAITING_CLIENT] ?? 0);
        $awaitingAdmin = (int) ($byStatus[BtwContract::STATUS_AWAITING_ADMIN] ?? 0);
        $signed = (int) ($byStatus[BtwContract::STATUS_SIGNED] ?? 0);
        $draft = (int) ($byStatus[BtwContract::STATUS_DRAFT] ?? 0)
            + (int) ($byStatus[BtwContract::STATUS_IN_REVIEW] ?? 0)
            + (int) ($byStatus[BtwContract::STATUS_READY_TO_SEND] ?? 0);

        $signedThisMonth = BtwContract::where('status', BtwContract::STATUS_SIGNED)
            ->where('signed_at', '>=', $now->copy()->startOfMonth())
            ->count();

        $expiring = BtwContract::with(['type', 'partyB'])
            ->whereNotNull('end_date')
            ->whereDate('end_date', '>=', $now)
            ->whereDate('end_date', '<=', $horizon)
            ->whereNotIn('status', [BtwContract::STATUS_CANCELLED, BtwContract::STATUS_SUPERSEDED])
            ->orderBy('end_date')
            ->limit(50)
            ->get();

        $recentSigned = BtwContract::with(['type', 'partyB'])
            ->where('status', BtwContract::STATUS_SIGNED)
            ->orderByDesc('signed_at')
            ->limit(10)
            ->get();

        $staleAwaiting = BtwContract::with(['type', 'partyB'])
            ->whereIn('status', [BtwContract::STATUS_AWAITING_CLIENT, BtwContract::STATUS_AWAITING_ADMIN])
            ->where(function ($q) {
                $q->where('sent_at', '<=', now()->subDays(3))
                    ->orWhere(function ($w) {
                        $w->whereNull('sent_at')->where('updated_at', '<=', now()->subDays(3));
                    });
            })
            ->orderBy('sent_at')
            ->limit(20)
            ->get();

        return view('contracts.dashboard', [
            'ctTab' => 'contracts.dashboard',
            'awaitingClient' => $awaitingClient,
            'awaitingAdmin' => $awaitingAdmin,
            'signed' => $signed,
            'draft' => $draft,
            'signedThisMonth' => $signedThisMonth,
            'byStatus' => $byStatus,
            'byType' => $byType,
            'expiring' => $expiring,
            'expiryDays' => $days,
            'recentSigned' => $recentSigned,
            'staleAwaiting' => $staleAwaiting,
            'total' => BtwContract::count(),
        ]);
    }

    public function report(Request $request)
    {
        $this->gate(['contracts.report']);
        $query = BtwContract::with(['type', 'partyA', 'partyB'])->orderByDesc('created_at');
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('type_id')) {
            $query->where('type_id', $request->type_id);
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        if ($request->get('export') === 'csv') {
            $rows = $query->limit(5000)->get();
            $csv = "Number,Title,Type,Status,Party A,Party B,Value,Currency,Effective,Start,End,Signed At\n";
            foreach ($rows as $c) {
                $csv .= '"'.implode('","', [
                    $c->number,
                    str_replace('"', '""', (string) $c->title),
                    optional($c->type)->name,
                    $c->status,
                    str_replace('"', '""', (string) (optional($c->partyA)->snapshot()['name'] ?? '')),
                    str_replace('"', '""', (string) (optional($c->partyB)->snapshot()['name'] ?? '')),
                    $c->value,
                    $c->currency,
                    optional($c->effective_date)->format('Y-m-d'),
                    optional($c->start_date)->format('Y-m-d'),
                    optional($c->end_date)->format('Y-m-d'),
                    optional($c->signed_at)->format('Y-m-d H:i'),
                ])."\"\n";
            }

            return Response::make($csv, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="contracts-report-'.date('Ymd').'.csv"',
            ]);
        }

        return view('contracts.report', [
            'ctTab' => 'contracts.dashboard',
            'contracts' => $query->paginate(40),
            'types' => ContractType::where('active', true)->orderBy('name')->get(),
            'filters' => $request->only(['status', 'type_id', 'from', 'to']),
        ]);
    }
}

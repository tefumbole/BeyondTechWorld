<?php

namespace App\Http\Controllers;

use App\Hotspot\HotspotAuditLog;
use App\Hotspot\HotspotLimits;
use App\Hotspot\HotspotLocation;
use App\Hotspot\HotspotMessageTemplate;
use App\Hotspot\HotspotNotifier;
use App\Hotspot\HotspotPackage;
use App\Hotspot\HotspotPayment;
use App\Hotspot\HotspotRouter;
use App\Hotspot\HotspotRouterService;
use App\Hotspot\HotspotSession;
use App\Hotspot\HotspotSubscription;
use App\Hotspot\HotspotVoucher;
use App\Hotspot\HotspotVoucherBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;

class HotspotAdminController extends Controller
{
    protected $permissions = [];

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (Auth::check()) {
                $role = Role::find(Auth::user()->role_id);
                if ($role) {
                    foreach (Role::findByName($role->name)->permissions as $permission) {
                        $this->permissions[] = $permission->name;
                    }
                }
            }
            View::share('all_permission', $this->permissions);
            if (! $this->can('hotspot.view') && ! $this->can('hotspot.manage') && ! $this->can('hotspot.routers')) {
                return redirect('dashboard')->with('not_permitted', 'Sorry! You are not allowed to access this module');
            }

            return $next($request);
        });
    }

    public function index()
    {
        app(HotspotNotifier::class)->ensureTemplates();
        $fromDay = now()->startOfDay();
        $paid = HotspotPayment::where('status', 'paid');

        return view('hotspot.admin.dashboard', [
            'locations' => HotspotLocation::count(),
            'packages' => HotspotPackage::count(),
            'routers' => HotspotRouter::orderBy('name')->get(),
            'activeSubscriptions' => HotspotSubscription::where('state', 'active')->count(),
            'expiredSubscriptions' => HotspotSubscription::where('state', 'expired')->count(),
            'onlineSessions' => HotspotSession::whereNull('stopped_at')->count(),
            'revenueToday' => (clone $paid)->where('paid_at', '>=', $fromDay)->sum('amount'),
            'revenueWeek' => (clone $paid)->where('paid_at', '>=', now()->startOfWeek())->sum('amount'),
            'revenueMonth' => (clone $paid)->where('paid_at', '>=', now()->startOfMonth())->sum('amount'),
            'recent' => HotspotPayment::orderByDesc('id')->limit(8)->get(),
        ]);
    }

    public function locations()
    {
        return view('hotspot.admin.locations', ['rows' => HotspotLocation::orderBy('name')->get()]);
    }

    public function storeLocation(Request $request)
    {
        $this->manage();
        $data = $this->validateLocation($request);
        $row = new HotspotLocation();
        $row->fill($data);
        $row->save();

        return back()->with('hotspot_status', 'Location saved.');
    }

    public function updateLocation(Request $request, $id)
    {
        $this->manage();
        $row = HotspotLocation::findOrFail($id);
        $row->fill($this->validateLocation($request));
        $row->save();

        return back()->with('hotspot_status', 'Location updated.');
    }

    public function packages()
    {
        return view('hotspot.admin.packages', [
            'rows' => HotspotPackage::orderBy('price')->orderBy('validity_seconds')->get(),
            'locations' => HotspotLocation::orderBy('name')->get(),
        ]);
    }

    public function storePackage(Request $request)
    {
        $this->manage();
        $row = new HotspotPackage();
        $row->fill($this->packageInput($request));
        $row->save();
        HotspotAuditLog::write('package.create', 'Created package '.$row->name);

        return back()->with('hotspot_status', 'Package saved. Set the price yourself; nothing is priced in advance.');
    }

    public function updatePackage(Request $request, $id)
    {
        $this->manage();
        $row = HotspotPackage::findOrFail($id);
        $row->fill($this->packageInput($request));
        $row->save();
        HotspotAuditLog::write('package.update', 'Updated package '.$row->name);

        return back()->with('hotspot_status', 'Package updated.');
    }

    public function pushProfile(Request $request, $id, HotspotRouterService $routers)
    {
        $this->manage();
        $package = HotspotPackage::findOrFail($id);
        $router = HotspotRouter::findOrFail($request->input('router_id'));
        try {
            $routers->pushProfile($router, $package);
        } catch (\Throwable $e) {
            return back()->with('hotspot_error', $e->getMessage());
        }

        return back()->with('hotspot_status', 'Rate profile sent. Firewall, NAT, and the hotspot server were left unchanged.');
    }

    public function routers()
    {
        $this->routersAllowed();

        return view('hotspot.admin.routers', [
            'rows' => HotspotRouter::orderBy('name')->get(),
            'locations' => HotspotLocation::orderBy('name')->get(),
        ]);
    }

    public function storeRouter(Request $request)
    {
        $this->routersAllowed();
        $row = new HotspotRouter();
        $this->fillRouter($row, $request);
        $row->status = 'offline';
        $row->save();
        HotspotAuditLog::write('router.create', 'Saved router '.$row->name, $row->id);

        return back()->with('hotspot_status', 'Router saved. It stays offline until the tunnel answers.');
    }

    public function updateRouter(Request $request, $id)
    {
        $this->routersAllowed();
        $row = HotspotRouter::findOrFail($id);
        $this->fillRouter($row, $request);
        $row->save();
        HotspotAuditLog::write('router.update', 'Updated router credentials for '.$row->name, $row->id);

        return back()->with('hotspot_status', 'Router updated.');
    }

    public function testRouter($id, HotspotRouterService $routers)
    {
        $this->routersAllowed();
        $router = HotspotRouter::findOrFail($id);
        $ok = $routers->test($router);

        return back()->with($ok ? 'hotspot_status' : 'hotspot_error', $ok ? $router->name.' is online.' : ($router->fresh()->last_error ?: 'The router is offline.'));
    }

    public function sessions(Request $request, HotspotRouterService $routers)
    {
        $rows = HotspotRouter::orderBy('name')->get();
        $sessions = collect();
        $notice = null;
        if ($request->filled('router_id')) {
            $router = HotspotRouter::findOrFail($request->input('router_id'));
            try {
                $sessions = $routers->activeSessions($router);
            } catch (\Throwable $e) {
                $router->status = 'offline';
                $router->last_error = substr($e->getMessage(), 0, 180);
                $router->save();
                $notice = $router->last_error;
                $sessions = HotspotSession::where('router_id', $router->id)->whereNull('stopped_at')->get();
            }
        }

        return view('hotspot.admin.sessions', ['routers' => $rows, 'sessions' => $sessions, 'notice' => $notice]);
    }

    public function disconnect(Request $request, HotspotRouterService $routers)
    {
        $this->manage();
        $router = HotspotRouter::findOrFail($request->input('router_id'));
        try {
            $routers->disconnect($router, $request->input('username'));
        } catch (\Throwable $e) {
            return back()->with('hotspot_error', $e->getMessage());
        }

        return back()->with('hotspot_status', 'Disconnect sent.');
    }

    public function subscriptions()
    {
        return view('hotspot.admin.subscriptions', [
            'rows' => HotspotSubscription::orderByDesc('id')->limit(100)->get(),
        ]);
    }

    public function vouchers()
    {
        return view('hotspot.admin.vouchers', [
            'packages' => HotspotPackage::orderBy('name')->get(),
            'batches' => HotspotVoucherBatch::orderByDesc('id')->limit(20)->get(),
            'rows' => HotspotVoucher::orderByDesc('id')->limit(100)->get(),
        ]);
    }

    public function storeVouchers(Request $request)
    {
        $this->manage();
        $package = HotspotPackage::findOrFail($request->input('package_id'));
        $quantity = max(1, min(200, (int) $request->input('quantity')));
        $batch = new HotspotVoucherBatch();
        $batch->package_id = $package->id;
        $batch->quantity = $quantity;
        $batch->note = $request->input('note');
        $batch->created_by = Auth::id();
        $batch->save();
        for ($i = 0; $i < $quantity; $i++) {
            $code = HotspotLimits::voucherCode();
            while (HotspotVoucher::where('code', $code)->exists()) {
                $code = HotspotLimits::voucherCode();
            }
            $voucher = new HotspotVoucher();
            $voucher->package_id = $package->id;
            $voucher->batch_id = $batch->id;
            $voucher->code = $code;
            $voucher->state = 'available';
            $voucher->save();
        }
        HotspotAuditLog::write('voucher.batch', 'Created '.$quantity.' vouchers for '.$package->name);

        return redirect()->route('hotspot.vouchers.print', ['batch' => $batch->id]);
    }

    public function printVouchers($batch)
    {
        $batch = HotspotVoucherBatch::findOrFail($batch);

        return view('hotspot.admin.vouchers_print', [
            'batch' => $batch,
            'rows' => HotspotVoucher::where('batch_id', $batch->id)->orderBy('code')->get(),
            'package' => $batch->package,
        ]);
    }

    public function exportVouchers()
    {
        $rows = HotspotVoucher::orderBy('code')->get();
        $lines = ["code,package,state,redeemed_at"];
        foreach ($rows as $row) {
            $lines[] = $row->code.','.optional($row->package)->name.','.$row->state.','.($row->redeemed_at ? $row->redeemed_at->toDateTimeString() : '');
        }

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="hotspot-vouchers.csv"',
        ]);
    }

    public function revokeVoucher($id)
    {
        $this->manage();
        $voucher = HotspotVoucher::findOrFail($id);
        $voucher->state = 'revoked';
        $voucher->save();
        if ($voucher->subscription_id) {
            $subscription = HotspotSubscription::find($voucher->subscription_id);
            if ($subscription && $subscription->state === 'active') {
                $subscription->state = 'suspended';
                $subscription->save();
                try {
                    app(\App\Hotspot\HotspotProvisioner::class)->revoke($subscription);
                } catch (\Throwable $e) {
                    \App\Jobs\ProvisionHotspotAccess::dispatch($subscription->id, true);
                }
            }
        }
        HotspotAuditLog::write('voucher.revoke', 'Revoked '.$voucher->code);

        return back()->with('hotspot_status', 'Voucher revoked. It cannot be used to log in.');
    }

    public function templates(HotspotNotifier $notifier)
    {
        $this->manage();
        $notifier->ensureTemplates();

        return view('hotspot.admin.templates', ['rows' => HotspotMessageTemplate::orderBy('key')->get()]);
    }

    public function saveTemplate(Request $request, $id)
    {
        $this->manage();
        $row = HotspotMessageTemplate::findOrFail($id);
        $row->body = $request->input('body');
        $row->hours_before = $request->filled('hours_before') ? (int) $request->input('hours_before') : null;
        $row->save();

        return back()->with('hotspot_status', 'Template saved.');
    }

    public function reports(Request $request)
    {
        $period = $request->input('period', 'month');
        $from = $period === 'day' ? now()->startOfDay() : ($period === 'week' ? now()->startOfWeek() : now()->startOfMonth());
        $payments = HotspotPayment::where('status', 'paid')->where('paid_at', '>=', $from)->get();
        $byPackage = [];
        $byLocation = [];
        $byMethod = [];
        foreach ($payments as $payment) {
            $package = optional($payment->package)->name ?: 'Package';
            $location = optional(HotspotLocation::find($payment->location_id))->name ?: 'All locations';
            $byPackage[$package] = (isset($byPackage[$package]) ? $byPackage[$package] : 0) + (int) $payment->amount;
            $byLocation[$location] = (isset($byLocation[$location]) ? $byLocation[$location] : 0) + (int) $payment->amount;
            $byMethod[$payment->method] = (isset($byMethod[$payment->method]) ? $byMethod[$payment->method] : 0) + (int) $payment->amount;
        }

        return view('hotspot.admin.reports', [
            'period' => $period,
            'total' => $payments->sum('amount'),
            'count' => $payments->count(),
            'byPackage' => $byPackage,
            'byLocation' => $byLocation,
            'byMethod' => $byMethod,
            'payments' => $payments,
        ]);
    }

    public function guide()
    {
        return view('hotspot.admin.guide', [
            'support' => app(\App\Hotspot\HotspotBillingService::class)->supportPhone(),
        ]);
    }

    protected function validateLocation(Request $request)
    {
        $request->validate(['name' => 'required|string|max:120', 'timezone' => 'nullable|string|max:60']);

        return [
            'name' => $request->input('name'),
            'address' => $request->input('address'),
            'timezone' => $request->input('timezone') ?: 'Africa/Douala',
            'active' => $request->input('active') === '1' || $request->input('active') === 'on',
        ];
    }

    protected function packageInput(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:120',
            'price' => 'required|integer|min:100',
            'validity_value' => 'required|integer|min:1',
            'validity_unit' => 'required|in:minutes,hours,days,weeks,months',
            'data_gb' => 'nullable|numeric|min:0.01',
        ]);
        $multipliers = ['minutes' => 60, 'hours' => 3600, 'days' => 86400, 'weeks' => 604800, 'months' => 2592000];
        $unit = $request->input('validity_unit');
        $seconds = (int) $request->input('validity_value') * $multipliers[$unit];
        $data = null;
        if ($request->filled('data_gb') && (float) $request->input('data_gb') > 0) {
            $data = (int) round((float) $request->input('data_gb') * 1073741824);
        }
        $uploadOpen = $request->input('unlimited_up') === '1';
        $downloadOpen = $request->input('unlimited_down') === '1';

        return [
            'location_id' => $request->input('location_id') ?: null,
            'name' => $request->input('name'),
            'price' => (int) $request->input('price'),
            'currency' => 'XAF',
            'validity_seconds' => $seconds,
            'data_bytes' => $data,
            'rate_up' => $uploadOpen ? null : max(1, (int) $request->input('rate_up', 1024)),
            'rate_down' => $downloadOpen ? null : max(1, (int) $request->input('rate_down', 2048)),
            'shared_users' => max(1, (int) $request->input('shared_users', 1)),
            'simultaneous_sessions' => max(1, (int) $request->input('simultaneous_sessions', 1)),
            'active' => $request->input('active') === '1',
        ];
    }

    protected function fillRouter(HotspotRouter $row, Request $request)
    {
        $request->validate([
            'location_id' => 'required|integer',
            'name' => 'required|string|max:120',
            'api_host' => 'required|string|max:64',
            'api_port' => 'nullable|integer|min:1|max:65535',
            'api_user' => 'required|string|max:80',
        ]);
        if (! HotspotLimits::acceptsManagementHost($request->input('api_host'))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'api_host' => 'Use the tunnel address only. A public IP is not stored as the management path.',
            ]);
        }
        HotspotLocation::findOrFail($request->input('location_id'));
        $row->location_id = $request->input('location_id');
        $row->name = $request->input('name');
        $row->identity = $request->input('identity');
        $row->api_host = $request->input('api_host');
        $row->api_port = $request->input('api_port') ?: 8728;
        $row->api_user = $request->input('api_user');
        $row->ros_version = $request->input('ros_version');
        if ($request->filled('api_password')) {
            $row->api_password = $request->input('api_password');
        }
        if ($request->filled('radius_secret')) {
            $row->radius_secret = $request->input('radius_secret');
        }
    }

    protected function manage()
    {
        if (! $this->can('hotspot.manage')) {
            abort(403);
        }
    }

    protected function routersAllowed()
    {
        if (! $this->can('hotspot.routers') && ! $this->can('hotspot.manage')) {
            abort(403);
        }
    }

    protected function can($permission)
    {
        return in_array($permission, $this->permissions, true);
    }
}

<?php

namespace App\Http\Controllers;

use App\MessageDeliveryBatch;
use App\Services\MessageDeliveryTracker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class MessageDeliveryController extends Controller
{
    protected $all_permission = [];

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! Auth::check()) {
                return redirect('/login');
            }
            $role = Role::find(Auth::user()->role_id);
            if ($role) {
                foreach ($role->permissions as $permission) {
                    $this->all_permission[] = $permission->name;
                }
            }
            if ((int) Auth::user()->role_id > 2
                && ! in_array('letter_send_index', $this->all_permission, true)
                && ! in_array('letter_index', $this->all_permission, true)
                && ! in_array('letter_sign_index', $this->all_permission, true)) {
                abort(403, 'Not permitted');
            }

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        if (! app(MessageDeliveryTracker::class)->enabled()) {
            return view('message_delivery.index', [
                'batches' => collect(),
                'activeCount' => 0,
                'tablesMissing' => true,
                'all_permission' => $this->all_permission,
            ]);
        }

        $batches = MessageDeliveryBatch::with('letter')
            ->orderByDesc('id')
            ->paginate(25);

        $activeCount = MessageDeliveryBatch::whereIn('status', ['queued', 'sending'])->count();

        return view('message_delivery.index', [
            'batches' => $batches,
            'activeCount' => $activeCount,
            'tablesMissing' => false,
            'all_permission' => $this->all_permission,
        ]);
    }

    public function show($id)
    {
        $batch = MessageDeliveryBatch::with(['items' => function ($q) {
            $q->orderBy('id');
        }, 'letter', 'queuedBy'])->findOrFail($id);

        return view('message_delivery.show', [
            'batch' => $batch,
            'all_permission' => $this->all_permission,
        ]);
    }

    /**
     * JSON poll endpoint for live progress.
     */
    public function status(Request $request)
    {
        if (! app(MessageDeliveryTracker::class)->enabled()) {
            return response()->json(['batches' => [], 'active' => 0]);
        }

        $ids = array_filter(array_map('intval', (array) $request->get('ids', [])));
        $q = MessageDeliveryBatch::query()->orderByDesc('id');
        if ($ids) {
            $q->whereIn('id', $ids);
        } else {
            $q->where(function ($w) {
                $w->whereIn('status', ['queued', 'sending'])
                    ->orWhere('updated_at', '>=', now()->subMinutes(30));
            })->limit(40);
        }

        $batches = $q->get()->map(function (MessageDeliveryBatch $b) {
            return [
                'id' => $b->id,
                'uuid' => $b->uuid,
                'title' => $b->title,
                'letter_id' => $b->letter_id,
                'status' => $b->status,
                'total' => (int) $b->total,
                'sent_count' => (int) $b->sent_count,
                'failed_count' => (int) $b->failed_count,
                'progress' => $b->progressPercent(),
                'active' => $b->isActive(),
                'started_at' => optional($b->started_at)->toDateTimeString(),
                'finished_at' => optional($b->finished_at)->toDateTimeString(),
                'updated_at' => optional($b->updated_at)->toDateTimeString(),
                'url' => route('message.delivery.show', $b->id),
            ];
        });

        $active = MessageDeliveryBatch::whereIn('status', ['queued', 'sending'])->count();

        return response()->json([
            'batches' => $batches,
            'active' => $active,
            'server_time' => now()->toDateTimeString(),
        ]);
    }

    public function itemStatus($id)
    {
        $batch = MessageDeliveryBatch::with(['items' => function ($q) {
            $q->orderBy('id');
        }])->findOrFail($id);

        return response()->json([
            'id' => $batch->id,
            'status' => $batch->status,
            'total' => (int) $batch->total,
            'sent_count' => (int) $batch->sent_count,
            'failed_count' => (int) $batch->failed_count,
            'progress' => $batch->progressPercent(),
            'active' => $batch->isActive(),
            'items' => $batch->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'recipient_name' => $item->recipient_name,
                    'phone' => $item->phone,
                    'email' => $item->email,
                    'role' => $item->role,
                    'channel' => $item->channel,
                    'status' => $item->status,
                    'error' => $item->error,
                    'sent_at' => optional($item->sent_at)->toDateTimeString(),
                ];
            }),
        ]);
    }
}

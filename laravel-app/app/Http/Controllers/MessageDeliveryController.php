<?php

namespace App\Http\Controllers;

use App\MessageDeliveryBatch;
use App\Services\MessageDeliveryTracker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
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

            $roleId = (int) Auth::user()->role_id;
            $hasLetter = in_array('letter_send_index', $this->all_permission, true)
                || in_array('letter_index', $this->all_permission, true)
                || in_array('letter_sign_index', $this->all_permission, true);
            $hasInvitation = in_array('online_invitation_send_invitation', $this->all_permission, true)
                || in_array('invitations.view', $this->all_permission, true)
                || in_array('online_invitation_event', $this->all_permission, true);

            if ($roleId > 2 && ! $hasLetter && ! $hasInvitation) {
                abort(403, 'Not permitted');
            }

            return $next($request);
        });
    }

    protected function moduleFromRequest(Request $request): string
    {
        $module = strtolower(trim((string) $request->get('module', '')));
        if (in_array($module, ['invitations', 'online_invitation', 'invitation'], true)) {
            return 'invitations';
        }
        if (in_array($module, ['letters', 'letter'], true)) {
            return 'letters';
        }

        return 'all';
    }

    public function index(Request $request)
    {
        $module = $this->moduleFromRequest($request);

        if (! app(MessageDeliveryTracker::class)->enabled()) {
            return view('message_delivery.index', [
                'batches' => collect(),
                'activeCount' => 0,
                'tablesMissing' => true,
                'module' => $module,
                'all_permission' => $this->all_permission,
            ]);
        }

        $query = MessageDeliveryBatch::with('letter')->orderByDesc('id');
        if ($module === 'invitations') {
            $query->where('type', 'online_invitation');
        } elseif ($module === 'letters') {
            $query->where('type', 'letter');
        }

        $batches = $query->paginate(25)->appends($request->query());

        $activeQuery = MessageDeliveryBatch::whereIn('status', ['queued', 'sending']);
        if ($module === 'invitations') {
            $activeQuery->where('type', 'online_invitation');
        } elseif ($module === 'letters') {
            $activeQuery->where('type', 'letter');
        }
        $activeCount = $activeQuery->count();

        return view('message_delivery.index', [
            'batches' => $batches,
            'activeCount' => $activeCount,
            'tablesMissing' => false,
            'module' => $module,
            'all_permission' => $this->all_permission,
        ]);
    }

    public function show(Request $request, $id)
    {
        $batch = MessageDeliveryBatch::with(['items' => function ($q) {
            $q->orderBy('id');
        }, 'letter', 'queuedBy'])->findOrFail($id);

        $module = $batch->type === 'online_invitation'
            ? 'invitations'
            : $this->moduleFromRequest($request);

        return view('message_delivery.show', [
            'batch' => $batch,
            'module' => $module,
            'all_permission' => $this->all_permission,
        ]);
    }

    /**
     * Resend failed Digital Invitation recipients from a delivery batch.
     */
    public function resendFailed($id)
    {
        $batch = MessageDeliveryBatch::with('items')->findOrFail($id);
        if (($batch->type ?? '') !== 'online_invitation') {
            return redirect()->back()->with('not_permitted', 'Resend is only available for digital invitation batches.');
        }

        $invitationIds = [];
        foreach ($batch->items as $item) {
            if (($item->status ?? '') !== 'failed') {
                continue;
            }
            $ref = trim((string) $item->recipient_ref);
            if (Str::startsWith($ref, 'invitation:')) {
                $invitationIds[] = (int) substr($ref, 11);
            }
        }
        $invitationIds = array_values(array_unique(array_filter($invitationIds)));

        if (! $invitationIds) {
            return redirect()->back()->with('not_permitted', 'No failed invitations found in this batch to resend.');
        }

        $newBatchId = app(OnlineInvitationInvitationController::class)->queueResendByIds($invitationIds);
        if (! $newBatchId) {
            return redirect()->route('online_invitation.invitations.index', ['status' => 'failed'])
                ->with('not_permitted', 'Could not queue resend. Open Failed invitations and use Send Again.');
        }

        return redirect()->route('message.delivery.index', ['module' => 'invitations'])
            ->with('message', 'Resent '.count($invitationIds).' invitation(s). New batch #'.$newBatchId.' is in the queue.');
    }

    /**
     * JSON poll endpoint for live progress.
     */
    public function status(Request $request)
    {
        if (! app(MessageDeliveryTracker::class)->enabled()) {
            return response()->json(['batches' => [], 'active' => 0]);
        }

        $module = $this->moduleFromRequest($request);
        $ids = array_filter(array_map('intval', (array) $request->get('ids', [])));
        $q = MessageDeliveryBatch::query()->orderByDesc('id');
        if ($module === 'invitations') {
            $q->where('type', 'online_invitation');
        } elseif ($module === 'letters') {
            $q->where('type', 'letter');
        }
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
                'type' => $b->type,
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

        $activeQuery = MessageDeliveryBatch::whereIn('status', ['queued', 'sending']);
        if ($module === 'invitations') {
            $activeQuery->where('type', 'online_invitation');
        } elseif ($module === 'letters') {
            $activeQuery->where('type', 'letter');
        }
        $active = $activeQuery->count();

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
            'type' => $batch->type,
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

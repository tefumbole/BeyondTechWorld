<?php

namespace App\Http\Controllers;

use App\Jobs\SendOnlineInvitationJob;
use App\Customer;
use App\CustomerGroup;
use App\OnlineInvitation;
use App\OnlineInvitationCategory;
use App\OnlineInvitationEvent;
use App\OnlineInvitationRequestLink;
use App\Services\MessageDeliveryTracker;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Support\OnlineInvitationQr;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Models\Role;
use PDF;
//use SimpleSoftwareIO\QrCode\Facades\QrCode;
//use Carbon\Carbon;
//use Illuminate\Support\Facades\File;
//use App\Employee;
//use App\Http\Controllers\Controller as BaseController;
//use PDF;

class OnlineInvitationInvitationController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!Auth::check()) {
                return $next($request);
            }
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
        if (! Auth::check()) {
            return false;
        }
        try {
            $role = Role::find(Auth::user()->role_id);
            if (! $role) {
                return false;
            }
            if ((int) Auth::user()->role_id <= 2) {
                return true;
            }

            return $role->hasPermissionTo('online_invitation_send_invitation')
                || $role->hasPermissionTo('invitations_module');
        } catch (PermissionDoesNotExist $e) {
            return false;
        }
    }

    private function canAdmit()
    {
        if (! Auth::check()) {
            return false;
        }
        try {
            $role = Role::find(Auth::user()->role_id);
            if (! $role) {
                return false;
            }
            if ((int) Auth::user()->role_id <= 2) {
                return true;
            }

            return $role->hasPermissionTo('online_invitation_admit')
                || $role->hasPermissionTo('online_invitation_send_invitation')
                || $role->hasPermissionTo('invitations.check_in');
        } catch (PermissionDoesNotExist $e) {
            return $this->ensureAccess();
        }
    }

    /**
     * Reset invitations to awaiting_sending and queue WhatsApp delivery.
     * Used by bulk send and by the Queued Messages "Resend failed" action.
     *
     * @param  array<int, int>  $invitationIds
     */
    public function queueResendByIds(array $invitationIds): ?int
    {
        if (! $this->ensureAccess()) {
            return null;
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $invitationIds))));
        if (! $ids) {
            return null;
        }

        $invitations = OnlineInvitation::where('is_active', 1)->whereIn('id', $ids)->get();
        foreach ($invitations as $invitation) {
            if (! in_array($invitation->status, ['awaiting_sending', 'failed', 'sent'], true)) {
                continue;
            }
            $invitation->status = 'awaiting_sending';
            $invitation->sent_at = null;
            $invitation->last_error = null;
            $invitation->save();
        }

        $sendIds = OnlineInvitation::where('is_active', 1)
            ->whereIn('id', $ids)
            ->where('status', 'awaiting_sending')
            ->pluck('id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->all();

        return $this->dispatchInvitationSendAfterResponse($sendIds);
    }

    /** Queue Wasender sends after the HTTP response to avoid nginx 502s. */
    protected function dispatchInvitationSendAfterResponse(array $invitationIds): ?int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $invitationIds))));
        if (! $ids) {
            return null;
        }

        $batchId = null;
        try {
            $batch = app(MessageDeliveryTracker::class)->queueOnlineInvitations($ids);
            $batchId = $batch ? (int) $batch->id : null;
        } catch (\Throwable $e) {
            \Log::warning('Could not create invitation delivery batch: '.$e->getMessage());
        }

        $userId = Auth::id();
        $runner = function () use ($ids, $userId, $batchId) {
            static $done = false;
            if ($done) {
                return;
            }
            $done = true;
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
                @session_write_close();
            }
            @set_time_limit(900);
            ignore_user_abort(true);

            $tracker = app(MessageDeliveryTracker::class);
            if ($batchId) {
                try {
                    $tracker->markBatchSending($batchId);
                } catch (\Throwable $e) {
                }
            }

            foreach ($ids as $id) {
                $ref = 'invitation:'.$id;
                try {
                    if ($batchId) {
                        $tracker->markItemSending($batchId, $ref);
                    }
                    (new SendOnlineInvitationJob($id, $userId, $batchId))->handle();

                    if ($batchId) {
                        $invitation = OnlineInvitation::find($id);
                        if ($invitation && $invitation->status === 'sent') {
                            $tracker->markItemSent($batchId, $ref, $invitation->recipient_phone);
                        } else {
                            $tracker->markItemFailed(
                                $batchId,
                                $ref,
                                $invitation ? $invitation->recipient_phone : null,
                                $invitation ? ($invitation->last_error ?: 'Send failed') : 'Invitation not found'
                            );
                        }
                    }
                } catch (\Throwable $e) {
                    \Log::warning('Online invitation send failed after response', [
                        'invitation_id' => $id,
                        'error' => $e->getMessage(),
                    ]);
                    if ($batchId) {
                        try {
                            $tracker->markItemFailed($batchId, $ref, null, $e->getMessage());
                        } catch (\Throwable $ignored) {
                        }
                    }
                }
            }

            if ($batchId) {
                try {
                    $tracker->finalizeBatch($batchId);
                } catch (\Throwable $e) {
                }
            }
        };
        app()->terminating($runner);
        register_shutdown_function($runner);

        return $batchId;
    }

    public function index(Request $request)
    {
        if (!$this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $query = OnlineInvitation::with(['event', 'user', 'customer', 'category'])
            ->where('is_active', 1)
            ->orderBy('id', 'desc');

        $status = trim((string) $request->get('status', ''));
        if ($status !== '') {
            if ($status === 'used') {
                $query->whereNotNull('used_at');
            } else {
                $query->where('status', $status);
            }
        }

        $search = trim((string) $request->get('q', ''));
        if ($search !== '') {
            $query->where(function ($sub) use ($search) {
                if (ctype_digit($search)) {
                    $sub->orWhere('id', (int) $search);
                }
                $sub->orWhere('token', 'like', '%' . $search . '%')
                    ->orWhere('last_error', 'like', '%' . $search . '%')
                    ->orWhere('recipient_name', 'like', '%' . $search . '%')
                    ->orWhere('recipient_phone', 'like', '%' . $search . '%')
                    ->orWhere('recipient_email', 'like', '%' . $search . '%')
                    ->orWhereHas('event', function ($eventQ) use ($search) {
                        $eventQ->where('name', 'like', '%' . $search . '%')
                            ->orWhere('location', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('user', function ($userQ) use ($search) {
                        $userQ->where('name', 'like', '%' . $search . '%')
                            ->orWhere('phone', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('customer', function ($customerQ) use ($search) {
                        $customerQ->where('name', 'like', '%' . $search . '%')
                            ->orWhere('phone_number', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('category', function ($catQ) use ($search) {
                        $catQ->where('name', 'like', '%' . $search . '%');
                    });
            });
        }

        $data = $query->paginate(12);

        return view('online_invitation.invitation.index', compact('data'));
    }

    public function create()
    {
        if (!$this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $events = OnlineInvitationEvent::with(['template', 'categories'])
            ->where('is_active', 1)
            ->orderBy('id', 'desc')
            ->get();
        $customers = Customer::where('is_active', 1)->orderBy('name')->get(['id', 'name', 'phone_number', 'email', 'customer_group_id']);
        $customerGroups = CustomerGroup::where('is_active', 1)->orderBy('name')->get(['id', 'name']);
        $users = User::where('is_active', 1)->where('is_deleted', false)->orderBy('name')->get(['id', 'name', 'phone', 'email']);
        $categories = OnlineInvitationCategory::where('is_active', 1)->orderBy('name')->get(['id', 'name']);

        $groupCounts = Customer::where('is_active', 1)
            ->selectRaw('customer_group_id, COUNT(*) as member_count')
            ->groupBy('customer_group_id')
            ->pluck('member_count', 'customer_group_id');

        $directoryPeople = collect();
        foreach ($customerGroups as $g) {
            $count = (int) ($groupCounts[$g->id] ?? 0);
            $directoryPeople->push([
                'id' => 'group:' . $g->id,
                'name' => $g->name,
                'email' => '',
                'phone' => '',
                'role' => 'group',
                'source' => 'Group',
                'member_count' => $count,
                'meta' => $count . ' customer' . ($count === 1 ? '' : 's'),
            ]);
        }
        foreach ($customers as $c) {
            $directoryPeople->push([
                'id' => 'customer:' . $c->id,
                'name' => $c->name ?: 'Untitled',
                'email' => $c->email ?: '',
                'phone' => $c->phone_number ?: '',
                'role' => 'customer',
                'source' => 'Customer',
                'member_count' => 1,
                'meta' => trim(($c->phone_number ?: '') . (($c->phone_number && $c->email) ? ' · ' : '') . ($c->email ?: '')),
            ]);
        }
        foreach ($users as $u) {
            $directoryPeople->push([
                'id' => 'user:' . $u->id,
                'name' => $u->name ?: 'Untitled',
                'email' => $u->email ?: '',
                'phone' => $u->phone ?: '',
                'role' => 'staff',
                'source' => 'Staff',
                'member_count' => 1,
                'meta' => trim(($u->phone ?: '') . (($u->phone && $u->email) ? ' · ' : '') . ($u->email ?: '')),
            ]);
        }
        $directoryPeople = $directoryPeople->values()->all();

        $eventPreviewData = [];
        foreach ($events as $event) {
            $eventPreviewData[$event->id] = [
                'id' => $event->id,
                'name' => $event->name,
                'event_at' => $event->event_at,
                'location' => $event->location,
                'template' => $event->template ? [
                    'id' => $event->template->id,
                    'name' => $event->template->name,
                    'background' => $event->template->background,
                    'border_color' => $event->template->border_color ?: '#c8a75e',
                    'font_color' => $event->template->font_color ?: '#f3e7c1',
                    'font_size' => (int) ($event->template->font_size ?: 16),
                ] : null,
                'categories' => $event->categories ? $event->categories->map(function ($c) {
                    return ['id' => $c->id, 'name' => $c->name];
                })->values()->all() : [],
            ];
        }

        return view('online_invitation.invitation.create', compact(
            'events',
            'customers',
            'customerGroups',
            'users',
            'categories',
            'eventPreviewData',
            'directoryPeople'
        ));
    }

    public function store(Request $request)
    {
        if (!$this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $this->validate($request, [
            'event_id' => 'required|integer|exists:online_invitation_events,id',
            'category_id' => 'required|integer|exists:online_invitation_categories,id',
            'recipient_mode' => 'bail|required|in:directory,csv',
            'recipient_ids' => 'bail|nullable|required_if:recipient_mode,directory|array|min:1',
            'recipient_ids.*' => 'bail|string|max:64',
            'recipient_csv' => 'bail|nullable|required_if:recipient_mode,csv|file|mimes:csv,txt|max:5120',
            'message' => 'nullable|string|max:500',
            'rsvp' => 'nullable|string|max:255',
            'border_color' => ['nullable', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'font_color' => ['nullable', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'font_size' => 'nullable|integer|min:12|max:28',
        ]);

        $eventId = (int) $request->event_id;
        $categoryId = (int) $request->category_id;
        $optionalMessage = trim((string) $request->message) ?: null;
        $rsvp = trim((string) $request->rsvp) ?: null;
        $borderColor = trim((string) $request->border_color) ?: '#c8a75e';
        $fontColor = trim((string) $request->font_color) ?: '#f3e7c1';
        $fontSize = (int) ($request->font_size ?: 16);
        if ($fontSize < 12 || $fontSize > 28) {
            $fontSize = 16;
        }

        $event = OnlineInvitationEvent::with('categories')->where('is_active', 1)->find($eventId);
        if (!$event) {
            return redirect()->back()->with('not_permitted', 'Event not found');
        }
        $allowedCategoryIds = $event->categories ? $event->categories->pluck('id')->toArray() : [];
        if (!empty($allowedCategoryIds) && !in_array($categoryId, $allowedCategoryIds, true)) {
            return redirect()->back()->with('not_permitted', 'Selected category is not allowed for this event');
        }

        $created = 0;
        $markedFailedMissingPhone = 0;

        $createInvitation = function (?int $customerId, ?int $userId, ?string $name, ?string $phone, ?string $email) use ($eventId, $categoryId, $optionalMessage, $rsvp, $borderColor, $fontColor, $fontSize, &$created, &$markedFailedMissingPhone) {
            $name = trim((string) $name) ?: null;
            $phone = $this->normalizeRecipientPhone($phone);
            $email = trim((string) $email) ?: null;

            $status = $phone ? 'awaiting_sending' : 'failed';
            $lastError = $phone ? null : 'Recipient phone is missing';
            if (! $phone) {
                $markedFailedMissingPhone++;
            }

            $payload = [
                'event_id' => $eventId,
                'category_id' => $categoryId,
                'user_id' => $userId,
                'customer_id' => $customerId,
                'recipient_name' => $name,
                'recipient_phone' => $phone,
                'recipient_email' => $email,
                'message' => $optionalMessage,
                'rsvp' => $rsvp,
                'border_color' => $borderColor,
                'font_color' => $fontColor,
                'font_size' => $fontSize,
                'status' => $status,
                'rsvp_status' => 'pending',
                'last_error' => $lastError,
                'is_active' => 1,
                'created_by' => Auth::id(),
            ];

            $payload['token'] = Str::random(48);
            OnlineInvitation::create($payload);
            $created++;
        };

        if ($request->recipient_mode === 'directory') {
            $customerIds = [];
            $userIds = [];
            $groupIds = [];

            foreach ((array) $request->recipient_ids as $ref) {
                $ref = trim((string) $ref);
                if ($ref === '') {
                    continue;
                }
                if (Str::startsWith($ref, 'customer:')) {
                    $customerIds[] = (int) substr($ref, 9);
                } elseif (Str::startsWith($ref, 'user:')) {
                    $userIds[] = (int) substr($ref, 5);
                } elseif (Str::startsWith($ref, 'group:')) {
                    $groupIds[] = (int) substr($ref, 6);
                }
            }

            $customerIds = array_values(array_unique(array_filter($customerIds)));
            $userIds = array_values(array_unique(array_filter($userIds)));
            $groupIds = array_values(array_unique(array_filter($groupIds)));

            if (!empty($groupIds)) {
                $fromGroups = Customer::whereIn('customer_group_id', $groupIds)
                    ->where('is_active', 1)
                    ->pluck('id')
                    ->map(function ($id) {
                        return (int) $id;
                    })
                    ->all();
                $customerIds = array_values(array_unique(array_merge($customerIds, $fromGroups)));
            }

            if (empty($customerIds) && empty($userIds)) {
                throw ValidationException::withMessages([
                    'recipient_ids' => ['Select at least one group, customer, or staff member.'],
                ]);
            }

            if (!empty($customerIds)) {
                $customers = Customer::whereIn('id', $customerIds)
                    ->where('is_active', 1)
                    ->get(['id', 'name', 'phone_number', 'email']);
                foreach ($customers as $customer) {
                    $createInvitation(
                        (int) $customer->id,
                        null,
                        $customer->name,
                        $customer->phone_number,
                        $customer->email
                    );
                }
            }

            if (!empty($userIds)) {
                $staffUsers = User::whereIn('id', $userIds)
                    ->where('is_active', 1)
                    ->where('is_deleted', false)
                    ->get(['id', 'name', 'phone', 'email']);
                foreach ($staffUsers as $user) {
                    $createInvitation(
                        null,
                        (int) $user->id,
                        $user->name,
                        $user->phone,
                        $user->email
                    );
                }
            }
        } else {
            $rows = $this->parseRecipientCsv($request->file('recipient_csv'));
            foreach ($rows as $row) {
                $createInvitation(null, null, $row['name'] ?? null, $row['number'] ?? null, $row['email'] ?? null);
            }
        }

        $parts = [];
        $parts[] = "Invitations created: {$created}";
        if ($markedFailedMissingPhone) {
            $parts[] = "Missing phone (marked failed): {$markedFailedMissingPhone}";
        }
        return redirect()->route('online_invitation.invitations.index')->with('message', implode('. ', $parts));
    }

    public function send($id)
    {
        if (!$this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $invitation = OnlineInvitation::where('is_active', 1)->findOrFail($id);
        if (!in_array($invitation->status, ['awaiting_sending', 'failed', 'sent'], true)) {
            return redirect()->back()->with('not_permitted', 'Invitation is not in a sendable state');
        }

        if ($invitation->status === 'sent') {
            // Allow manual resends by returning the invitation to queue state.
            $invitation->status = 'awaiting_sending';
            $invitation->sent_at = null;
            $invitation->last_error = null;
            $invitation->save();
        }

        $this->dispatchInvitationSendAfterResponse([$invitation->id]);

//        $invitation = OnlineInvitation::with(['event.template', 'event.categories', 'user'])
//            ->where('is_active', 1)
//            ->find($id);
//
//        if (!$invitation) {
//            return;
//        }
//
//        if (!in_array($invitation->status, ['awaiting_sending', 'failed'], true)) {
//            return;
//        }
//
//        $invitation->send_attempts = (int) $invitation->send_attempts + 1;
//        $invitation->save();
//
//        if (!$invitation->token) {
//            $invitation->token = Str::random(48);
//            $invitation->save();
//        }
//
//        $user = $invitation->user;
//        $event = $invitation->event;
//
//        try {
//            if (!$user) {
//                throw new \Exception('User not found');
//            }
//            if (!$event) {
//                throw new \Exception('Event not found');
//            }
//
//            $controller = new BaseController();
//            $acceptUrl = rtrim(env('APP_URL'), '/').'/online-invitation/invite/'.$invitation->token;
//
//            // In this codebase, WhatsApp sending commonly uses `phone_number` (Employee/Customer),
//            // while App\\User stores `phone`. Try both (User.phone first, then Employee.phone_number).
//            $phone = $user->phone;
//            if (!$phone) {
//                $employee = Employee::where('user_id', $user->id)->select('phone_number')->first();
//                $phone = $employee ? $employee->phone_number : null;
//            }
//            if (!$phone) {
//                throw new \Exception('Recipient phone is missing (user.phone / employee.phone_number)');
//            }
//
//            // Build a PDF invitation (with embedded QR code) and send as a WhatsApp document.
//            $qrPng = QrCode::format('png')->size(320)->margin(1)->generate($acceptUrl);
//            $qrDataUri = 'data:image/png;base64,' . base64_encode($qrPng);
//
//            $eventAt = $event->event_at;
//            $eventAtText = $eventAt;
//            try {
//                if ($eventAt) {
//                    $eventAtText = Carbon::parse($eventAt)->format('D, M d, Y h:i A');
//                }
//            } catch (\Throwable $e) {
//                $eventAtText = $eventAt;
//            }
//
//            $pdfData = [
//                'recipientName' => $user->name ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
//                'eventName' => $event->name,
//                'eventLocation' => $event->location,
//                'eventAtText' => $eventAtText,
//                'acceptUrl' => $acceptUrl,
//                'qrDataUri' => $qrDataUri,
//            ];
//
//            $pdfDir = public_path('public/documents/online_invitation/');
//            $waBasePath = rtrim(env('APP_URL'), '/') . '/public/public/documents/online_invitation/';
//            if (!File::exists($pdfDir)) {
//                File::makeDirectory($pdfDir, 0755, true);
//            }
//            $pdfFilename = 'invitation_' . $invitation->id . '.pdf';
//            $pdfPath = $pdfDir . $pdfFilename;
//
//            PDF::loadView('pdf.online_invitation_pdf', $pdfData)
//                ->setPaper('A4', 'portrait')
//                ->save($pdfPath);
//
//            $pdfSent = false;
//            $pdfSendError = null;
//            try {
//                if (!file_exists($pdfPath)) {
//                    throw new \Exception('Invitation PDF was not created at: ' . $pdfPath);
//                }
//                $result = $controller->wpAttachMessage($pdfPath, $phone, $pdfFilename, $waBasePath . $pdfFilename);
//
//                if (env('WHATSAPP_SERVICE') === 'WASENDER' && is_array($result) && ($result['status'] ?? null) === 'error') {
//                    throw new \Exception('WASENDER attachment error: ' . ($result['message'] ?? 'Unknown error'));
//                }
//                $pdfSent = true;
//            } catch (\Throwable $e) {
//                $pdfSendError = substr((string) $e->getMessage(), 0, 2000);
//                // If attachment fails, still try sending a minimal fallback text message.
//                $fallback = "*Invitation*\n\n";
//                $fallback .= "*Name:* " . $user->name . "\n";
//                $fallback .= "*Event:* " . $event->name . "\n";
//                $fallback .= "*Date & Time:* " . $eventAtText . "\n";
//                if ($event->location) {
//                    $fallback .= "*Location:* " . $event->location . "\n";
//                }
//                $fallback .= "\nAccept / View:\n" . $acceptUrl . "\n";
//                $controller->wpMessage($phone, $fallback);
//            }
//
//            // WASENDER fetches the document from `documentUrl` asynchronously, so deleting immediately can break delivery.
//            if (env('WHATSAPP_SERVICE') !== 'WASENDER' && file_exists($pdfPath)) {
//                @unlink($pdfPath);
//            }
//
//            $invitation->status = 'sent';
//            $invitation->sent_at = date('Y-m-d H:i:s');
//            $invitation->last_error = $pdfSent ? null : $pdfSendError;
//            $invitation->save();
//        } catch (\Throwable $e) {
//            $invitation->status = 'failed';
//            $invitation->last_error = substr((string) $e->getMessage(), 0, 2000);
//            $invitation->save();
//        }
        return redirect()->route('message.delivery.index', ['module' => 'invitations'])
            ->with('message', 'Invitation queued for WhatsApp delivery.');
    }

    public function bulkSend(Request $request)
    {
        if (!$this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $this->validate($request, [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $ids = array_values(array_unique(array_map('intval', $request->ids ?: [])));
        $ids = array_values(array_filter($ids, function ($id) {
            return $id > 0;
        }));
        if (count($ids) === 0) {
            throw ValidationException::withMessages(['ids' => 'Please select at least one invitation.']);
        }

        $invitations = OnlineInvitation::where('is_active', 1)->whereIn('id', $ids)->get();
        $foundIds = $invitations->pluck('id')->map(function ($id) {
            return (int) $id;
        })->toArray();

        $queued = 0;
        $resetFromSent = 0;
        $skipped = 0;

        foreach ($invitations as $invitation) {
            if (!in_array($invitation->status, ['awaiting_sending', 'failed', 'sent'], true)) {
                $skipped++;
                continue;
            }

            if ($invitation->status === 'sent') {
                $invitation->status = 'awaiting_sending';
                $invitation->sent_at = null;
                $invitation->last_error = null;
                $invitation->save();
                $resetFromSent++;
            }

            $queued++;
        }

        $sendIds = $invitations->filter(function ($invitation) {
            return in_array($invitation->status, ['awaiting_sending', 'failed'], true)
                || ($invitation->status === 'sent'); // already reset above when sent
        })->pluck('id')->map(function ($id) {
            return (int) $id;
        })->all();
        // Re-query awaiting after resets
        $sendIds = OnlineInvitation::whereIn('id', $ids)
            ->where('is_active', 1)
            ->where('status', 'awaiting_sending')
            ->pluck('id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->all();
        $this->dispatchInvitationSendAfterResponse($sendIds);

        $notFound = max(0, count($ids) - count($foundIds));

        $parts = [];
        $parts[] = "Queued: {$queued}";
        if ($resetFromSent) {
            $parts[] = "Reset from sent: {$resetFromSent}";
        }
        if ($skipped) {
            $parts[] = "Skipped (not sendable): {$skipped}";
        }
        if ($notFound) {
            $parts[] = "Not found: {$notFound}";
        }

        return redirect()->route('message.delivery.index', ['module' => 'invitations'])
            ->with('message', implode('. ', $parts).'. Watch delivery progress below.');
    }

    public function bulkDelete(Request $request)
    {
        if (!$this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $this->validate($request, [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $ids = array_values(array_unique(array_map('intval', $request->ids ?: [])));
        $ids = array_values(array_filter($ids, function ($id) {
            return $id > 0;
        }));
        if (count($ids) === 0) {
            throw ValidationException::withMessages(['ids' => 'Please select at least one invitation.']);
        }

        $invitations = OnlineInvitation::where('is_active', 1)->whereIn('id', $ids)->get(['id']);
        $foundIds = $invitations->pluck('id')->map(function ($id) {
            return (int) $id;
        })->toArray();

        $deleted = 0;
        foreach ($invitations as $invitation) {
            $invitation->update(['is_active' => 0]);
            $deleted++;
        }

        $notFound = max(0, count($ids) - count($foundIds));

        $parts = [];
        $parts[] = "Deleted: {$deleted}";
        if ($notFound) {
            $parts[] = "Not found: {$notFound}";
        }

        return redirect()->back()->with('message', implode('. ', $parts));
    }

    public function showByToken(Request $request, $token)
    {
        $data = OnlineInvitation::with(['event.template', 'event.categories', 'user', 'customer', 'category'])
            ->where('is_active', 1)
            ->where('token', $token)
            ->firstOrFail();

        $canManage = $this->canAdmit();
        $canRsvp = empty($data->used_at);

        // QR scans show a compact Valid/Invalid card (invoice style), not the full invitation artwork.
        // Staff can still open ?full=1 for the designed invite page.
        if ($request->query('full') == '1') {
            return view('online_invitation.invitation.accept', compact('data', 'canManage', 'canRsvp'));
        }

        return view('online_invitation.invitation.verify', compact('data', 'canManage', 'canRsvp'));
    }

    /**
     * Create or reuse a guest self-apply link for this invitation's event + type.
     */
    public function guestApplyLink($id)
    {
        if (! $this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $invitation = OnlineInvitation::with('category')->where('is_active', 1)->findOrFail($id);
        if (! $invitation->event_id || ! $invitation->category_id) {
            return redirect()->back()->with('not_permitted', 'Invitation is missing event or type.');
        }

        $link = OnlineInvitationRequestLink::where('is_active', 1)
            ->where('event_id', $invitation->event_id)
            ->where('category_id', $invitation->category_id)
            ->orderByDesc('id')
            ->first();

        if (! $link) {
            $link = OnlineInvitationRequestLink::create([
                'event_id' => $invitation->event_id,
                'category_id' => $invitation->category_id,
                'token' => Str::random(40),
                'is_active' => 1,
                'max_uses' => null,
                'use_count' => 0,
                'created_by' => Auth::id(),
            ]);
        }

        $url = route('online_invitation.request.show', $link->token);

        return redirect()->back()->with(
            'message',
            'Guest apply link for <strong>'.e(optional($invitation->category)->name ?: 'this type').'</strong> '
            .'(event #'.(int) $invitation->event_id.'): '
            .'<a href="'.e($url).'" target="_blank" rel="noopener">'.e($url).'</a> '
            .'— share this so guests can enter their phone and receive an invitation.'
        );
    }

    public function rsvpAccept($token)
    {
        $invitation = OnlineInvitation::where('is_active', 1)->where('token', $token)->firstOrFail();
        if ($invitation->used_at) {
            return redirect()->back()->with('not_permitted', 'Invitation already admitted.');
        }
        $invitation->rsvp_status = 'accepted';
        $invitation->rsvp_at = now();
        if (! $invitation->accepted_at) {
            $invitation->accepted_at = now();
        }
        $invitation->save();

        return redirect()->back()->with('message', 'Thank you — you are marked as attending.');
    }

    public function rsvpDecline($token)
    {
        $invitation = OnlineInvitation::where('is_active', 1)->where('token', $token)->firstOrFail();
        if ($invitation->used_at) {
            return redirect()->back()->with('not_permitted', 'Invitation already admitted.');
        }
        $invitation->rsvp_status = 'declined';
        $invitation->rsvp_at = now();
        $invitation->save();

        return redirect()->back()->with('message', 'Your decline has been recorded.');
    }

    public function attending(Request $request)
    {
        if (! $this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $eventId = (int) $request->get('event_id');
        $query = OnlineInvitation::with(['event', 'category', 'customer', 'user'])
            ->where('is_active', 1)
            ->where('rsvp_status', 'accepted')
            ->orderByDesc('rsvp_at');
        if ($eventId > 0) {
            $query->where('event_id', $eventId);
        }
        $data = $query->paginate(40);
        $events = OnlineInvitationEvent::where('is_active', 1)->orderByDesc('id')->get(['id', 'name']);

        return view('online_invitation.invitation.attending', compact('data', 'events', 'eventId'));
    }

    public function acceptAndUse($token)
    {
        if (! $this->canAdmit()) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to admit guests.');
        }

        $invitation = OnlineInvitation::with(['event', 'user', 'customer', 'category'])
            ->where('is_active', 1)
            ->where('token', $token)
            ->firstOrFail();

        if ($invitation->used_at) {
            return redirect()->back()->with('not_permitted', 'Invitation already used');
        }

        if (! $invitation->accepted_at) {
            $invitation->accepted_at = date('Y-m-d H:i:s');
        }
        if (($invitation->rsvp_status ?? '') !== 'accepted') {
            $invitation->rsvp_status = 'accepted';
            $invitation->rsvp_at = now();
        }
        $invitation->used_at = date('Y-m-d H:i:s');
        $invitation->save();

        $this->sendWelcomeWhatsApp($invitation);

        return redirect()->back()->with('message', 'Guest admitted. Welcome message sent if phone is available.');
    }

    protected function sendWelcomeWhatsApp(OnlineInvitation $invitation): void
    {
        $phone = $invitation->recipient_phone
            ?: optional($invitation->customer)->phone_number
            ?: optional($invitation->user)->phone;
        if (! $phone) {
            return;
        }
        $name = $invitation->recipient_name
            ?: optional($invitation->customer)->name
            ?: optional($invitation->user)->name
            ?: 'Guest';
        $eventName = optional($invitation->event)->name ?: 'the event';
        $msg = "Welcome, {$name}!\n\nYou have been admitted to *{$eventName}*. Enjoy the event.";
        try {
            (new \App\Http\Controllers\Controller())->wpMessage($phone, $msg);
        } catch (\Throwable $e) {
            \Log::warning('Welcome WhatsApp failed', [
                'invitation_id' => $invitation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function pdfByToken($token)
    {
        $invitation = OnlineInvitation::with(['event.template', 'event.categories', 'user', 'customer', 'category'])
            ->where('is_active', 1)
            ->where('token', $token)
            ->firstOrFail();

        $pdfData = $this->buildInvitationPdfData($invitation);
        $pdf = PDF::loadView('pdf.online_invitation_pdf', $pdfData)->setOptions(['isRemoteEnabled' => true]);

        return $pdf->download('invitation_' . $invitation->id . '.pdf');
    }

    public function pngByToken($token)
    {
        $invitation = OnlineInvitation::with(['event.template', 'event.categories', 'user', 'customer', 'category'])
            ->where('is_active', 1)
            ->where('token', $token)
            ->firstOrFail();

        if (!extension_loaded('imagick')) {
            return redirect()->route('online_invitation.invite.pdf', $token);
        }

        $pdfData = $this->buildInvitationPdfData($invitation);
        $pdf = PDF::loadView('pdf.online_invitation_pdf', $pdfData)->setOptions(['isRemoteEnabled' => true]);
        $pdfBinary = $pdf->output();

        $im = new \Imagick();
        $im->setResolution(200, 200);
        $im->readImageBlob($pdfBinary);
        $im->setIteratorIndex(0);
        $im->setImageFormat('png');
        $im->setImageCompressionQuality(92);
        $pngBinary = $im->getImageBlob();
        $im->clear();
        $im->destroy();

        return response($pngBinary, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename=invitation_' . $invitation->id . '.png',
        ]);
    }

    public function destroy($id)
    {
        if (!$this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $invitation = OnlineInvitation::findOrFail($id);
        $invitation->update(['is_active' => 0]);

        return redirect()->route('online_invitation.invitations.index')->with('message', 'Invitation deleted successfully');
    }

    private function normalizeRecipientPhone($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $value = preg_replace('/\\s+/', '', $value);
        $value = preg_replace('/[^0-9+]/', '', $value);
        if ($value === '' || $value === '+') {
            return null;
        }
        return $value;
    }

    private function parseRecipientCsv($file): array
    {
        if (!$file) {
            return [];
        }

        $path = $file->getRealPath();
        if (!$path) {
            return [];
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            return [];
        }

        $rows = [];
        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return [];
        }

        $normalizedHeader = [];
        foreach ($header as $h) {
            $h = strtolower(trim((string) $h));
            $h = preg_replace('/\\s+/', '', $h);
            $normalizedHeader[] = $h;
        }

        $hasHeader = in_array('name', $normalizedHeader, true) || in_array('number', $normalizedHeader, true) || in_array('phone', $normalizedHeader, true) || in_array('email', $normalizedHeader, true);
        $colMap = [];
        if ($hasHeader) {
            foreach ($normalizedHeader as $idx => $key) {
                if ($key === 'phone') {
                    $key = 'number';
                }
                if (in_array($key, ['name', 'number', 'email'], true)) {
                    $colMap[$key] = $idx;
                }
            }
        } else {
            $colMap = ['name' => 0, 'number' => 1, 'email' => 2];
            // First row was data, not header
            $row = $header;
            $rows[] = [
                'name' => $row[$colMap['name']] ?? null,
                'number' => $row[$colMap['number']] ?? null,
                'email' => $row[$colMap['email']] ?? null,
            ];
        }

        while (($cols = fgetcsv($handle)) !== false) {
            if (!is_array($cols) || count($cols) === 0) {
                continue;
            }
            $name = $cols[$colMap['name']] ?? null;
            $number = $cols[$colMap['number']] ?? null;
            $email = $cols[$colMap['email']] ?? null;
            if (trim((string) $name) === '' && trim((string) $number) === '' && trim((string) $email) === '') {
                continue;
            }
            $rows[] = ['name' => $name, 'number' => $number, 'email' => $email];
        }

        fclose($handle);
        return $rows;
    }

    private function buildInvitationPdfData(OnlineInvitation $invitation): array
    {
        $event = $invitation->event;
        if (!$event) {
            abort(404);
        }

        $acceptUrl = route('online_invitation.invite.show', $invitation->token);

        $qrDataUri = OnlineInvitationQr::dataUri($acceptUrl, 320, 1);

        $eventAtText = $event->event_at;
        try {
            if ($event->event_at) {
                $eventAtText = Carbon::parse($event->event_at)->format('D, M d, Y h:i A');
            }
        } catch (\Throwable $e) {
            $eventAtText = $event->event_at;
        }

        $recipientName = $invitation->recipient_name ?: (optional($invitation->customer)->name ?: optional($invitation->user)->name);
        $recipientPhone = $invitation->recipient_phone ?: (optional($invitation->customer)->phone_number ?: optional($invitation->user)->phone);
        $recipientEmail = $invitation->recipient_email ?: (optional($invitation->customer)->email ?: optional($invitation->user)->email);
        $recipientEmail = trim((string) $recipientEmail) ?: null;

        $bg = $event->template ? trim((string) ($event->template->background ?: '')) : '';
        $pdfBgColor = '#ffffff';
        $pdfBgImage = null;
        if ($bg !== '') {
            if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $bg) || preg_match('/^rgba?\\(/i', $bg) || preg_match('/^hsla?\\(/i', $bg) || strcasecmp($bg, 'transparent') === 0) {
                $pdfBgColor = $bg;
            } else {
                $ref = $bg;
                if (preg_match('/url\\(([^)]+)\\)/i', $bg, $m)) {
                    $ref = $m[1];
                }
                $ref = trim((string) $ref, " \t\n\r\0\x0B'\"");
                if ($ref !== '') {
                    if (preg_match('#^https?://#i', $ref) || preg_match('#^data:image/[^;]+;base64,#i', $ref)) {
                        $pdfBgImage = $ref;
                    } else {
                        $pdfBgImage = asset(ltrim($ref, '/'));
                    }
                }
            }
        }

        return [
            'recipientName' => $recipientName ?: 'Guest',
            'recipientPhone' => $recipientPhone,
            'recipientEmail' => $recipientEmail,
            'optionalMessage' => $invitation->message,
            'rsvp' => $invitation->rsvp,
            'borderColor' => $invitation->border_color ?: '#c8a75e',
            'fontColor' => $invitation->font_color ?: '#f3e7c1',
            'fontSize' => (int) ($invitation->font_size ?: optional(optional($event)->template)->font_size ?: 16),
            'eventName' => $event->name,
            'eventLocation' => $event->location,
            'eventAtText' => $eventAtText,
            'categoryName' => $invitation->category ? $invitation->category->name : null,
            'acceptUrl' => $acceptUrl,
            'qrDataUri' => $qrDataUri,
            'pdfBgColor' => $pdfBgColor,
            'pdfBgImage' => $pdfBgImage,
        ];
    }
}

<?php

namespace App\Services;

use App\Notifications\ContractWorkflowNotification;
use App\Services\Messaging\NotificationRouter;
use App\StaffPermission;
use App\Support\WhatsAppMessage;
use App\User;
use Illuminate\Support\Facades\Log;

class StaffPermissionNotifier
{
    protected $router;

    public function __construct(NotificationRouter $router)
    {
        $this->router = $router;
    }

    /**
     * WhatsApp + in-app bell for admins when a staff permission request is submitted.
     */
    public function notifyAdminsOfNewRequest(StaffPermission $permission)
    {
        $path = route('permissions.show', $permission->id, false);
        $loginUrl = url('/login?redirect='.rawurlencode($path));
        $from = $permission->from_at ? $permission->from_at->format('D d M Y H:i') : '—';
        $to = $permission->to_at ? $permission->to_at->format('D d M Y H:i') : '—';
        $reason = trim((string) $permission->reason) !== '' ? trim($permission->reason) : '—';
        $bellMessage = ($permission->full_name ?: 'Someone').' requested permission ('.$permission->reference_number.')';

        $admins = User::where('is_deleted', false)
            ->where('is_active', 1)
            ->where('role_id', '<=', 2)
            ->orderBy('id')
            ->get(['id', 'name', 'phone']);

        foreach ($admins as $admin) {
            try {
                $admin->notify(new ContractWorkflowNotification(
                    $bellMessage,
                    url($path),
                    'permission_pending'
                ));
            } catch (\Throwable $e) {
                Log::warning('Permission in-app notify failed', [
                    'admin_id' => $admin->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $phone = trim((string) $admin->phone);
            if ($phone === '') {
                continue;
            }

            usleep(5500000);
            $message = WhatsAppMessage::permissionRequestAdmin(
                $admin->name,
                $permission->full_name,
                $permission->reference_number,
                $permission->company_role,
                $from,
                $to,
                $reason,
                $loginUrl
            );
            $send = $this->router->sendWhatsAppText($phone, $message, [
                'title' => 'Permission request',
                'message' => $bellMessage,
            ]);
            if (empty($send['success'])) {
                Log::warning('Permission admin WhatsApp failed', [
                    'admin_id' => $admin->id,
                    'error' => $send['error'] ?? 'unknown',
                ]);
            }
        }
    }
}

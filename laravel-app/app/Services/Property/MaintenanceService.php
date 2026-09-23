<?php

namespace App\Services\Property;

use App\Employee;
use App\Property\MaintenanceRequest;
use App\Property\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MaintenanceService
{
    protected $log;
    protected $media;

    public function __construct(PropertyActivityLogger $log, PropertyMediaGuard $media)
    {
        $this->log = $log;
        $this->media = $media;
    }

    public function open(Tenancy $tenancy, $description, array $extra = [])
    {
        $messageId = isset($extra['provider_message_id']) ? $extra['provider_message_id'] : null;
        if ($messageId) {
            $existing = MaintenanceRequest::where('provider_message_id', $messageId)->first();
            if ($existing) {
                return ['ok' => true, 'duplicate' => true, 'request' => $existing];
            }
        }
        $text = trim((string) $description);
        $emergency = $this->emergency($text);
        $priority = $emergency ? 'URGENT' : 'NORMAL';
        $suggested = isset($extra['suggested_priority']) ? strtoupper((string) $extra['suggested_priority']) : '';
        if (! $emergency && in_array($suggested, ['LOW', 'NORMAL', 'HIGH'], true)) {
            $priority = $suggested;
        }
        $row = MaintenanceRequest::create([
            'property_id' => $tenancy->property_id,
            'unit_id' => $tenancy->unit_id,
            'tenancy_id' => $tenancy->id,
            'customer_id' => $tenancy->customer_id,
            'category' => $this->category($text),
            'description' => $text,
            'priority' => $priority,
            'emergency' => $emergency,
            'status' => 'OPEN',
            'conversation_id' => isset($extra['conversation_id']) ? $extra['conversation_id'] : null,
            'source' => isset($extra['source']) ? $extra['source'] : 'whatsapp',
            'provider_message_id' => $messageId,
            'opened_at' => Carbon::now(),
        ]);
        $this->log->write($emergency ? 'maintenance_emergency' : 'maintenance_opened', 'maintenance', $row->id, [
            'tenancy_id' => $tenancy->id,
            'category' => $row->category,
            'priority' => $row->priority,
        ], $row->conversation_id);

        return ['ok' => true, 'request' => $row, 'emergency' => $emergency];
    }

    public function owned($requestId, array $tenancyIds)
    {
        $row = MaintenanceRequest::find((int) $requestId);
        if (! $row || ! in_array((int) $row->tenancy_id, array_map('intval', $tenancyIds), true)) {
            $this->log->write('maintenance_denied', 'maintenance', (int) $requestId, ['reason' => 'wrong_owner']);

            return null;
        }

        return $row;
    }

    public function attach(MaintenanceRequest $request, $bytes, $name, $mime, $voice = false)
    {
        $check = $this->media->check($name, $mime, strlen((string) $bytes), $voice);
        if (empty($check['ok'])) {
            $this->log->write('maintenance_attachment_rejected', 'maintenance', $request->id, ['error' => $check['error']]);

            return ['ok' => false, 'error' => $check['error']];
        }
        $dir = storage_path('app/property-maintenance/'.$request->id);
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $stored = bin2hex(random_bytes(8)).'.'.$check['ext'];
        $path = $dir.'/'.$stored;
        file_put_contents($path, $bytes);
        $id = DB::table('property_maintenance_attachments')->insertGetId([
            'maintenance_request_id' => $request->id,
            'tenancy_id' => $request->tenancy_id,
            'original_name' => $check['name'],
            'stored_name' => $stored,
            'mime' => $check['mime'],
            'size_bytes' => strlen((string) $bytes),
            'kind' => $check['kind'],
            'path' => $path,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        $this->log->write('maintenance_attachment', 'maintenance', $request->id, [
            'attachment_id' => $id,
            'kind' => $check['kind'],
            'mime' => $check['mime'],
        ]);

        return ['ok' => true, 'id' => $id];
    }

    public function assign(MaintenanceRequest $request, $employeeId)
    {
        $employee = Employee::where('id', (int) $employeeId)->where('is_active', true)->first();
        if (! $employee) {
            return ['ok' => false, 'error' => 'employee_missing'];
        }
        $request->assigned_employee_id = $employee->id;
        if ($request->status === 'OPEN') {
            $request->status = 'ASSIGNED';
        }
        $request->acknowledged_at = $request->acknowledged_at ?: Carbon::now();
        $request->save();
        $this->log->write('maintenance_assigned', 'maintenance', $request->id, ['employee_id' => $employee->id]);

        return ['ok' => true, 'request' => $request];
    }

    public function setStatus(MaintenanceRequest $request, $status)
    {
        $status = strtoupper((string) $status);
        $allowed = ['OPEN', 'ACKNOWLEDGED', 'ASSIGNED', 'IN_PROGRESS', 'WAITING_PART', 'RESOLVED', 'CLOSED'];
        if (! in_array($status, $allowed, true)) {
            return ['ok' => false, 'error' => 'bad_status'];
        }
        $request->status = $status;
        if ($status === 'ACKNOWLEDGED') {
            $request->acknowledged_at = $request->acknowledged_at ?: Carbon::now();
        }
        if ($status === 'IN_PROGRESS') {
            $request->started_at = $request->started_at ?: Carbon::now();
        }
        if ($status === 'RESOLVED') {
            $request->resolved_at = Carbon::now();
        }
        if ($status === 'CLOSED') {
            $request->closed_at = Carbon::now();
        }
        $request->save();
        $this->log->write('maintenance_status', 'maintenance', $request->id, ['status' => $status]);

        return ['ok' => true, 'request' => $request];
    }

    public function noteFieldAttendance(MaintenanceRequest $request, $attendanceId)
    {
        $request->attendance_id = (int) $attendanceId;
        $request->save();
        $this->log->write('maintenance_field_link', 'maintenance', $request->id, [
            'attendance_id' => (int) $attendanceId,
            'status_unchanged' => $request->status,
        ]);

        return $request;
    }

    public function category($text)
    {
        $t = strtolower((string) $text);
        $map = [
            'Plumbing' => '/leak|water|plumb|flood/',
            'Electrical' => '/electric|power|spark|light|ac\b|air ?con/',
            'Door/Lock' => '/lock|door/',
            'Appliance' => '/fridge|stove|appliance/',
            'Cleaning' => '/clean/',
            'Internet/Network' => '/internet|wifi|network/',
            'Security' => '/security|alarm/',
            'Structural' => '/wall|roof|ceiling|crack/',
        ];
        foreach ($map as $name => $pattern) {
            if (preg_match($pattern, $t)) {
                return $name;
            }
        }

        return 'Other';
    }

    public function emergency($text)
    {
        return (bool) preg_match('/\b(fire|electrical fire|sparking|sparks|gas smell|smell of gas|major flood|flooding|cannot breathe|physical danger)\b/i', (string) $text);
    }

    public function emergencyText()
    {
        $text = trim((string) config('services.property.emergency_instruction'));
        if ($text === '') {
            $text = 'This chat cannot dispatch emergency services. Leave the area if you are in immediate danger, and contact the property office using the number BeyondTechWorld already gave you.';
        }
        $contact = trim((string) config('services.property.emergency_contact'));
        if ($contact !== '') {
            $text .= ' Office contact: '.$contact.'.';
        }

        return $text;
    }
}

<?php

namespace App\Services\Property;

use App\Property\BillPaymentRequest;
use App\Property\MaintenanceRequest;
use App\Property\RentObligation;
use App\Property\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PropertyMetrics
{
    public function all()
    {
        if (! Schema::hasTable('tenancies')) {
            return $this->empty();
        }

        return [
            'active_tenancies' => Tenancy::where('status', 'ACTIVE')->count(),
            'rent_due' => Schema::hasTable('rent_obligations') ? RentObligation::whereIn('status', ['DUE', 'PARTIALLY_PAID'])->count() : 0,
            'rent_overdue' => Schema::hasTable('rent_obligations') ? RentObligation::where('status', 'OVERDUE')->count() : 0,
            'maintenance_open' => Schema::hasTable('property_maintenance_requests') ? MaintenanceRequest::whereNotIn('status', ['RESOLVED', 'CLOSED'])->count() : 0,
            'maintenance_urgent' => Schema::hasTable('property_maintenance_requests') ? MaintenanceRequest::where('priority', 'URGENT')->whereNotIn('status', ['RESOLVED', 'CLOSED'])->count() : 0,
            'maintenance_unassigned' => Schema::hasTable('property_maintenance_requests') ? MaintenanceRequest::whereNull('assigned_employee_id')->whereNotIn('status', ['RESOLVED', 'CLOSED'])->count() : 0,
            'bills_open' => Schema::hasTable('bill_payment_requests') ? BillPaymentRequest::count() : 0,
            'bills_awaiting_details' => Schema::hasTable('bill_payment_requests') ? BillPaymentRequest::where('status', 'AWAITING_DETAILS')->count() : 0,
            'bills_review' => Schema::hasTable('bill_payment_requests') ? BillPaymentRequest::where('status', 'UNDER_REVIEW')->count() : 0,
            'bills_processing' => Schema::hasTable('bill_payment_requests') ? BillPaymentRequest::whereIn('status', ['PROCESSING', 'PENDING_CONFIRMATION'])->count() : 0,
            'bills_paid' => Schema::hasTable('bill_payment_requests') ? BillPaymentRequest::where('status', 'PAID')->count() : 0,
            'bills_failed' => Schema::hasTable('bill_payment_requests') ? BillPaymentRequest::where('status', 'FAILED')->count() : 0,
            'duplicates_prevented' => Schema::hasTable('property_activities') ? DB::table('property_activities')->where('action', 'bill_duplicate_event')->count() : 0,
        ];
    }

    public function diagnostics()
    {
        $metrics = $this->all();

        return [
            'module' => Schema::hasTable('tenancies') ? 'ready' : 'missing',
            'tenant_identity' => 'stage1',
            'rent_billing' => Schema::hasTable('rent_obligations') ? 'ready' : 'missing',
            'reminders_enabled' => (bool) config('services.property.reminders_enabled'),
            'maintenance_open' => $metrics['maintenance_open'],
            'bill_requests' => $metrics['bills_open'],
            'payment_provider' => 'not_connected',
            'webhook' => trim((string) config('services.property.bill_webhook_secret')) !== '' ? 'Configured' : 'Missing',
            'failed_payments' => $metrics['bills_failed'],
            'duplicates_prevented' => $metrics['duplicates_prevented'],
        ];
    }

    protected function empty()
    {
        return [
            'active_tenancies' => 0,
            'rent_due' => 0,
            'rent_overdue' => 0,
            'maintenance_open' => 0,
            'maintenance_urgent' => 0,
            'maintenance_unassigned' => 0,
            'bills_open' => 0,
            'bills_awaiting_details' => 0,
            'bills_review' => 0,
            'bills_processing' => 0,
            'bills_paid' => 0,
            'bills_failed' => 0,
            'duplicates_prevented' => 0,
        ];
    }
}

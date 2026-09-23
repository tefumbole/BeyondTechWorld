<?php

namespace App\Services\Assistant;

use App\Assistant\AssistantKnowledge;
use App\Booking;
use App\Product;
use App\Quotation;
use App\Sale;
use App\Services\Rental\RentalAvailabilityService;
use App\Services\Rental\RentalQuoteService;
use App\WhatsApp\Lead;
use App\WhatsApp\WhatsAppContact;
use Illuminate\Support\Facades\Schema;

class AssistantToolExecutor
{
    protected $registry;
    protected $policy;
    protected $handover;

    public function __construct(
        AssistantToolRegistry $registry,
        AssistantPolicyService $policy,
        AssistantHandoverService $handover
    ) {
        $this->registry = $registry;
        $this->policy = $policy;
        $this->handover = $handover;
    }

    public function execute($name, array $params, array $context)
    {
        if (preg_match('/grade|pass_intern|release_next|official_score/i', (string) $name)) {
            return ['success' => false, 'error' => 'grading_forbidden'];
        }
        if (preg_match('/approve_overtime|approve_attendance|override_location|modify_historical/i', (string) $name)) {
            return ['success' => false, 'error' => 'privileged'];
        }
        $meta = $this->registry->get($name);
        if (! $meta) {
            return ['success' => false, 'error' => 'unknown_tool'];
        }
        $roles = isset($context['roles']) ? $context['roles'] : [];
        list($ok, $reason) = $this->policy->toolAllowed($name, $meta, $roles);
        if (! $ok) {
            return ['success' => false, 'error' => $reason, 'tool' => $name];
        }
        $method = 'tool'.str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
        if (! method_exists($this, $method)) {
            return ['success' => false, 'error' => 'unimplemented_tool'];
        }

        return $this->{$method}($params, $context);
    }

    protected function toolGetContactSummary(array $params, array $context)
    {
        return [
            'success' => true,
            'name' => isset($context['contact_name']) ? $context['contact_name'] : null,
            'phone' => isset($context['phone']) ? $context['phone'] : null,
            'roles' => isset($context['roles']) ? $context['roles'] : [],
        ];
    }

    protected function toolGetCompanyInformation(array $params, array $context)
    {
        return ['success' => true, 'entries' => $this->knowledge(['company', 'hours', 'support'])];
    }

    protected function toolGetServices(array $params, array $context)
    {
        return ['success' => true, 'entries' => $this->knowledge(['services', 'rental', 'internship'])];
    }

    protected function toolSearchRentalProducts(array $params, array $context)
    {
        if (! Schema::hasTable('products')) {
            return ['success' => true, 'products' => [], 'note' => 'Catalogue unavailable.'];
        }
        $q = trim((string) (isset($params['query']) ? $params['query'] : ''));
        $query = Product::query()->where('is_active', true);
        if ($q !== '') {
            $query->where(function ($inner) use ($q) {
                $inner->where('name', 'like', '%'.$q.'%')->orWhere('code', 'like', '%'.$q.'%');
            });
        }
        $rows = $query->orderBy('name')->limit(8)->get(['id', 'name', 'code', 'rent_price_per_day', 'rent_price_per_hour']);
        $products = [];
        foreach ($rows as $row) {
            $products[] = [
                'id' => $row->id,
                'name' => $row->name,
                'code' => $row->code,
                'listed_day_rate' => $row->rent_price_per_day,
                'listed_hour_rate' => $row->rent_price_per_hour,
                'availability_checked' => false,
            ];
        }

        return ['success' => true, 'products' => $products, 'availability_checked' => false];
    }

    protected function toolCheckRentalAvailability(array $params, array $context)
    {
        $availability = app(RentalAvailabilityService::class);
        $range = $availability->resolveRange($params);
        $query = isset($params['product']) ? $params['product'] : (isset($params['query']) ? $params['query'] : '');
        if (! $range) {
            return $this->toolSearchRentalProducts($params, $context);
        }
        $products = $availability->search($query, 1);
        $product = $products->first();
        if (! $product) {
            $proposal = $this->proposalAvailability($params, $context, $range);
            if ($proposal) {
                return $proposal;
            }

            return ['success' => true, 'products' => [], 'availability_checked' => true, 'available' => false, 'reason' => 'not_found'];
        }
        $qty = isset($params['qty']) ? (int) $params['qty'] : 1;
        $check = $availability->assess($product, $qty, $range['start'], $range['end']);
        $check['days'] = $range['days'];
        $check['products'] = [$check];
        if (empty($check['available'])) {
            $check['alternatives'] = $availability->alternatives($product, $qty, $range['start'], $range['end']);
        }
        if (! empty($check['priced']) && ! empty($check['available'])) {
            $check['estimate_total'] = round($check['day_rate'] * $check['requested_qty'] * $range['days'], 2);
        }

        return $check;
    }

    protected function proposalAvailability(array $params, array $context, array $range)
    {
        $conversation = isset($context['conversation']) ? $context['conversation'] : null;
        if (! $conversation) {
            return null;
        }
        $request = app(\App\Services\Rental\RentalRequestService::class)->active($conversation);
        if (! $request) {
            return null;
        }
        $proposal = app(\App\Services\Rental\RentalRecommendationService::class)->propose($request, $params);
        foreach (isset($proposal['lines']) ? $proposal['lines'] : [] as $line) {
            if (empty($line['success']) || empty($line['available']) || empty($line['name'])) {
                continue;
            }

            return [
                'success' => true,
                'availability_checked' => true,
                'available' => true,
                'priced' => true,
                'name' => $line['name'],
                'start' => $range['start'],
                'available_qty' => isset($line['available_qty']) ? $line['available_qty'] : $line['quantity'],
                'day_rate' => isset($line['unit_price']) ? $line['unit_price'] : 0,
                'requested_qty' => isset($line['quantity']) ? $line['quantity'] : 1,
                'products' => [$line],
            ];
        }

        return null;
    }

    protected function toolCreateRentalQuotation(array $params, array $context)
    {
        return app(RentalQuoteService::class)->createDraft($params, $context);
    }

    protected function toolRequestRentalBooking(array $params, array $context)
    {
        return app(RentalQuoteService::class)->requestBooking($params, $context);
    }

    protected function toolGetRentalProductInformation(array $params, array $context)
    {
        $id = isset($params['product_id']) ? (int) $params['product_id'] : 0;
        $product = $id && Schema::hasTable('products') ? Product::where('is_active', true)->find($id) : null;
        if (! $product) {
            return ['success' => false, 'error' => 'not_found'];
        }

        return [
            'success' => true,
            'id' => $product->id,
            'name' => $product->name,
            'code' => $product->code,
            'listed_day_rate' => $product->rent_price_per_day,
            'availability_checked' => false,
        ];
    }

    protected function toolGetCustomerQuotations(array $params, array $context)
    {
        $customerId = $this->customerId($context);
        if (! $customerId || ! Schema::hasTable('quotations')) {
            return ['success' => false, 'error' => 'not_found'];
        }
        $rows = Quotation::where('customer_id', $customerId)->orderByDesc('id')->limit(5)->get();
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => $row->id,
                'reference' => $row->reference_no,
                'status' => Quotation::statusLabel($row->quotation_status),
            ];
        }

        return ['success' => true, 'quotations' => $out];
    }

    protected function toolGetQuotationStatus(array $params, array $context)
    {
        $customerId = $this->customerId($context);
        $id = isset($params['quotation_id']) ? (int) $params['quotation_id'] : 0;
        $row = $id && $customerId ? Quotation::where('customer_id', $customerId)->find($id) : null;
        if (! $row) {
            return ['success' => false, 'error' => 'not_found'];
        }

        return ['success' => true, 'id' => $row->id, 'reference' => $row->reference_no, 'status' => Quotation::statusLabel($row->quotation_status)];
    }

    protected function toolGetCustomerBookings(array $params, array $context)
    {
        $customerId = $this->customerId($context);
        if (! $customerId || ! Schema::hasTable('bookings')) {
            return ['success' => false, 'error' => 'not_found'];
        }
        $rows = Booking::where('customer_id', $customerId)->orderByDesc('id')->limit(8)->get();
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->bookingRow($row);
        }

        return ['success' => true, 'bookings' => $out, 'count' => count($out)];
    }

    protected function toolGetBookingStatus(array $params, array $context)
    {
        $customerId = $this->customerId($context);
        if (! $customerId || ! Schema::hasTable('bookings')) {
            return ['success' => false, 'error' => 'not_found'];
        }
        $id = isset($params['booking_id']) ? (int) $params['booking_id'] : 0;
        $row = $id ? Booking::where('customer_id', $customerId)->find($id) : null;
        if (! $row) {
            $rows = Booking::where('customer_id', $customerId)->orderByDesc('id')->limit(5)->get();
            if ($rows->count() === 1) {
                return ['success' => true, 'booking' => $this->bookingRow($rows->first()), 'ambiguous' => false];
            }
            if ($rows->count() > 1) {
                $list = [];
                foreach ($rows as $item) {
                    $list[] = $this->bookingRow($item);
                }

                return ['success' => true, 'ambiguous' => true, 'bookings' => $list];
            }

            return ['success' => false, 'error' => 'not_found'];
        }

        return ['success' => true, 'booking' => $this->bookingRow($row), 'ambiguous' => false];
    }

    protected function toolGetCustomerPaymentSummary(array $params, array $context)
    {
        return ['success' => false, 'error' => 'verification_required'];
    }

    protected function toolGetInternshipSummary(array $params, array $context)
    {
        return app(\App\Services\Internship\InternshipWhatsAppService::class)->summary($context, $params);
    }

    protected function toolGetCurrentInternshipTask(array $params, array $context)
    {
        return app(\App\Services\Internship\InternshipWhatsAppService::class)->currentTask($context, $params);
    }

    protected function toolGetTaskInstructions(array $params, array $context)
    {
        return app(\App\Services\Internship\InternshipWhatsAppService::class)->currentTask($context, $params);
    }

    protected function toolGetInternshipProgress(array $params, array $context)
    {
        return app(\App\Services\Internship\InternshipWhatsAppService::class)->progress($context, $params);
    }

    protected function toolGetTaskMaterials(array $params, array $context)
    {
        return app(\App\Services\Internship\InternshipWhatsAppService::class)->materials($context, $params);
    }

    protected function toolGetSubmissionStatus(array $params, array $context)
    {
        return app(\App\Services\Internship\InternshipWhatsAppService::class)->submissionStatus($context, $params);
    }

    protected function toolPrepareInternshipSubmission(array $params, array $context)
    {
        return app(\App\Services\Internship\InternshipWhatsAppService::class)->prepare($context, $params);
    }

    protected function toolSubmitInternshipWork(array $params, array $context)
    {
        return app(\App\Services\Internship\InternshipWhatsAppService::class)->confirm($context, $params);
    }

    protected function toolAttachSubmissionFile(array $params, array $context)
    {
        return app(\App\Services\Internship\InternshipWhatsAppService::class)->prepare($context, $params);
    }

    protected function toolAttachSubmissionLink(array $params, array $context)
    {
        return app(\App\Services\Internship\InternshipWhatsAppService::class)->prepare($context, $params);
    }

    protected function toolRequestSupervisorHandover(array $params, array $context)
    {
        return app(\App\Services\Internship\InternshipWhatsAppService::class)->handover($context, $params);
    }

    protected function toolGetAttendanceStatus(array $params, array $context)
    {
        return app(\App\Services\Attendance\AttendanceWhatsAppService::class)->status($context, $params);
    }

    protected function toolCheckIn(array $params, array $context)
    {
        return app(\App\Services\Attendance\AttendanceWhatsAppService::class)->checkIn($context, $params);
    }

    protected function toolCheckOut(array $params, array $context)
    {
        return app(\App\Services\Attendance\AttendanceWhatsAppService::class)->checkOut($context, $params);
    }

    protected function toolGetWorkHours(array $params, array $context)
    {
        return app(\App\Services\Attendance\AttendanceWhatsAppService::class)->hours($context, $params);
    }

    protected function toolGetCurrentAssignment(array $params, array $context)
    {
        return app(\App\Services\Attendance\AttendanceWhatsAppService::class)->assignment($context, $params);
    }

    protected function toolCheckInAssignment(array $params, array $context)
    {
        return app(\App\Services\Attendance\AttendanceWhatsAppService::class)->checkInAssignment($context, $params);
    }

    protected function toolCheckOutAssignment(array $params, array $context)
    {
        return app(\App\Services\Attendance\AttendanceWhatsAppService::class)->checkOutAssignment($context, $params);
    }

    protected function toolValidateAssignmentLocation(array $params, array $context)
    {
        $service = app(\App\Services\Attendance\AttendanceLocationService::class);
        if (! $service->validCoordinates(isset($params['latitude']) ? $params['latitude'] : null, isset($params['longitude']) ? $params['longitude'] : null)) {
            return ['success' => false, 'error' => 'invalid_location'];
        }

        return ['success' => true] + $service->verify(
            $params['latitude'],
            $params['longitude'],
            isset($params['expected_latitude']) ? $params['expected_latitude'] : null,
            isset($params['expected_longitude']) ? $params['expected_longitude'] : null,
            isset($params['radius']) ? $params['radius'] : null
        );
    }

    protected function toolRequestAttendanceCorrection(array $params, array $context)
    {
        return app(\App\Services\Attendance\AttendanceWhatsAppService::class)->requestCorrection($context, $params);
    }

    protected function toolGetAttendanceCorrectionStatus(array $params, array $context)
    {
        return app(\App\Services\Attendance\AttendanceWhatsAppService::class)->correctionStatus($context, $params);
    }

    protected function toolGetCurrentLead(array $params, array $context)
    {
        $contactId = isset($context['contact_id']) ? $context['contact_id'] : null;
        if (! $contactId || ! Schema::hasTable('leads')) {
            return ['success' => false, 'error' => 'not_found'];
        }
        $lead = Lead::where('contact_id', $contactId)->orderByDesc('id')->first();
        if (! $lead) {
            return ['success' => false, 'error' => 'not_found'];
        }

        return ['success' => true, 'id' => $lead->id, 'status' => $lead->status, 'category' => $lead->category];
    }

    protected function toolRequestHumanHandover(array $params, array $context)
    {
        $conversation = isset($context['conversation']) ? $context['conversation'] : null;
        if ($conversation) {
            $this->handover->toHuman($conversation, isset($params['reason']) ? $params['reason'] : 'tool');
        }

        return ['success' => true, 'handed_over' => true];
    }

    protected function toolListAvailableDocuments(array $params, array $context)
    {
        $dir = public_path('quotation');
        $files = [];
        if (is_dir($dir)) {
            foreach (array_slice(glob($dir.'/quotation_*_invoice.pdf') ?: [], 0, 8) as $file) {
                $files[] = ['name' => basename($file)];
            }
        }

        return ['success' => true, 'documents' => $files];
    }

    protected function knowledge(array $categories)
    {
        if (! Schema::hasTable('assistant_knowledge')) {
            return [];
        }
        $rows = AssistantKnowledge::where('enabled', true)->whereIn('category', $categories)->get();
        $out = [];
        foreach ($rows as $row) {
            $out[] = ['title' => $row->title, 'content' => $row->content];
        }

        return $out;
    }

    protected function customerId(array $context)
    {
        return isset($context['customer_id']) ? (int) $context['customer_id'] : 0;
    }

    protected function bookingRow(Booking $row)
    {
        return [
            'id' => $row->id,
            'reference' => $row->reference_no,
            'status' => $row->booking_status,
            'payment_status' => $row->payment_status,
            'grand_total' => null,
        ];
    }
}

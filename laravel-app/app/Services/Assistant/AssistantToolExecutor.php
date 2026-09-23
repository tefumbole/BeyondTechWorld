<?php

namespace App\Services\Assistant;

use App\Assistant\AssistantKnowledge;
use App\Booking;
use App\InternshipEnrolment;
use App\InternshipTaskAssignment;
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
        $enrolment = $this->enrolment($context);
        if (! $enrolment) {
            return ['success' => false, 'error' => 'not_found'];
        }

        return ['success' => true, 'status' => $enrolment->status, 'enrolment_id' => $enrolment->id];
    }

    protected function toolGetCurrentInternshipTask(array $params, array $context)
    {
        $enrolment = $this->enrolment($context);
        if (! $enrolment || ! Schema::hasTable('internship_task_assignments')) {
            return ['success' => false, 'error' => 'not_found'];
        }
        $assignment = InternshipTaskAssignment::where('enrolment_id', $enrolment->id)
            ->whereNotIn('status', ['completed', 'cancelled', 'withdrawn'])
            ->orderByDesc('id')
            ->first();
        if (! $assignment) {
            return ['success' => false, 'error' => 'not_found'];
        }
        $title = null;
        if ($assignment->program_task_id && Schema::hasTable('internship_program_tasks') && $assignment->task) {
            $title = $assignment->task->title ?: $assignment->task->name;
        }

        return [
            'success' => true,
            'assignment_id' => $assignment->id,
            'status' => $assignment->status,
            'title' => $title ?: ('Task #'.$assignment->id),
            'scheduled_work_date' => (string) $assignment->scheduled_work_date,
        ];
    }

    protected function toolGetInternshipProgress(array $params, array $context)
    {
        $enrolment = $this->enrolment($context);
        if (! $enrolment || ! Schema::hasTable('internship_task_assignments')) {
            return ['success' => false, 'error' => 'not_found'];
        }
        $total = InternshipTaskAssignment::where('enrolment_id', $enrolment->id)->count();
        $done = InternshipTaskAssignment::where('enrolment_id', $enrolment->id)->where('status', 'completed')->count();

        return ['success' => true, 'released' => $total, 'completed' => $done, 'status' => $enrolment->status];
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

    protected function enrolment(array $context)
    {
        $userId = isset($context['intern_user_id']) ? (int) $context['intern_user_id'] : 0;
        if (! $userId || ! Schema::hasTable('internship_enrolments')) {
            return null;
        }

        return InternshipEnrolment::where('student_user_id', $userId)->orderByDesc('id')->first();
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

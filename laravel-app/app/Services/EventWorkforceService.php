<?php

namespace App\Services;

use App\Event;
use App\EventAssignment;
use App\EventLabourBudget;
use App\EventWorkerCategory;
use App\EventWorkerProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EventWorkforceService
{
    const SPECIALIZATIONS = [
        'live_sound' => 'Live Sound Engineering',
        'lighting' => 'Lighting Engineering',
        'led_screen' => 'LED Screen Engineering',
        'video' => 'Video Production',
        'networking' => 'Networking',
        'electrical' => 'Electrical Installation',
        'rigging' => 'Rigging',
        'stage_management' => 'Stage Management',
        'loading' => 'Equipment Loading',
        'transport' => 'Equipment Transportation',
        'software' => 'Software Engineering',
        'cybersecurity' => 'Cybersecurity',
        'photography' => 'Photography',
        'camera' => 'Camera Operation',
        'general_labour' => 'General Labour',
        'other' => 'Other',
    ];

    public function createProfile(array $data)
    {
        return EventWorkerProfile::create($data);
    }

    public function assignWorker(Event $event, EventWorkerProfile $profile, array $data)
    {
        return DB::transaction(function () use ($event, $profile, $data) {
            $this->assertNoDuplicateAssignment($event, $profile, $data);

            $category = $profile->category;
            $dailyRate = (int) ($data['event_daily_rate'] ?? $profile->standard_daily_rate ?? ($category ? $category->default_daily_rate : 0));
            $expectedDays = (int) ($data['expected_days'] ?? 1);
            $method = $data['compensation_method'] ?? 'daily';

            $expectedTotal = $this->calculateExpectedTotal($method, $dailyRate, $expectedDays, $data);

            $assignment = EventAssignment::create([
                'event_id' => $event->id,
                'worker_profile_id' => $profile->id,
                'assignment_role' => $data['assignment_role'] ?? null,
                'is_supervisor' => ! empty($data['is_supervisor']),
                'reporting_time' => $data['reporting_time'] ?? null,
                'work_start_date' => $data['work_start_date'] ?? null,
                'work_end_date' => $data['work_end_date'] ?? null,
                'expected_days' => $expectedDays,
                'default_daily_rate' => $profile->standard_daily_rate,
                'event_daily_rate' => $dailyRate,
                'hourly_rate' => $data['hourly_rate'] ?? $profile->standard_hourly_rate,
                'fixed_amount' => $data['fixed_amount'] ?? null,
                'compensation_method' => $method,
                'expected_total' => $expectedTotal,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->refreshLabourBudgetAllocation($event);

            return $assignment;
        });
    }

    public function calculateExpectedTotal($method, $dailyRate, $expectedDays, array $data)
    {
        switch ($method) {
            case 'hourly':
                $hours = (float) ($data['expected_hours'] ?? 8);
                $rate = (int) ($data['hourly_rate'] ?? 0);

                return (int) round($hours * $rate);
            case 'fixed':
            case 'manual':
                return (int) ($data['fixed_amount'] ?? 0);
            case 'daily':
            default:
                return (int) ($dailyRate * $expectedDays);
        }
    }

    public function assertNoDuplicateAssignment(Event $event, EventWorkerProfile $profile, array $data)
    {
        $role = $data['assignment_role'] ?? '';
        $start = $data['work_start_date'] ?? null;
        $end = $data['work_end_date'] ?? $start;

        $exists = EventAssignment::where('event_id', $event->id)
            ->where('worker_profile_id', $profile->id)
            ->where('assignment_role', $role)
            ->when($start, function ($q) use ($start, $end) {
                $q->where(function ($w) use ($start, $end) {
                    $w->whereBetween('work_start_date', [$start, $end])
                        ->orWhereBetween('work_end_date', [$start, $end])
                        ->orWhere(function ($x) use ($start, $end) {
                            $x->where('work_start_date', '<=', $start)->where('work_end_date', '>=', $end);
                        });
                });
            })
            ->exists();

        if ($exists) {
            throw new \InvalidArgumentException('This worker is already assigned to the same role for this date period.');
        }
    }

    /** @return array<int, array{event: Event, assignment: EventAssignment}> */
    public function overlappingAssignments(EventWorkerProfile $profile, $startDate, $endDate, $excludeEventId = null)
    {
        if (! $startDate) {
            return [];
        }
        $endDate = $endDate ?: $startDate;

        $assignments = EventAssignment::with('event')
            ->where('worker_profile_id', $profile->id)
            ->when($excludeEventId, function ($q) use ($excludeEventId) {
                $q->where('event_id', '!=', $excludeEventId);
            })
            ->whereNotNull('work_start_date')
            ->get();

        $conflicts = [];
        foreach ($assignments as $a) {
            $aStart = $a->work_start_date;
            $aEnd = $a->work_end_date ?: $aStart;
            if ($startDate <= $aEnd && $endDate >= $aStart) {
                $conflicts[] = ['event' => $a->event, 'assignment' => $a];
            }
        }

        return $conflicts;
    }

    public function rentalScheduleWarning(Event $event)
    {
        if (! $event->booking_id || ! $event->booking) {
            return null;
        }

        $booking = $event->booking;
        $products = $booking->bookingProduct;
        if (! $products || $products->isEmpty()) {
            return null;
        }

        $rentalStart = $products->min('start');
        $rentalEnd = $products->max('end');
        $opStart = $event->packing_at ?: $event->setup_start_at ?: $event->event_start_at;
        $opEnd = $event->return_at ?: $event->event_end_at ?: $event->dismantling_end_at;

        if (! $opStart || ! $rentalStart || ! $rentalEnd) {
            return null;
        }

        $warnings = [];
        if (Carbon::parse($rentalStart)->gt($opStart)) {
            $warnings[] = 'Rental starts after operational packing/setup — equipment may not be covered.';
        }
        if ($opEnd && Carbon::parse($rentalEnd)->lt($opEnd)) {
            $warnings[] = 'Rental ends before equipment return — extend rental dates.';
        }

        return $warnings ? implode(' ', $warnings) : null;
    }

    public function saveLabourBudget(Event $event, array $data)
    {
        $budget = $event->labourBudget ?: new EventLabourBudget(['event_id' => $event->id]);
        $budget->fill($data);
        $budget->save();
        $this->refreshLabourBudgetAllocation($event);

        return $budget->fresh();
    }

    public function refreshLabourBudgetAllocation(Event $event)
    {
        $budget = $event->labourBudget;
        if (! $budget) {
            return;
        }
        $allocated = (int) $event->assignments()->sum('expected_total');
        $budget->allocated_amount = $allocated;
        $budget->save();
    }

    public function searchPeople($term)
    {
        $term = '%' . $term . '%';
        $profiles = EventWorkerProfile::with('category')
            ->where(function ($q) use ($term) {
                $q->where('telephone', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('specialization', 'like', $term);
            })
            ->limit(20)
            ->get();

        $customers = \App\Customer::where('name', 'like', $term)
            ->orWhere('phone_number', 'like', $term)
            ->limit(10)
            ->get(['id', 'name', 'phone_number', 'email']);

        return compact('profiles', 'customers');
    }

    public function profileFromCustomer($customerId, $categoryId = null)
    {
        $customer = \App\Customer::findOrFail($customerId);
        $existing = EventWorkerProfile::where('customer_id', $customerId)->first();
        if ($existing) {
            return $existing;
        }

        $category = $categoryId
            ? EventWorkerCategory::find($categoryId)
            : EventWorkerCategory::active()->first();

        return $this->createProfile([
            'customer_id' => $customer->id,
            'worker_category_id' => $category ? $category->id : null,
            'standard_daily_rate' => $category ? $category->default_daily_rate : 0,
            'telephone' => $customer->phone_number,
            'email' => $customer->email,
            'specialization' => 'general_labour',
            'is_active' => true,
        ]);
    }
}

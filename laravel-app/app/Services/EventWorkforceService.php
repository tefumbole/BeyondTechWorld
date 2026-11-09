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
            $foodPerDay = max(0, (int) (isset($data['food_per_day']) ? $data['food_per_day'] : 0));

            $expectedTotal = $this->calculateExpectedTotal($method, $dailyRate, $expectedDays, $data, $foodPerDay);

            $assignment = EventAssignment::create([
                'event_id' => $event->id,
                'worker_profile_id' => $profile->id,
                'assignment_role' => $data['assignment_role'] ?? null,
                'is_supervisor' => ! empty($data['is_supervisor']),
                'reporting_time' => $data['reporting_time'] ?? null,
                'work_start_date' => $data['work_start_date'] ?? null,
                'work_end_date' => $data['work_end_date'] ?? null,
                'work_dates' => isset($data['work_dates']) ? $data['work_dates'] : null,
                'expected_days' => $expectedDays,
                'default_daily_rate' => $profile->standard_daily_rate,
                'event_daily_rate' => $dailyRate,
                'hourly_rate' => $data['hourly_rate'] ?? $profile->standard_hourly_rate,
                'fixed_amount' => $data['fixed_amount'] ?? null,
                'compensation_method' => $method,
                'expected_total' => $expectedTotal,
                'food_per_day' => $foodPerDay,
                'notes' => $foodPerDay > 0 ? 'Food money '.number_format($foodPerDay).' XAF per day' : ($data['notes'] ?? null),
            ]);

            $this->refreshLabourBudgetAllocation($event);

            return $assignment;
        });
    }

    public function writeAssignment(Event $event, EventWorkerProfile $profile, array $data, $assignmentId = null)
    {
        return DB::transaction(function () use ($event, $profile, $data, $assignmentId) {
            $assignment = null;
            if ($assignmentId) {
                $assignment = EventAssignment::where('event_id', $event->id)->where('id', $assignmentId)->first();
            }
            if (! $assignment) {
                $assignment = EventAssignment::where('event_id', $event->id)
                    ->where('worker_profile_id', $profile->id)
                    ->first();
            }

            $days = Event::parseWorkDays(isset($data['work_dates']) ? $data['work_dates'] : []);
            $dates = array();
            foreach ($days as $day) {
                $dates[] = $day['date'];
            }
            $expectedDays = count($dates) > 0 ? count($dates) : max(1, (int) (isset($data['expected_days']) ? $data['expected_days'] : 1));
            $dailyRate = (int) (isset($data['event_daily_rate']) ? $data['event_daily_rate'] : 0);
            $foodPerDay = max(0, (int) (isset($data['food_per_day']) ? $data['food_per_day'] : 0));
            $start = count($dates) ? $dates[0] : (isset($data['work_start_date']) ? $data['work_start_date'] : null);
            $end = count($dates) ? $dates[count($dates) - 1] : (isset($data['work_end_date']) ? $data['work_end_date'] : $start);
            $total = 0;
            if (count($days)) {
                foreach ($days as $day) {
                    $total += $day['pay_amount'] !== null ? (int) $day['pay_amount'] : ($dailyRate + $foodPerDay);
                }
            } else {
                $total = $this->calculateExpectedTotal('daily', $dailyRate, $expectedDays, [], $foodPerDay);
            }
            $fields = [
                'assignment_role' => isset($data['assignment_role']) ? $data['assignment_role'] : null,
                'work_start_date' => $start,
                'work_end_date' => $end,
                'work_dates' => count($days) ? json_encode($days) : null,
                'expected_days' => $expectedDays,
                'event_daily_rate' => $dailyRate,
                'default_daily_rate' => $profile->standard_daily_rate,
                'compensation_method' => 'daily',
                'expected_total' => $total,
                'food_per_day' => $foodPerDay,
                'is_supervisor' => ! empty($data['is_supervisor']),
                'notes' => $foodPerDay > 0 ? 'Food money '.number_format($foodPerDay).' XAF per day' : null,
            ];

            if ($assignment) {
                $assignment->fill($fields);
                $assignment->save();
            } else {
                $this->assertNoDuplicateAssignment($event, $profile, [
                    'assignment_role' => $fields['assignment_role'],
                    'work_start_date' => $start,
                    'work_end_date' => $end,
                ]);
                $fields['event_id'] = $event->id;
                $fields['worker_profile_id'] = $profile->id;
                $assignment = EventAssignment::create($fields);
            }

            $this->refreshLabourBudgetAllocation($event);

            return $assignment;
        });
    }

    public function dropAssignment(EventAssignment $assignment)
    {
        if ($assignment->payments()->where('status', 'paid')->exists()) {
            return false;
        }
        foreach ($assignment->timesheets as $sheet) {
            $sheet->entries()->delete();
            $sheet->delete();
        }
        $assignment->payments()->delete();
        $contract = $assignment->contract;
        if ($contract) {
            $contract->delete();
        }
        $assignment->delete();

        return true;
    }

    public function calculateExpectedTotal($method, $dailyRate, $expectedDays, array $data, $foodPerDay = 0)
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
                return (int) (($dailyRate + (int) $foodPerDay) * $expectedDays);
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
        $allocated = (int) $event->assignments()->where(function ($query) {
            $query->whereNull('acceptance_status')->orWhere('acceptance_status', '!=', 'rejected');
        })->sum('expected_total');
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

    public function profileFromUser($userId, $categoryId = null)
    {
        $user = \App\User::findOrFail($userId);
        $existing = EventWorkerProfile::where('user_id', $user->id)->first();
        if ($existing) {
            return $existing;
        }
        $existing = $this->profileByPhone($user->phone);
        if ($existing) {
            if (! $existing->user_id) {
                $existing->user_id = $user->id;
                $existing->save();
            }

            return $existing;
        }

        return $this->createProfile($this->profileAttributes($categoryId, [
            'user_id' => $user->id,
            'telephone' => $user->phone,
            'email' => $user->email,
        ]));
    }

    public function profileFromEmployee($employeeId, $categoryId = null)
    {
        $employee = \App\Employee::findOrFail($employeeId);
        if ($employee->user_id) {
            $profile = $this->profileFromUser($employee->user_id, $categoryId);
            if (! $profile->telephone && $employee->phone_number) {
                $profile->telephone = $employee->phone_number;
                $profile->save();
            }

            return $profile;
        }
        $existing = $this->profileByPhone($employee->phone_number);
        if ($existing) {
            return $existing;
        }
        $customer = $this->customerFromContact($employee->name, $employee->phone_number, $employee->email, $employee->city);

        return $this->profileFromCustomer($customer->id, $categoryId);
    }

    public function profileFromPortal($portalId, $categoryId = null)
    {
        $portal = \App\BeyondUser::findOrFail($portalId);
        $existing = EventWorkerProfile::where('be_user_id', $portal->id)->first();
        if ($existing) {
            return $existing;
        }
        $existing = $this->profileByPhone($portal->phone);
        if ($existing) {
            if (! $existing->be_user_id) {
                $existing->be_user_id = $portal->id;
                $existing->save();
            }

            return $existing;
        }

        return $this->createProfile($this->profileAttributes($categoryId, [
            'be_user_id' => $portal->id,
            'telephone' => $portal->phone,
            'email' => $portal->email,
        ]));
    }

    protected function profileByPhone($phone)
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($digits) < 6) {
            return null;
        }
        $tail = substr($digits, -9);

        return EventWorkerProfile::where('telephone', 'like', '%'.$tail)->first();
    }

    protected function profileAttributes($categoryId, array $extra)
    {
        $category = $categoryId ? EventWorkerCategory::find($categoryId) : null;

        return array_merge([
            'worker_category_id' => $category ? $category->id : null,
            'standard_daily_rate' => $category ? $category->default_daily_rate : 0,
            'specialization' => 'general_labour',
            'is_active' => true,
        ], $extra);
    }

    protected function customerFromContact($name, $phone, $email = null, $city = null)
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        $tail = strlen($digits) >= 6 ? substr($digits, -9) : '';
        $customer = null;
        if ($tail !== '') {
            $customer = \App\Customer::where('phone_number', 'like', '%'.$tail)->first();
        }
        if (! $customer && $email) {
            $customer = \App\Customer::where('email', $email)->first();
        }
        if ($customer) {
            return $customer;
        }
        $groupId = \App\CustomerGroup::query()->value('id');

        return \App\Customer::create([
            'customer_group_id' => $groupId ? $groupId : 1,
            'name' => $name ?: 'Crew',
            'phone_number' => $phone ?: 'N/A',
            'email' => $email,
            'address' => 'N/A',
            'city' => $city ?: 'N/A',
            'is_active' => true,
        ]);
    }

    public function rememberRole(EventWorkerProfile $profile, EventWorkerCategory $category)
    {
        $profile->worker_category_id = $category->id;
        $profile->standard_daily_rate = (int) $category->default_daily_rate;
        $profile->save();

        return $profile;
    }
}

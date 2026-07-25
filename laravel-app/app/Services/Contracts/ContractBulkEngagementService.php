<?php

namespace App\Services\Contracts;

use App\BtwContract;
use App\ContractSetting;
use App\ContractTemplate;
use App\Event;
use App\EventAssignment;

/**
 * Bulk-create enterprise Engineer Engagement contracts from event workforce assignments.
 */
class ContractBulkEngagementService
{
    protected $instances;

    public function __construct(ContractInstanceService $instances)
    {
        $this->instances = $instances;
    }

    /**
     * @return array{created: BtwContract[], skipped: array, errors: array}
     */
    public function createForAssignments(Event $event, array $assignmentIds)
    {
        $template = ContractTemplate::with('currentVersion')
            ->where('code', 'ENG-EVENT')
            ->where('active', true)
            ->first();
        if (! $template || ! $template->currentVersion) {
            throw new \RuntimeException('Active Engineer Engagement template (ENG-EVENT) not found.');
        }

        $companyName = ContractSetting::getValue('company_legal_name', 'Beyond Enterprise');
        $companyAddress = ContractSetting::getValue('company_address', '');

        $created = [];
        $skipped = [];
        $errors = [];

        $assignments = EventAssignment::with('workerProfile.customer', 'workerProfile.user')
            ->where('event_id', $event->id)
            ->whereIn('id', $assignmentIds)
            ->get();

        foreach ($assignments as $assignment) {
            $profile = $assignment->workerProfile;
            $name = $profile ? $profile->displayName() : 'Worker';

            $already = BtwContract::where('primary_link_type', 'event')
                ->where('primary_link_id', (string) $event->id)
                ->where('purpose', 'like', '%assignment:'.$assignment->id.'%')
                ->whereNotIn('status', ['cancelled', 'superseded'])
                ->exists();
            if ($already) {
                $skipped[] = ['assignment_id' => $assignment->id, 'name' => $name, 'reason' => 'Contract already exists'];
                continue;
            }

            try {
                $customer = $profile ? $profile->customer : null;
                $user = $profile ? $profile->user : null;
                $rate = (int) ($assignment->event_daily_rate ?: $assignment->default_daily_rate ?: 0);
                $days = (int) ($assignment->expected_days ?: 1);
                $contract = $this->instances->createFromTemplate($template, [
                    'title' => 'Engineer Engagement — '.$name.' / '.$event->name,
                    'effective_date' => optional($event->event_start_at)->toDateString() ?: now()->toDateString(),
                    'start_date' => optional($assignment->work_start_date ?: $event->event_start_at)->toDateString(),
                    'end_date' => optional($assignment->work_end_date ?: $event->event_end_at)->toDateString(),
                    'value' => $rate * max(1, $days),
                    'currency' => 'XAF',
                    'purpose' => 'Event workforce engagement (assignment:'.$assignment->id.')',
                    'link_type' => 'event',
                    'link_id' => (string) $event->id,
                    'party_a' => [
                        'name' => $companyName,
                        'address' => $companyAddress,
                        'subject_type' => 'company',
                    ],
                    'party_b' => [
                        'name' => $name,
                        'email' => $customer->email ?? ($user->email ?? ''),
                        'phone' => $customer->phone_number ?? '',
                        'address' => $customer->address ?? '',
                        'subject_type' => $customer ? 'customer' : ($user ? 'user' : null),
                        'subject_id' => $customer ? (string) $customer->id : ($user ? (string) $user->id : null),
                    ],
                    'worker_role' => $assignment->assignment_role,
                    'worker_daily_rate' => number_format($rate, 0),
                    'work_estimated_days' => (string) $days,
                ]);
                $created[] = $contract;
            } catch (\Throwable $e) {
                $errors[] = ['assignment_id' => $assignment->id, 'name' => $name, 'error' => $e->getMessage()];
            }
        }

        return compact('created', 'skipped', 'errors');
    }
}

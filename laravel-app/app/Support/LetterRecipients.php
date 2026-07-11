<?php

namespace App\Support;

use App\Customer;
use App\CustomerGroup;
use App\Employee;
use App\User;
use Illuminate\Support\Collection;

class LetterRecipients
{
    public static function customers(): Collection
    {
        self::syncMissingCustomerRecords();

        return Customer::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone_number', 'email']);
    }

    public static function employees(): Collection
    {
        return Employee::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone_number', 'email']);
    }

    public static function allCustomerIds(): array
    {
        return self::customers()->pluck('id')->all();
    }

    public static function allEmployeeIds(): array
    {
        return self::employees()->pluck('id')->all();
    }

    public static function encodeAllRecipients(): string
    {
        return 'c:' . implode(',', self::allCustomerIds()) . '|e:' . implode(',', self::allEmployeeIds());
    }

    public static function eachRecipient(string $peopleType, string $to, callable $callback): void
    {
        if ($peopleType === 'all') {
            if (preg_match('/c:([^|]*)/', $to, $customerMatch)) {
                foreach (array_filter(explode(',', $customerMatch[1])) as $id) {
                    $recipient = Customer::find($id);
                    if ($recipient) {
                        $callback($recipient, Customer::class, $id);
                    }
                }
            }

            if (preg_match('/e:([^|]*)/', $to, $employeeMatch)) {
                foreach (array_filter(explode(',', $employeeMatch[1])) as $id) {
                    $recipient = Employee::find($id);
                    if ($recipient) {
                        $callback($recipient, Employee::class, $id);
                    }
                }
            }

            return;
        }

        $model = $peopleType === 'customer' ? Customer::class : Employee::class;

        foreach (array_filter(explode(',', $to)) as $id) {
            $recipient = $model::find($id);
            if ($recipient) {
                $callback($recipient, $model, $id);
            }
        }
    }

    public static function recipientModel(string $peopleType): string
    {
        return in_array($peopleType, ['customer', 'all'], true) ? Customer::class : Employee::class;
    }

    protected static function syncMissingCustomerRecords(): void
    {
        $linkedUserIds = Customer::whereNotNull('user_id')->pluck('user_id')->filter()->all();
        $defaultGroupId = CustomerGroup::where('is_active', true)->value('id') ?? 1;

        $orphanUsers = User::where('role_id', 5)
            ->where('is_active', true)
            ->where('is_deleted', false)
            ->when(!empty($linkedUserIds), function ($query) use ($linkedUserIds) {
                $query->whereNotIn('id', $linkedUserIds);
            })
            ->get(['id', 'name', 'phone', 'email']);

        foreach ($orphanUsers as $user) {
            Customer::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'customer_group_id' => $defaultGroupId,
                    'name' => $user->name,
                    'phone_number' => $user->phone ?: 'NAN',
                    'email' => $user->email,
                    'address' => 'NAN',
                    'city' => 'NAN',
                    'is_active' => true,
                ]
            );
        }
    }
}

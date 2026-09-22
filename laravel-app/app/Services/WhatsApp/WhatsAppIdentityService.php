<?php

namespace App\Services\WhatsApp;

use App\Application;
use App\Customer;
use App\Employee;
use App\InternshipEnrolment;
use App\Supplier;
use App\Support\WhatsAppPhone;
use App\User;
use Illuminate\Support\Facades\Schema;

class WhatsAppIdentityService
{
    /**
     * Every ERP identity that matches this phone. One number may have many roles.
     *
     * @return array<int, array{role:string,type:string,id:int,name:?string,label:string}>
     */
    public function resolve($phone)
    {
        $normalized = $this->normalize($phone);
        if ($normalized === '') {
            return [];
        }

        $matches = [];
        $this->collectUsers($normalized, $matches);
        $this->collectEmployees($normalized, $matches);
        $this->collectCustomers($normalized, $matches);
        $this->collectSuppliers($normalized, $matches);
        $this->collectApplicants($normalized, $matches);

        return $matches;
    }

    public function normalize($phone)
    {
        return WhatsAppPhone::sanitizeForStorage($phone);
    }

    public function display($phone)
    {
        return WhatsAppPhone::display($phone);
    }

    protected function collectUsers($normalized, array &$matches)
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $variants = $this->variants($normalized);
        $users = User::query()
            ->where(function ($q) {
                $q->where('is_deleted', false)->orWhereNull('is_deleted');
            })
            ->where(function ($q) use ($variants) {
                $q->whereIn('phone', $variants)->orWhereIn('additional_phone', $variants);
            })
            ->get();

        $internIds = [];
        if (Schema::hasTable('internship_enrolments')) {
            $internIds = InternshipEnrolment::query()
                ->whereIn('student_user_id', $users->pluck('id')->all())
                ->pluck('student_user_id')
                ->all();
        }

        foreach ($users as $user) {
            $matches[] = $this->row('user', User::class, $user->id, $user->name, 'User');
            if (in_array((int) $user->id, array_map('intval', $internIds), true)) {
                $matches[] = $this->row('intern', User::class, $user->id, $user->name, 'Intern');
            }
        }
    }

    protected function collectEmployees($normalized, array &$matches)
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        Employee::query()
            ->whereIn('phone_number', $this->variants($normalized))
            ->get()
            ->each(function ($employee) use (&$matches) {
                $matches[] = $this->row('employee', Employee::class, $employee->id, $employee->name, 'Employee');
            });
    }

    protected function collectCustomers($normalized, array &$matches)
    {
        if (! Schema::hasTable('customers')) {
            return;
        }

        Customer::query()
            ->whereIn('phone_number', $this->variants($normalized))
            ->get()
            ->each(function ($customer) use (&$matches) {
                $matches[] = $this->row('customer', Customer::class, $customer->id, $customer->name, 'Customer');
            });
    }

    protected function collectSuppliers($normalized, array &$matches)
    {
        if (! Schema::hasTable('suppliers')) {
            return;
        }

        Supplier::query()
            ->whereIn('phone_number', $this->variants($normalized))
            ->get()
            ->each(function ($supplier) use (&$matches) {
                $matches[] = $this->row('supplier', Supplier::class, $supplier->id, $supplier->name, 'Supplier');
            });
    }

    protected function collectApplicants($normalized, array &$matches)
    {
        if (! Schema::hasTable('applications')) {
            return;
        }

        $variants = $this->variants($normalized);
        Application::query()
            ->where(function ($q) use ($variants) {
                $q->whereIn('phone', $variants)->orWhereIn('whatsapp_number', $variants);
            })
            ->get()
            ->each(function ($app) use (&$matches) {
                $matches[] = $this->row('applicant', Application::class, $app->id, $app->full_name, 'Applicant');
            });
    }

    protected function variants($normalized)
    {
        $cc = WhatsAppPhone::countryCode();
        $local = strpos($normalized, $cc) === 0 ? substr($normalized, strlen($cc)) : $normalized;

        return array_values(array_unique(array_filter([
            $normalized,
            '+'.$normalized,
            $local,
            '0'.$local,
        ])));
    }

    protected function row($role, $type, $id, $name, $label)
    {
        return [
            'role' => $role,
            'type' => $type,
            'id' => (int) $id,
            'name' => $name,
            'label' => $label,
        ];
    }
}

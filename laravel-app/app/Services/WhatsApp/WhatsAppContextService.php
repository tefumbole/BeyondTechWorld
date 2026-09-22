<?php

namespace App\Services\WhatsApp;

use App\Booking;
use App\Customer;
use App\Employee;
use App\InternshipEnrolment;
use App\Quotation;
use App\Sale;
use App\User;
use App\WhatsApp\Lead;
use App\WhatsApp\LeadCatalog;
use App\WhatsApp\WhatsAppContact;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppSetting;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class WhatsAppContextService
{
    public function forConversation(WhatsAppConversation $conversation, $user)
    {
        $contact = $conversation->contact;
        $roles = [];
        if ($contact) {
            $contact->load('links');
            foreach ($contact->links as $link) {
                $roles[] = $link->role;
            }
        }
        $lead = null;
        if ($contact) {
            $lead = Lead::where('contact_id', $contact->id)->orderByDesc('id')->first();
        }

        return [
            'roles' => $roles,
            'customer' => $this->can($user, ['customers-index', 'customers-edit', 'whatsapp.manage'])
                ? $this->customerBlock($contact) : null,
            'intern' => $this->can($user, ['internship.view', 'internship.manage', 'whatsapp.manage'])
                ? $this->internBlock($contact) : null,
            'employee' => $this->can($user, ['employees-index', 'whatsapp.manage'])
                ? $this->employeeBlock($contact) : null,
            'lead' => $lead,
            'sla' => $this->sla(),
        ];
    }

    public function sla()
    {
        return [
            'normal' => max(1, (int) WhatsAppSetting::getValue('sla_normal_minutes', 30)),
            'warning' => max(1, (int) WhatsAppSetting::getValue('sla_warning_minutes', 60)),
            'critical' => max(1, (int) WhatsAppSetting::getValue('sla_critical_minutes', 240)),
        ];
    }

    protected function customerBlock(WhatsAppContact $contact = null)
    {
        if (! $contact || ! Schema::hasTable('customers')) {
            return null;
        }
        $customer = $this->linked($contact, 'customer', Customer::class);
        if (! $customer) {
            $customer = app(WhatsAppLeadService::class)->findExistingCustomer($contact->normalized_phone);
        }
        if (! $customer) {
            return null;
        }
        $block = [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone_number,
            'bookings' => [],
            'balance' => null,
            'quotation' => null,
            'payment' => null,
        ];
        if (Schema::hasTable('bookings') && class_exists(Booking::class)) {
            $block['bookings'] = Booking::where('customer_id', $customer->id)->orderByDesc('id')->limit(5)->get();
        }
        if (Schema::hasTable('quotations') && class_exists(Quotation::class)) {
            $block['quotation'] = Quotation::where('customer_id', $customer->id)->orderByDesc('id')->first();
        }
        if (Schema::hasTable('sales') && class_exists(Sale::class)) {
            $sale = Sale::where('customer_id', $customer->id)->orderByDesc('id')->first();
            $block['payment'] = $sale;
            if (isset($customer->deposit) || isset($customer->expense)) {
                $block['balance'] = (float) ($customer->deposit ?? 0) - (float) ($customer->expense ?? 0);
            }
        }

        return $block;
    }

    protected function internBlock(WhatsAppContact $contact = null)
    {
        if (! $contact || ! Schema::hasTable('internship_enrolments')) {
            return null;
        }
        $userId = null;
        foreach ($contact->links as $link) {
            if ($link->role === 'intern') {
                $userId = $link->linkable_id;
            }
        }
        if (! $userId) {
            return null;
        }
        $enrolment = InternshipEnrolment::where('student_user_id', $userId)->orderByDesc('id')->first();
        $user = User::find($userId);

        return [
            'user_id' => $userId,
            'name' => $user ? $user->name : null,
            'enrolment' => $enrolment,
            'status' => $enrolment ? $enrolment->status : null,
            'program_id' => $enrolment && isset($enrolment->program_id) ? $enrolment->program_id : null,
        ];
    }

    protected function employeeBlock(WhatsAppContact $contact = null)
    {
        if (! $contact || ! Schema::hasTable('employees')) {
            return null;
        }
        $employee = $this->linked($contact, 'employee', Employee::class);
        if (! $employee) {
            return null;
        }

        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'user_id' => $employee->user_id,
        ];
    }

    protected function linked(WhatsAppContact $contact, $role, $class)
    {
        foreach ($contact->links as $link) {
            if ($link->role === $role && $link->linkable) {
                return $link->linkable;
            }
            if ($link->role === $role && class_exists($class)) {
                return $class::find($link->linkable_id);
            }
        }

        return null;
    }

    protected function can($user, array $names)
    {
        if (! $user) {
            return false;
        }
        $role = Role::find($user->role_id);
        if (! $role) {
            return false;
        }
        foreach ($names as $name) {
            try {
                if ($role->hasPermissionTo($name)) {
                    return true;
                }
            } catch (\Exception $e) {
            }
        }

        return false;
    }
}

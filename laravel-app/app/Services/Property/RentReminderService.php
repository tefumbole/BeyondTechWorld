<?php

namespace App\Services\Property;

use App\Customer;
use App\Property\RentObligation;
use App\Property\Tenancy;
use App\Services\WhatsApp\WhatsAppConversationService;
use App\Services\WhatsApp\WhatsAppIdentityService;
use App\WhatsApp\WhatsAppContact;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RentReminderService
{
    protected $billing;
    protected $log;

    public function __construct(RentBillingService $billing, PropertyActivityLogger $log)
    {
        $this->billing = $billing;
        $this->log = $log;
    }

    public function run($asOf = null)
    {
        $today = $asOf ? Carbon::parse($asOf)->startOfDay() : Carbon::today();
        $before = max(0, (int) config('services.property.reminder_days_before', 3));
        $sent = 0;
        $rows = RentObligation::query()->orderBy('id')->get();
        foreach ($rows as $row) {
            $row = $this->billing->refresh($row, $today);
            if (in_array($row->status, ['PAID', 'WAIVED', 'CANCELLED'], true) || $row->balance() <= 0) {
                continue;
            }
            $due = Carbon::parse($row->due_date)->startOfDay();
            $type = null;
            if ($due->equalTo($today)) {
                $type = 'on_due';
            } elseif ($before > 0 && $due->equalTo($today->copy()->addDays($before))) {
                $type = 'before_due';
            } elseif ($due->lt($today)) {
                $type = 'overdue';
            }
            if (! $type) {
                continue;
            }
            $exists = DB::table('rent_reminder_logs')
                ->where('rent_obligation_id', $row->id)
                ->where('reminder_type', $type)
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('rent_reminder_logs')->insert([
                'tenancy_id' => $row->tenancy_id,
                'rent_obligation_id' => $row->id,
                'reminder_type' => $type,
                'period_key' => $row->period_key,
                'sent_at' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
            $tenancy = Tenancy::find($row->tenancy_id);
            $this->log->write('rent_reminder', 'rent_obligation', $row->id, [
                'type' => $type,
                'period' => $row->period_key,
                'tenancy_id' => $row->tenancy_id,
                'customer_id' => $tenancy ? $tenancy->customer_id : null,
                'balance' => $row->balance(),
            ]);
            $this->notify($tenancy, $row, $type);
            $sent++;
        }

        return $sent;
    }

    protected function notify($tenancy, RentObligation $row, $type)
    {
        if (! $tenancy || ! Schema::hasTable('whatsapp_contacts')) {
            return;
        }
        $customer = Customer::find($tenancy->customer_id);
        if (! $customer || ! $customer->phone_number) {
            return;
        }
        try {
            $phone = app(WhatsAppIdentityService::class)->normalize($customer->phone_number);
            $contact = WhatsAppContact::where('normalized_phone', $phone)->first();
            if (! $contact) {
                return;
            }
            $conversation = app(WhatsAppConversationService::class)->openConversation($contact);
            $balance = number_format($row->balance(), 0, '.', ',');
            $currency = (string) config('services.property.currency', 'XAF');
            if ($type === 'before_due') {
                $body = 'Rent for '.$row->period_key.' is due on '.$row->due_date.'. The balance on this tenancy is '.$balance.' '.$currency.'.';
            } elseif ($type === 'on_due') {
                $body = 'Rent for '.$row->period_key.' is due today, '.$row->due_date.'. The balance on this tenancy is '.$balance.' '.$currency.'.';
            } else {
                $body = 'Our records show rent for '.$row->period_key.' is overdue. The balance on this tenancy is '.$balance.' '.$currency.'.';
            }
            app(WhatsAppConversationService::class)->reply($conversation, $body);
        } catch (\Throwable $e) {
            $this->log->write('rent_reminder_send_failed', 'rent_obligation', $row->id, ['type' => $type]);
        }
    }
}

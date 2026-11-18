<?php

namespace App\Console\Commands;

use App\ContractReminder;
use App\Services\Messaging\NotificationRouter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class ProcessContractReminders extends Command
{
    protected $signature = 'contracts:process-reminders {--send} {--id=}';

    protected $description = 'Send a contract signer reminder only when --send is passed';

    public function handle(NotificationRouter $notify)
    {
        if (! $this->option('send')) {
            $this->info('Signer reminders stay off until someone sends one by hand.');

            return 0;
        }

        $due = ContractReminder::with(['contract.signatories', 'contract.partyA', 'contract.partyB'])
            ->where('is_sent', false)
            ->when($this->option('id'), function ($query) {
                $query->where('id', $this->option('id'));
            }, function ($query) {
                $query->where('reminder_time', '<=', now());
            })
            ->orderBy('reminder_time')
            ->limit(100)
            ->get();

        $sent = 0;
        foreach ($due as $reminder) {
            $contract = $reminder->contract;
            if (! $contract) {
                $reminder->is_sent = true;
                $reminder->sent_at = now();
                $reminder->save();
                continue;
            }

            $body = $reminder->message
                ?: ('Reminder for contract '.$contract->number.': '.$contract->title
                    .($reminder->label ? ' ('.$reminder->label.')' : '')
                    .'. Status: '.$contract->statusLabel().'. Open: '.url('/admin/contracts/'.$contract->id));

            $targets = [];
            foreach ($contract->signatories as $sig) {
                if ($sig->phone) {
                    $targets['p:'.$sig->phone] = ['phone' => $sig->phone, 'email' => $sig->email];
                } elseif ($sig->email) {
                    $targets['e:'.$sig->email] = ['phone' => null, 'email' => $sig->email];
                }
            }
            foreach ([$contract->partyA, $contract->partyB] as $party) {
                if (! $party) {
                    continue;
                }
                $snap = $party->snapshot();
                if (! empty($snap['phone'])) {
                    $targets['p:'.$snap['phone']] = ['phone' => $snap['phone'], 'email' => $snap['email'] ?? null];
                } elseif (! empty($snap['email'])) {
                    $targets['e:'.$snap['email']] = ['phone' => null, 'email' => $snap['email']];
                }
            }

            foreach ($targets as $t) {
                if (! empty($t['phone'])) {
                    try {
                        $notify->sendWhatsAppText($t['phone'], $body);
                    } catch (\Throwable $e) {
                        \Log::warning('[contract-reminder] WhatsApp: '.$e->getMessage());
                    }
                }
                if (! empty($t['email'])) {
                    try {
                        Mail::raw($body, function ($m) use ($t, $contract) {
                            $m->to($t['email'])->subject('Contract reminder: '.$contract->number);
                        });
                    } catch (\Throwable $e) {
                        \Log::warning('[contract-reminder] Email: '.$e->getMessage());
                    }
                }
            }

            $reminder->is_sent = true;
            $reminder->sent_at = now();
            $reminder->save();
            $sent++;
        }

        $this->info("Processed {$sent} contract reminder(s).");

        return 0;
    }
}

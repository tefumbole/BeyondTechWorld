<?php

namespace App\Console\Commands;

use App\BtwContract;
use App\ContractSetting;
use App\ContractSignatory;
use App\SignatureRequest;
use App\Services\Messaging\NotificationRouter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendContractSignatureReminders extends Command
{
    protected $signature = 'contracts:send-signature-reminders {--send}';

    protected $description = 'Remind pending contract signatories only when --send is passed';

    public function handle(NotificationRouter $notify)
    {
        if (! $this->option('send')) {
            $this->info('Signer reminders stay off until someone sends one by hand.');

            return 0;
        }

        $first = (int) ContractSetting::getValue('reminder_first_hours', 48);
        $every = (int) ContractSetting::getValue('reminder_every_hours', 72);
        $max = (int) ContractSetting::getValue('reminder_max', 5);

        $contracts = BtwContract::whereIn('status', [
            BtwContract::STATUS_AWAITING_CLIENT,
            BtwContract::STATUS_AWAITING_ADMIN,
        ])->get();

        $sent = 0;
        foreach ($contracts as $contract) {
            $pending = $contract->signatories()->where('status', 'pending')->get();
            foreach ($pending as $sig) {
                /** @var ContractSignatory $sig */
                $req = SignatureRequest::where('signatory_id', $sig->id)
                    ->whereNull('revoked_at')
                    ->orderByDesc('sent_at')
                    ->first();
                if (! $req || ! $req->sent_at) {
                    continue;
                }
                if ((int) $req->attempts >= $max) {
                    continue;
                }
                $hoursSinceFirst = $req->sent_at->diffInHours(now());
                $threshold = $first + (max(0, ((int) $req->attempts - 1)) * $every);
                if ($hoursSinceFirst < $threshold) {
                    continue;
                }

                $url = url('/admin/contracts/'.$contract->id);
                // Prefer reusing last portal path message without exposing hash; ask admin to resend for new token
                $msg = "Reminder: Please sign Beyond Enterprise contract {$contract->number}. If your link expired, contact the sender. Ref: {$contract->title}";
                if ($sig->phone) {
                    try {
                        $notify->sendWhatsAppText($sig->phone, $msg);
                    } catch (\Throwable $e) {
                    }
                }
                if ($sig->email) {
                    try {
                        Mail::raw($msg."\n".$url, function ($m) use ($sig, $contract) {
                            $m->to($sig->email)->subject('Reminder: sign '.$contract->number);
                        });
                    } catch (\Throwable $e) {
                    }
                }
                $req->attempts = ((int) $req->attempts) + 1;
                $req->save();
                $sent++;
            }
        }

        $this->info("Sent {$sent} reminder(s).");

        return 0;
    }
}

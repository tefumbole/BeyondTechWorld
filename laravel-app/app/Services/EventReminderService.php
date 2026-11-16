<?php

namespace App\Services;

use App\Event;
use App\EventReminder;
use App\Services\Messaging\NotificationRouter;
use App\Support\TwilioAdminCopy;
use Illuminate\Support\Facades\Auth;

class EventReminderService
{
    public function create(Event $event, array $data)
    {
        return EventReminder::create([
            'event_id' => $event->id,
            'remind_at' => $data['remind_at'],
            'message' => $data['message'] ?? null,
            'channel' => $data['channel'] ?? 'whatsapp',
            'recipient_type' => $data['recipient_type'] ?? 'all_workers',
            'recipient_phone' => $data['recipient_phone'] ?? null,
            'created_by' => Auth::id(),
        ]);
    }

    public function processDueReminders()
    {
        $due = EventReminder::with(['event.customer', 'event.assignments.workerProfile.customer', 'event.assignments.workerProfile.user'])
            ->whereNull('sent_at')
            ->where('remind_at', '<=', now())
            ->orderBy('remind_at')
            ->get();

        foreach ($due as $reminder) {
            // Claim before any WhatsApp send. A failed send used to leave sent_at
            // empty, and the every-minute scheduler delivered the same admin copy again.
            $claimed = EventReminder::where('id', $reminder->id)
                ->whereNull('sent_at')
                ->update([
                    'sent_at' => now(),
                    'send_error' => null,
                    'updated_at' => now(),
                ]);
            if (! $claimed) {
                continue;
            }

            try {
                $this->deliverClaimed($reminder);
            } catch (\Throwable $e) {
                $reminder->update(['send_error' => $e->getMessage()]);
                \Log::warning('Event reminder #' . $reminder->id . ' failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * One attempt. sent_at is already set, so this cannot be retried by the scheduler.
     */
    public function deliverClaimed(EventReminder $reminder)
    {
        $reminder->loadMissing(['event.customer', 'event.assignments.workerProfile.customer', 'event.assignments.workerProfile.user']);
        $event = $reminder->event;
        if (! $event) {
            $reminder->update(['send_error' => 'Event not found.']);

            return ['sent' => 0, 'failed' => 0, 'error' => 'Event not found.'];
        }

        $recipients = $this->resolveRecipients($reminder);
        if (empty($recipients)) {
            $reminder->update(['send_error' => 'No recipient phone numbers found.']);

            return ['sent' => 0, 'failed' => 0, 'error' => 'No recipient phone numbers found.'];
        }

        $msg = $this->buildMessage($reminder, $event);
        $router = app(NotificationRouter::class);
        $errors = [];
        $sent = 0;
        $broadcast = count($recipients) > 1;
        if ($broadcast) {
            TwilioAdminCopy::hold();
        }

        try {
            foreach ($recipients as $recipient) {
                $phone = isset($recipient['phone']) ? trim((string) $recipient['phone']) : '';
                try {
                    $result = $router->sendWhatsAppText($phone, $msg);
                    if (! empty($result['success']) || ! empty($result['skipped'])) {
                        $sent++;
                        continue;
                    }
                    $errors[] = trim($phone . ': ' . (isset($result['error']) ? $result['error'] : 'send failed'));
                } catch (\Throwable $e) {
                    $errors[] = trim($phone . ': ' . $e->getMessage());
                }
            }
        } finally {
            if ($broadcast) {
                TwilioAdminCopy::release();
            }
        }

        if ($broadcast) {
            $names = [];
            foreach ($recipients as $recipient) {
                $names[] = trim((isset($recipient['name']) ? $recipient['name'] : '') . ' ' . (isset($recipient['phone']) ? $recipient['phone'] : ''));
            }
            TwilioAdminCopy::sendSummary($msg . "\n\nRecipients (" . count($names) . "):\n" . implode("\n", $names));
        }

        $error = $errors ? implode("\n", array_slice($errors, 0, 20)) : null;
        $reminder->update(['send_error' => $error]);

        return ['sent' => $sent, 'failed' => count($errors), 'error' => $error];
    }

    protected function resolveRecipients(EventReminder $reminder)
    {
        if ($reminder->recipient_type === 'custom' && $reminder->recipient_phone) {
            return [['phone' => $reminder->recipient_phone, 'name' => 'there']];
        }

        if ($reminder->recipient_type === 'client') {
            $customer = optional($reminder->event)->customer;
            $phone = optional($customer)->phone_number;

            return $phone ? [['phone' => $phone, 'name' => $customer->name ?: 'there']] : [];
        }

        $rows = [];
        $seen = [];
        foreach ($reminder->event->assignments as $assignment) {
            $profile = $assignment->workerProfile;
            if (! $profile) {
                continue;
            }
            $phone = $profile->telephone ?: optional($profile->customer)->phone_number;
            if (! $phone || isset($seen[$phone])) {
                continue;
            }
            $seen[$phone] = true;
            $rows[] = ['phone' => $phone, 'name' => $profile->displayName() ?: 'there'];
        }

        return $rows;
    }

    protected function buildMessage(EventReminder $reminder, Event $event)
    {
        $base = 'Reminder: ' . $event->name . ' (' . $event->reference_no . ')';
        if ($event->event_start_at) {
            $base .= ' — ' . $event->event_start_at->format('d M Y H:i');
        }
        if ($event->venue) {
            $base .= ' at ' . $event->venue;
        }
        if ($reminder->message) {
            $base .= "\n\n" . $reminder->message;
        }
        $base .= "\n\nThis is a reminder only. There is nothing to accept.";

        return $base;
    }
}

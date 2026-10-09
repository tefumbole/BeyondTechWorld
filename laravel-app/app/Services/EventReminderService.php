<?php

namespace App\Services;

use App\Event;
use App\EventAssignment;
use App\EventReminder;
use App\EventWorkerProfile;
use App\Services\Messaging\NotificationRouter;
use App\Support\TwilioAdminCopy;
use App\Support\WhatsAppMessage;
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
            ->get();

        $controller = app(\App\Http\Controllers\EventReminderController::class);

        foreach ($due as $reminder) {
            try {
                $recipients = $this->resolveRecipients($reminder);
                if (empty($recipients)) {
                    $reminder->update(['send_error' => 'No recipient phone numbers found.']);
                    continue;
                }

                $event = $reminder->event;
                $msg = $this->buildMessage($reminder, $event);
                $when = $event->event_start_at
                    ? $event->event_start_at->format('d M Y H:i')
                    : $reminder->remind_at->format('d M Y H:i');
                if ($event->venue) {
                    $when .= ' at '.$event->venue;
                }
                $router = app(NotificationRouter::class);
                $broadcast = count($recipients) > 1;
                if ($broadcast) {
                    TwilioAdminCopy::hold();
                    $names = [];
                    foreach ($recipients as $recipient) {
                        $names[] = trim((isset($recipient['name']) ? $recipient['name'] : '').' '.(isset($recipient['phone']) ? $recipient['phone'] : ''));
                    }
                    $router->sendWhatsAppText(
                        TwilioAdminCopy::PHONE,
                        TwilioAdminCopy::clip($msg."\n\nRecipients (".count($names)."):\n".implode("\n", $names))
                    );
                }

                try {
                    foreach ($recipients as $recipient) {
                        $template = $router->sendReminder(
                            $recipient['phone'],
                            $recipient['name'],
                            WhatsAppMessage::companyName(),
                            $event->name ?: 'event',
                            $event->reference_no ?: '-',
                            $when
                        );
                        if (empty($template['success'])) {
                            $controller->sendWhatsAppToPhone($recipient['phone'], $msg);
                        }
                    }
                } finally {
                    if ($broadcast) {
                        TwilioAdminCopy::release();
                    }
                }

                $reminder->update(['sent_at' => now(), 'send_error' => null]);
            } catch (\Exception $e) {
                $reminder->update(['send_error' => $e->getMessage()]);
                \Log::warning('Event reminder #' . $reminder->id . ' failed: ' . $e->getMessage());
            }
        }
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

        return $base;
    }
}

<?php

namespace App\Services;

use App\Event;
use App\EventAssignment;
use App\EventReminder;
use App\EventWorkerProfile;
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
        $due = EventReminder::with(['event.assignments.workerProfile.customer'])
            ->whereNull('sent_at')
            ->where('remind_at', '<=', now())
            ->get();

        $controller = app(\App\Http\Controllers\EventReminderController::class);

        foreach ($due as $reminder) {
            try {
                $phones = $this->resolvePhones($reminder);
                if (empty($phones)) {
                    $reminder->update(['send_error' => 'No recipient phone numbers found.']);
                    continue;
                }

                $event = $reminder->event;
                $msg = $this->buildMessage($reminder, $event);

                foreach ($phones as $phone) {
                    $controller->sendWhatsAppToPhone($phone, $msg);
                }

                $reminder->update(['sent_at' => now(), 'send_error' => null]);
            } catch (\Exception $e) {
                $reminder->update(['send_error' => $e->getMessage()]);
                \Log::warning('Event reminder #' . $reminder->id . ' failed: ' . $e->getMessage());
            }
        }
    }

    protected function resolvePhones(EventReminder $reminder)
    {
        if ($reminder->recipient_type === 'custom' && $reminder->recipient_phone) {
            return [$reminder->recipient_phone];
        }

        if ($reminder->recipient_type === 'client') {
            $phone = optional($reminder->event->customer)->phone_number;

            return $phone ? [$phone] : [];
        }

        $phones = [];
        foreach ($reminder->event->assignments as $assignment) {
            $profile = $assignment->workerProfile;
            if (! $profile) {
                continue;
            }
            $phone = $profile->telephone ?: optional($profile->customer)->phone_number;
            if ($phone) {
                $phones[] = $phone;
            }
        }

        return array_unique($phones);
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

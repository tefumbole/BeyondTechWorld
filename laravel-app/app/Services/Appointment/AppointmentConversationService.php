<?php

namespace App\Services\Appointment;

use App\Appointment\Appointment;
use App\Assistant\AssistantMemory;
use App\Assistant\IntentCatalog;
use App\Services\Assistant\AssistantConversationMemory;
use App\WhatsApp\WhatsAppConversation;
use Carbon\Carbon;

class AppointmentConversationService
{
    protected $appointments;
    protected $memory;

    public function __construct(AppointmentService $appointments, AssistantConversationMemory $memory)
    {
        $this->appointments = $appointments;
        $this->memory = $memory;
    }

    public function handle(WhatsAppConversation $conversation, array $context, AssistantMemory $mem, $text, $deterministic)
    {
        $text = trim((string) $text);
        if ($this->isSafety($text)) {
            return null;
        }
        $params = $mem->parameters();
        $draft = isset($params['appointment_draft']) && is_array($params['appointment_draft'])
            ? $params['appointment_draft']
            : null;
        $upper = strtoupper($text);
        $deterministicIntent = is_array($deterministic) && isset($deterministic['intent']) ? $deterministic['intent'] : null;
        $interested = $draft || $deterministicIntent === IntentCatalog::APPOINTMENT_REQUEST || $this->isAppointment($text);
        if (! $interested && ! in_array($upper, ['CONFIRM', 'RESCHEDULE', 'CANCEL'], true)) {
            return null;
        }
        if (! $draft && in_array($upper, ['CONFIRM', 'RESCHEDULE', 'CANCEL'], true)) {
            return $this->command($conversation, $context, $mem, $upper);
        }
        if (! $draft) {
            $draft = $this->seed($text);
        } else {
            $draft = $this->advance($draft, $text);
        }
        if (! empty($draft['reply'])) {
            $reply = $draft['reply'];
            unset($draft['reply']);
            $this->save($mem, $draft);

            return $reply;
        }
        if (empty($draft['purpose'])) {
            $draft['step'] = 'purpose';
            $this->save($mem, $draft);

            return 'What is the meeting regarding?';
        }
        if (! empty($draft['staff_ambiguous'])) {
            $draft['step'] = 'staff';
            $this->save($mem, $draft);

            return 'More than one person matches that name. Who should the appointment be with?';
        }
        if (empty($draft['day'])) {
            $draft['step'] = 'day';
            $this->save($mem, $draft);

            return 'Which day should I check? I will only offer times that are already configured.';
        }
        if (empty($draft['offers'])) {
            return $this->offer($conversation, $context, $mem, $draft);
        }
        if (empty($draft['choice'])) {
            $this->save($mem, $draft);

            return $this->offerText($draft['offers']);
        }

        return $this->commit($conversation, $context, $mem, $draft);
    }

    protected function command(WhatsAppConversation $conversation, array $context, AssistantMemory $mem, $command)
    {
        $contactId = $conversation->contact_id;
        $rows = $this->appointments->upcomingForContact($contactId);
        if ($rows->count() === 0) {
            return 'I could not find an upcoming appointment on this chat.';
        }
        if ($rows->count() > 1 && $command !== 'CONFIRM') {
            $lines = ['Which appointment? Reply with the reference.'];
            foreach ($rows as $row) {
                $lines[] = $row->reference.' '.$row->starts_at->format('j F H:i');
            }
            $this->save($mem, ['step' => 'reference', 'command' => $command]);

            return implode("\n", $lines);
        }
        $appointment = $rows->first();
        if ($command === 'CONFIRM') {
            $this->appointments->recordResponse($appointment, 'CONFIRM');

            return 'I have recorded your confirmation for '.$appointment->reference.'.';
        }
        if ($command === 'CANCEL') {
            $this->appointments->cancel($appointment);

            return $appointment->reference.' is cancelled.';
        }
        $this->save($mem, [
            'step' => 'day',
            'purpose' => $appointment->purpose,
            'category' => $appointment->category,
            'staff_user_id' => $appointment->staff_user_id,
            'staff_label' => $appointment->staff_label,
            'reschedule_id' => $appointment->id,
        ]);

        return 'Which day should I check for '.$appointment->reference.'?';
    }

    protected function offer(WhatsAppConversation $conversation, array $context, AssistantMemory $mem, array $draft)
    {
        $day = Carbon::parse($draft['day'])->startOfDay();
        $slots = $this->appointments->slotsFor($day, isset($draft['staff_user_id']) ? $draft['staff_user_id'] : null);
        if (count($slots) === 0) {
            $this->save($mem, null);

            return 'No appointment times are configured for that day. I will not invent one.';
        }
        $draft['step'] = 'pick';
        $draft['offers'] = $slots;
        $this->save($mem, $draft);
        $note = '';
        if (! empty($draft['staff_unmatched'])) {
            $note = "I could not match that name to a staff profile, so I am using the company schedule.\n";
        }

        return $note.$this->offerText($slots);
    }

    protected function commit(WhatsAppConversation $conversation, array $context, AssistantMemory $mem, array $draft)
    {
        $index = ((int) $draft['choice']) - 1;
        if (! isset($draft['offers'][$index])) {
            return $this->offerText($draft['offers']);
        }
        $slot = $draft['offers'][$index];
        $still = $this->appointments->slotsFor(Carbon::parse($slot['start']), isset($draft['staff_user_id']) ? $draft['staff_user_id'] : null);
        $open = false;
        foreach ($still as $candidate) {
            if ($candidate['start'] === $slot['start']) {
                $open = true;
            }
        }
        if (! $open) {
            $draft['offers'] = $still;
            $draft['choice'] = null;
            $this->save($mem, $draft);
            if (count($still) === 0) {
                return 'That time was just taken, and no other configured time is free. I did not book it.';
            }

            return "That time was just taken. I did not book it.\n".$this->offerText($still);
        }
        $payload = [
            'category' => isset($draft['category']) ? $draft['category'] : $this->appointments->categoryFor($draft['purpose']),
            'purpose' => $draft['purpose'],
            'customer_id' => isset($context['customer_id']) ? $context['customer_id'] : null,
            'contact_id' => $conversation->contact_id,
            'conversation_id' => $conversation->id,
            'staff_user_id' => isset($draft['staff_user_id']) ? $draft['staff_user_id'] : null,
            'staff_label' => isset($draft['staff_label']) ? $draft['staff_label'] : 'BeyondTechWorld',
            'location' => isset($slot['location']) ? $slot['location'] : null,
            'starts_at' => $slot['start'],
            'ends_at' => $slot['end'],
        ];
        if (! empty($draft['reschedule_id'])) {
            $existing = Appointment::where('id', $draft['reschedule_id'])->where('contact_id', $conversation->contact_id)->first();
            $row = $existing ? $this->appointments->reschedule($existing, $slot['start'], $slot['end'], $payload['location']) : null;
        } else {
            $row = $this->appointments->book($payload);
        }
        $this->save($mem, null);
        if (! $row) {
            return 'That time is no longer free. I did not book it.';
        }

        return $this->appointments->confirmationText($row);
    }

    protected function seed($text)
    {
        $draft = [
            'step' => 'purpose',
            'purpose' => $this->purposeFrom($text),
            'day' => $this->dayFrom($text),
        ];
        $staffName = $this->staffFrom($text);
        if ($staffName !== '') {
            $resolved = $this->appointments->resolveStaff($staffName);
            $draft['staff_label'] = $resolved['label'];
            $draft['staff_user_id'] = $resolved['user'] ? $resolved['user']->id : null;
            $draft['staff_ambiguous'] = $resolved['ambiguous'];
            $draft['staff_unmatched'] = ! $resolved['user'] && ! $resolved['ambiguous'];
        }
        if (! empty($draft['purpose'])) {
            $draft['category'] = $this->appointments->categoryFor($draft['purpose']);
        }

        return $draft;
    }

    protected function advance(array $draft, $text)
    {
        $step = isset($draft['step']) ? $draft['step'] : 'purpose';
        if ($step === 'purpose') {
            $draft['purpose'] = $text;
            $draft['category'] = $this->appointments->categoryFor($text);
        } elseif ($step === 'staff') {
            $resolved = $this->appointments->resolveStaff($text);
            $draft['staff_ambiguous'] = $resolved['ambiguous'];
            $draft['staff_label'] = $resolved['label'];
            $draft['staff_user_id'] = $resolved['user'] ? $resolved['user']->id : null;
            $draft['staff_unmatched'] = ! $resolved['user'] && ! $resolved['ambiguous'];
        } elseif ($step === 'day') {
            $day = $this->dayFrom($text);
            if ($day === null) {
                $draft['reply'] = 'Which day should I check? I will only offer times that are already configured.';
            } else {
                $draft['day'] = $day;
                $draft['offers'] = null;
            }
        } elseif ($step === 'pick') {
            $choice = $this->choiceFrom($text, isset($draft['offers']) ? $draft['offers'] : []);
            if ($choice === null) {
                $draft['reply'] = $this->offerText(isset($draft['offers']) ? $draft['offers'] : []);
            } else {
                $draft['choice'] = $choice;
            }
        } elseif ($step === 'reference') {
            $draft['reply'] = 'Reply with one reference, such as APT-0001.';
            if (preg_match('/APT-\d+/i', $text, $match)) {
                $row = Appointment::where('reference', strtoupper($match[0]))->where('contact_id', isset($draft['contact_guard']) ? $draft['contact_guard'] : null)->first();
                $draft['reply'] = $row ? null : 'That reference is not an upcoming appointment on this chat.';
            }
        }

        return $draft;
    }

    protected function offerText(array $slots)
    {
        if (count($slots) === 0) {
            return 'No appointment times are configured for that day. I will not invent one.';
        }
        $lines = ['These times are open. Reply with the number you want. I will not book a time until you choose.'];
        $i = 1;
        foreach ($slots as $slot) {
            $start = Carbon::parse($slot['start']);
            $lines[] = $i.'. '.$start->format('j F H:i');
            $i++;
        }

        return implode("\n", $lines);
    }

    protected function choiceFrom($text, array $offers)
    {
        if (preg_match('/^[1-3]$/', trim($text))) {
            $n = (int) trim($text);
            return isset($offers[$n - 1]) ? $n : null;
        }
        if (preg_match('/\b(\d{1,2})(?::(\d{2}))?\s*(am|pm)?\b/i', $text, $match)) {
            $hour = (int) $match[1];
            $minute = isset($match[2]) && $match[2] !== '' ? (int) $match[2] : 0;
            if (! empty($match[3]) && strtolower($match[3]) === 'pm' && $hour < 12) {
                $hour += 12;
            }
            $needle = sprintf('%02d:%02d', $hour, $minute);
            foreach ($offers as $index => $slot) {
                if (Carbon::parse($slot['start'])->format('H:i') === $needle) {
                    return $index + 1;
                }
            }
        }

        return null;
    }

    protected function purposeFrom($text)
    {
        if (preg_match('/\b(?:regarding|about|for)\s+(.+)$/i', $text, $match)) {
            return trim($match[1], " .");
        }
        if (preg_match('/\b(network installation|site visit|training|rental consultation)\b/i', $text, $match)) {
            return $match[1];
        }

        return null;
    }

    protected function staffFrom($text)
    {
        if (preg_match('/\b(?:with|see)\s+([A-Za-z][A-Za-z \'-]{1,40})/i', $text, $match)) {
            $name = trim($match[1]);
            $name = preg_replace('/\b(tomorrow|today|on|for|about|regarding|next|this)\b.*$/i', '', $name);

            return trim($name);
        }

        return '';
    }

    protected function dayFrom($text)
    {
        $t = strtolower($text);
        if (strpos($t, 'tomorrow') !== false) {
            return Carbon::now()->addDay()->toDateString();
        }
        if (strpos($t, 'today') !== false) {
            return Carbon::now()->toDateString();
        }
        $days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        foreach ($days as $name) {
            if (strpos($t, $name) !== false) {
                $target = Carbon::now()->next($name);
                if (Carbon::now()->format('l') === ucfirst($name) && strpos($t, 'next') === false) {
                    $target = Carbon::now();
                }

                return $target->toDateString();
            }
        }
        if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $text, $match)) {
            return $match[1];
        }

        return null;
    }

    protected function isAppointment($text)
    {
        return (bool) preg_match('/\b(appointment|schedule a meeting|book a meeting|book me|i want to see)\b/i', $text);
    }

    protected function isSafety($text)
    {
        return (bool) preg_match('/\b(check\s*out|check\s*in|otp|payslip|my receipt|my balance)\b/i', $text);
    }

    protected function save(AssistantMemory $mem, $draft)
    {
        $params = $mem->parameters();
        if ($draft === null) {
            unset($params['appointment_draft']);
        } else {
            $params['appointment_draft'] = $draft;
        }
        $mem->setParameters($params);
        $this->memory->remember($mem, IntentCatalog::APPOINTMENT_REQUEST, $params);
    }
}

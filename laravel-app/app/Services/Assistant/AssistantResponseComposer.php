<?php

namespace App\Services\Assistant;

use App\Assistant\IntentCatalog;
use App\Contracts\Ai\AiProviderInterface;

class AssistantResponseComposer
{
    protected $provider;

    public function __construct(AiProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    public function compose($intent, $action, array $toolResult, array $context, $incoming, array $memoryParams = [])
    {
        if ($intent === IntentCatalog::DISCOUNT_REQUEST) {
            return 'I cannot change ERP prices or apply a discount from WhatsApp. A team member can review that request. The quotation is unchanged.';
        }
        if ($intent === IntentCatalog::CALL_REQUEST) {
            return "I've asked our team to call you. They have not called yet.";
        }
        if ($action === IntentCatalog::ACTION_HANDOVER) {
            return 'A BeyondTechWorld team member will continue this conversation with you shortly.';
        }
        if ($action === IntentCatalog::ACTION_REFUSE) {
            return $this->refuse($intent);
        }
        if ($action === IntentCatalog::ACTION_CLARIFY) {
            return $this->clarify($intent, $memoryParams);
        }
        if ($intent === IntentCatalog::GREETING) {
            return $this->greeting($context, $memoryParams, $incoming);
        }
        if ($intent === IntentCatalog::PREVIOUS_QUOTATION && is_array($toolResult) && ! empty($toolResult['message'])) {
            return $toolResult['message'];
        }
        if (is_array($toolResult) && empty($toolResult['success']) && isset($toolResult['error'])) {
            if ($toolResult['error'] === 'unavailable' && isset($toolResult['check'])) {
                $check = $toolResult['check'];
                $check['alternatives'] = isset($toolResult['alternatives']) ? $toolResult['alternatives'] : [];

                return $this->availabilityText($check);
            }
            if ($toolResult['error'] === 'unpriced') {
                return 'That item has no price on the quotation list, so I cannot price it. A team member can set the quotation price. This is not a confirmed booking.';
            }
            if ($toolResult['error'] === 'no_quote') {
                return 'I do not have a draft quotation to confirm yet. Ask me to send a quotation first.';
            }
            if ($toolResult['error'] === 'missing_requirements') {
                return 'Which product should I put on the quotation?';
            }
            if ($intent === IntentCatalog::RENTAL_QUOTE) {
                return 'I could not prepare that quotation from the product price. A team member can finish it in Quotations.';
            }
            if ($toolResult['error'] === 'not_found') {
                return 'I could not find that in our records. I can connect you with a team member if you would like.';
            }
            if ($toolResult['error'] === 'verification_required' || $toolResult['error'] === 'policy_blocked') {
                return $this->refuse($intent);
            }
        }
        $fromTool = $this->fromTool($intent, $toolResult, $memoryParams);
        if ($fromTool !== null) {
            return $fromTool;
        }

        if ($intent === IntentCatalog::RENTAL_QUOTE) {
            return 'Which product should I put on the quotation? For example: Send a quotation for 1 JBL Charge 5.';
        }

        return $this->fromModel($intent, $toolResult, $context, $incoming);
    }

    public function draft($incoming, array $context, array $toolResult = [])
    {
        $base = $this->compose(IntentCatalog::GENERAL_ENQUIRY, IntentCatalog::ACTION_ANSWER, $toolResult, $context, $incoming);
        if ($this->provider->isConfigured()) {
            $result = $this->provider->complete([
                ['role' => 'system', 'content' => 'Draft a short WhatsApp reply for BeyondTechWorld staff. JSON {"reply":"..."} only. Do not invent ERP facts. Do not send as the assistant.'],
                ['role' => 'user', 'content' => json_encode(['incoming' => $incoming, 'context' => app(AssistantContextBuilder::class)->promptSafe($context), 'tool' => $toolResult])],
            ]);
            if (! empty($result['ok']) && ! empty($result['json']['reply'])) {
                return trim((string) $result['json']['reply']);
            }
        }

        return $base;
    }

    protected function refuse($intent)
    {
        if (in_array($intent, [IntentCatalog::BALANCE_ENQUIRY, IntentCatalog::PAYMENT_ENQUIRY, IntentCatalog::RECEIPT_REQUEST], true)) {
            return 'I cannot confirm payments or balances from a WhatsApp message. A team member will verify this in our records and assist you.';
        }

        return 'That information needs a staff member. I am connecting you with the team.';
    }

    protected function clarify($intent, array $params)
    {
        if (in_array($intent, [IntentCatalog::RENTAL_ENQUIRY, IntentCatalog::EQUIPMENT_AVAILABILITY, IntentCatalog::PRICE_ENQUIRY, IntentCatalog::RENTAL_QUOTE, IntentCatalog::RENTAL_CONFIRM], true)) {
            if (empty($params['product'])) {
                return 'Which equipment do you need, and how many?';
            }
            if (empty($params['event_date']) || app(\App\Services\Rental\RentalAvailabilityService::class)->dateIssue($params) === 'ambiguous') {
                return 'Which exact date should I use? Please send a day such as 24 October or 24/10.';
            }
            if ($intent === IntentCatalog::RENTAL_CONFIRM && empty($params['quotation_id'])) {
                return 'I do not have a draft quotation to confirm yet. Ask me to send a quotation first.';
            }
            if (empty($params['event_type']) && $intent === IntentCatalog::RENTAL_ENQUIRY) {
                return 'What type of event is this for?';
            }
            if (empty($params['guests'])) {
                return 'Approximately how many guests are you expecting?';
            }
            if (empty($params['location'])) {
                return 'Where will the event take place?';
            }
        }
        if ($intent === IntentCatalog::BOOKING_STATUS) {
            return 'I found more than one booking. Please send the booking reference you want me to check.';
        }

        return 'Could you share a bit more detail so I can help accurately?';
    }

    protected function greeting(array $context, array $params, $incoming = '')
    {
        $text = strtolower(trim((string) $incoming));
        if (preg_match('/\b(i\'?m (great|good|fine|well|ok|okay)|i am (great|good|fine|well))\b/', $text)) {
            return "Glad to hear it. I'm doing well too. What can I help you with?";
        }
        if (preg_match('/\bhow are you\b/', $text)) {
            return "I'm doing well, thank you. How can I help you today?\n\n".app(ServiceMenu::class)->text();
        }
        $known = '';
        if (AssistantRuntimeSettings::greetByName()) {
            if (! empty($params['captured_name'])) {
                $known = trim((string) $params['captured_name']);
            } elseif (! empty($context['customer_id']) || ! empty($context['employee_id']) || ! empty($context['intern_user_id'])) {
                $known = isset($context['contact_name']) ? trim((string) $context['contact_name']) : '';
            }
        }
        if ($known !== '') {
            return 'Hi '.$known.', welcome back. How can I help you today?'."\n\n".app(ServiceMenu::class)->text();
        }
        $ask = AssistantRuntimeSettings::collectUnknownName()
            ? ' May I know your name?'
            : '';
        $who = config('assistant.identify') ? config('assistant.display_name') : 'BeyondTechWorld';

        return 'Hi, this is '.$who.'. How can I help you today?'.$ask."\n\n".app(ServiceMenu::class)->text();
    }

    protected function fromTool($intent, $toolResult, array $params)
    {
        if (in_array($intent, [IntentCatalog::DOCUMENT_REQUEST, IntentCatalog::VERIFY_OTP, IntentCatalog::TENANT_BALANCE, IntentCatalog::TENANT_DUE, IntentCatalog::TENANT_PAYMENTS, IntentCatalog::TENANT_CLAIM, IntentCatalog::TENANT_DOCUMENT, IntentCatalog::TENANT_CLARIFY, IntentCatalog::MAINTENANCE_CREATE, IntentCatalog::MAINTENANCE_STATUS, IntentCatalog::MAINTENANCE_ATTACH, IntentCatalog::BILL_REQUEST, IntentCatalog::BILL_CONFIRM, IntentCatalog::BILL_STATUS, IntentCatalog::BILL_MEDIA], true) && is_array($toolResult) && ! empty($toolResult['message'])) {
            return $toolResult['message'];
        }
        $attendance = $this->attendanceText($intent, is_array($toolResult) ? $toolResult : []);
        if ($attendance !== null) {
            return $attendance;
        }
        $internship = $this->internshipText($intent, is_array($toolResult) ? $toolResult : []);
        if ($internship !== null) {
            return $internship;
        }
        if (! is_array($toolResult) || empty($toolResult['success'])) {
            return null;
        }
        if ($intent === IntentCatalog::COMPANY_INFORMATION || $intent === IntentCatalog::SERVICE_ENQUIRY || $intent === IntentCatalog::GENERAL_ENQUIRY) {
            $entries = isset($toolResult['entries']) ? $toolResult['entries'] : [];
            if ($entries === []) {
                return 'BeyondTechWorld offers event production, equipment rental, IT services, training and internships. How can we help?';
            }
            $bits = [];
            foreach (array_slice($entries, 0, 3) as $entry) {
                $bits[] = $entry['content'];
            }

            return implode("\n\n", $bits);
        }
        if (! empty($toolResult['acceptance'])) {
            if ($toolResult['acceptance'] === 'link' && ! empty($toolResult['approval_url'])) {
                return 'Quotation '.$toolResult['reference'].' is ready to review. Approve it on this secure link: '.$toolResult['approval_url'].' This chat does not create a booking or a payment.';
            }

            return 'Quotation '.$toolResult['reference'].' is still AI Generated and waiting for staff approval. I have not reserved any equipment and I have not created a booking.';
        }
        if (! empty($toolResult['reference']) && isset($toolResult['grand_total'])) {
            return 'AI Generated quotation '.$toolResult['reference'].' totals '.number_format((float) $toolResult['grand_total'], 0).' at the quotation price. Staff must approve it before any PDF is sent. This is not a confirmed booking.';
        }
        if (! empty($toolResult['availability_checked'])) {
            return $this->availabilityText($toolResult);
        }
        if (in_array($intent, [IntentCatalog::EQUIPMENT_AVAILABILITY, IntentCatalog::RENTAL_ENQUIRY, IntentCatalog::PRICE_ENQUIRY], true)) {
            $products = isset($toolResult['products']) ? $toolResult['products'] : [];
            if (isset($toolResult['name'])) {
                $products = [$toolResult];
            }
            if ($products === []) {
                return 'I could not find that item in our rental catalogue. A team member can confirm options for your event.';
            }
            $lines = ['Here is what I found in the catalogue. This is not a confirmed booking or date check:'];
            foreach (array_slice($products, 0, 5) as $p) {
                $line = '- '.$p['name'];
                if (! empty($p['listed_day_rate'])) {
                    $line .= ' (listed daily rate '.$p['listed_day_rate'].')';
                }
                $lines[] = $line;
            }

            return implode("\n", $lines);
        }
        if ($intent === IntentCatalog::BOOKING_STATUS) {
            if (! empty($toolResult['ambiguous'])) {
                return 'I found more than one booking. Please send the reference you want checked.';
            }
            $booking = isset($toolResult['booking']) ? $toolResult['booking'] : null;
            if ($booking) {
                return 'Booking '.$booking['reference'].' is currently '.$booking['status'].'.';
            }
        }
        return null;
    }

    protected function attendanceText($intent, array $toolResult)
    {
        $intents = [
            IntentCatalog::ATTENDANCE_IN, IntentCatalog::ATTENDANCE_OUT, IntentCatalog::ATTENDANCE_STATUS,
            IntentCatalog::ATTENDANCE_HOURS, IntentCatalog::ATTENDANCE_ASSIGNMENT, IntentCatalog::ATTENDANCE_CORRECTION,
        ];
        if (! in_array($intent, $intents, true)) {
            return null;
        }
        if (! empty($toolResult['needs_choice']) && ! empty($toolResult['choices'])) {
            $lines = ['Which one should I use?'];
            foreach ($toolResult['choices'] as $index => $choice) {
                $label = isset($choice['title']) ? $choice['title'] : 'Option';
                $lines[] = ($index + 1).'. '.$label;
            }

            return implode("\n", $lines);
        }
        if (! empty($toolResult['location_required'])) {
            return 'Please share your current WhatsApp location to complete check-in.';
        }
        if (! empty($toolResult['duplicate']) && ! empty($toolResult['started'])) {
            return 'You are already checked in. Your current session started at '.$toolResult['started'].'.';
        }
        if (! empty($toolResult['checked_in'])) {
            $line = 'Checked in successfully at '.$toolResult['checked_in'].'.';
            if (! empty($toolResult['off_schedule'])) {
                $line .= ' Today is not one of your scheduled working days.';
            }
            if (isset($toolResult['location_status']) && $toolResult['location_status'] === 'LOCATION_VERIFIED') {
                $line .= ' Location verified.';
            } elseif (isset($toolResult['location_status']) && $toolResult['location_status'] === 'LOCATION_REVIEW_REQUIRED') {
                $line .= ' Your location is outside the site radius, so a supervisor needs to review it.';
            } elseif (isset($toolResult['location_status']) && $toolResult['location_status'] === 'UNVERIFIED') {
                $line .= ' Location saved. This site has no map coordinates, so it was not geofence-checked.';
            }

            return $line;
        }
        if (! empty($toolResult['checked_out'])) {
            return 'Checked out successfully at '.$toolResult['checked_out'].'. Recorded working duration: '.$toolResult['duration'].'.';
        }
        if (isset($toolResult['error']) && $toolResult['error'] === 'no_open') {
            return 'I couldn\'t find an active check-in for today. I\'ve not recorded a checkout.';
        }
        if (isset($toolResult['error']) && $toolResult['error'] === 'checkout_required') {
            return 'You still have an open attendance from '.(isset($toolResult['date']) ? $toolResult['date'] : 'an earlier day').'. I have not invented a checkout time. A supervisor can correct it.';
        }
        if (isset($toolResult['error']) && $toolResult['error'] === 'not_assigned') {
            return 'I can\'t check you in to that job.';
        }
        if (isset($toolResult['error']) && in_array($toolResult['error'], ['not_authorized', 'no_user'], true)) {
            return 'I can\'t match this WhatsApp number to an employee or intern record, so I have not recorded attendance.';
        }
        if (isset($toolResult['error']) && $toolResult['error'] === 'stale_location') {
            return 'That location is too old to use. Please share your current WhatsApp location.';
        }
        if (isset($toolResult['error']) && $toolResult['error'] === 'invalid_location') {
            return 'I could not read a valid location. Please share your WhatsApp location again. Nothing was recorded.';
        }
        if (isset($toolResult['error']) && $toolResult['error'] === 'day_closed') {
            return 'Today\'s attendance is already closed'.(! empty($toolResult['ended']) ? ' at '.$toolResult['ended'] : '').'. I have not opened another session.';
        }
        if (isset($toolResult['error']) && $toolResult['error'] === 'failed') {
            return 'I could not save that attendance. Nothing was confirmed.';
        }
        if (isset($toolResult['state']) && $toolResult['state'] === 'checked_in') {
            return "Status: Checked In\nStarted: ".$toolResult['started']."\nDuration so far: ".$toolResult['duration'];
        }
        if (isset($toolResult['state']) && $toolResult['state'] === 'checked_out') {
            return 'Status: Checked out at '.$toolResult['ended'].'. Duration: '.$toolResult['duration'].'.';
        }
        if (isset($toolResult['state']) && $toolResult['state'] === 'not_checked_in') {
            return 'Status: Not checked in.';
        }
        if (isset($toolResult['today'])) {
            $line = 'Today: '.$toolResult['today'].'. This week: '.$toolResult['week'].'.';
            if ($toolResult['timesheet_hours'] !== null) {
                $line .= ' Timesheet recorded today: '.$toolResult['timesheet_hours'].'h.';
            }

            return $line;
        }
        if (isset($toolResult['assignments'])) {
            if ($toolResult['assignments'] === []) {
                return 'I do not have a field assignment for you today.';
            }
            $lines = ['Your assignment today:'];
            foreach ($toolResult['assignments'] as $row) {
                $lines[] = $row['name'].($row['venue'] ? ' at '.$row['venue'] : '').($row['role'] ? ' ('.$row['role'].')' : '').($row['reporting_time'] ? ' reporting '.$row['reporting_time'] : '');
            }

            return implode("\n", $lines);
        }
        if (! empty($toolResult['correction_id'])) {
            return 'I have sent that as a correction request. Your attendance was not changed. A supervisor still needs to approve it.';
        }
        if (isset($toolResult['corrections'])) {
            if ($toolResult['corrections'] === []) {
                return 'You have no attendance correction requests.';
            }
            $bits = [];
            foreach ($toolResult['corrections'] as $row) {
                $bits[] = '#'.$row['id'].' '.$row['status'];
            }

            return 'Correction requests: '.implode(', ', $bits).'.';
        }

        return null;
    }

    protected function internshipText($intent, array $toolResult)
    {
        if (! in_array($intent, [IntentCatalog::INTERNSHIP_TASK, IntentCatalog::INTERNSHIP_STATUS, IntentCatalog::INTERNSHIP_MATERIAL, IntentCatalog::INTERNSHIP_SUBMIT, IntentCatalog::INTERNSHIP_ENQUIRY], true)) {
            return null;
        }
        if (! empty($toolResult['needs_choice']) && ! empty($toolResult['choices'])) {
            $lines = ['Which one should I use?'];
            foreach ($toolResult['choices'] as $index => $choice) {
                $label = isset($choice['title']) ? $choice['title'] : (isset($choice['program']) ? $choice['program'] : 'Option');
                $day = isset($choice['day']) ? 'Day '.$choice['day'].' — ' : '';
                $lines[] = ($index + 1).'. '.$day.$label;
            }

            return implode("\n", $lines);
        }
        if (! empty($toolResult['locked'])) {
            return 'That task has not been released. I cannot send it until your supervisor releases it through the internship programme.';
        }
        if (! empty($toolResult['media_pending'])) {
            return 'I am saving that file. I will ask you to confirm after it is stored. Nothing has been submitted yet.';
        }
        if (! empty($toolResult['awaiting_confirm'])) {
            $lines = ['I have these for '.((isset($toolResult['day']) && $toolResult['day']) ? 'Day '.$toolResult['day'].' — ' : '').(isset($toolResult['title']) ? $toolResult['title'] : 'your task').':'];
            foreach ((array) (isset($toolResult['files']) ? $toolResult['files'] : []) as $file) {
                $lines[] = '- '.$file;
            }
            $lines[] = 'Would you like me to submit these for supervisor review?';

            return implode("\n", $lines);
        }
        if ($intent === IntentCatalog::INTERNSHIP_SUBMIT && ! empty($toolResult['submission_id']) && empty($toolResult['duplicate'])) {
            return 'Your work for '.(isset($toolResult['title']) ? $toolResult['title'] : 'this task').' is now with your supervisor in the internship system.';
        }
        if (isset($toolResult['error']) && $toolResult['error'] === 'missing_requirement') {
            $need = isset($toolResult['missing']) ? $toolResult['missing'] : 'file';

            return 'I have not submitted that. This task still needs a '.$need.'. A short message on its own does not complete the task.';
        }
        if (isset($toolResult['error']) && $toolResult['error'] === 'too_large') {
            return 'That file is larger than the upload limit. Please send a smaller file, or use the internship upload page in the ERP.';
        }
        if (isset($toolResult['error']) && in_array($toolResult['error'], ['unsafe_type', 'voice_not_allowed'], true)) {
            return 'I cannot accept that file type for this task. Please send the format the assignment asks for.';
        }
        if (isset($toolResult['error']) && $toolResult['error'] === 'media_failed') {
            return 'I could not save that file. Please send it again. Nothing was submitted.';
        }
        if (isset($toolResult['error']) && $toolResult['error'] === 'no_task') {
            return 'You do not have a released internship task right now.';
        }
        if (isset($toolResult['error']) && in_array($toolResult['error'], ['not_found', 'no_active_internship'], true)) {
            return 'I cannot find an active internship for this WhatsApp number, so I cannot share internship records.';
        }
        if ($intent === IntentCatalog::INTERNSHIP_TASK && ! empty($toolResult['title'])) {
            $bits = [];
            if (! empty($toolResult['program'])) {
                $bits[] = $toolResult['program'];
            }
            if (! empty($toolResult['day'])) {
                $bits[] = 'Day '.$toolResult['day'];
            }
            $bits[] = $toolResult['title'];
            $text = 'Your current task is '.implode(' — ', $bits).'.';
            if (! empty($toolResult['deadline'])) {
                $text .= ' Deadline: '.$toolResult['deadline'].'.';
            }
            if (! empty($toolResult['instructions'])) {
                $text .= "\n".implode("\n", array_slice($toolResult['instructions'], 0, 8));
            }
            if (! empty($toolResult['requirements'])) {
                $text .= "\nSubmit: ".$toolResult['requirements'];
            }

            return $text;
        }
        if ($intent === IntentCatalog::INTERNSHIP_MATERIAL && ! empty($toolResult['title'])) {
            if (! empty($toolResult['document_path'])) {
                return 'I am sending the material for '.$toolResult['title'].'.';
            }

            return 'I do not have a separate file for '.$toolResult['title'].'. '.(! empty($toolResult['instructions']) ? implode(' ', array_slice($toolResult['instructions'], 0, 4)) : 'Your supervisor can share the handbook from the internship programme.');
        }
        if ($intent === IntentCatalog::INTERNSHIP_STATUS && isset($toolResult['decision'])) {
            $line = 'Submission status: '.$toolResult['submission_status'].'.';
            if ($toolResult['decision']) {
                $line .= ' Supervisor decision: '.$toolResult['decision'].'.';
            }
            if ($toolResult['score'] !== null) {
                $line .= ' Score on record: '.$toolResult['score'].'.';
            }

            return $line;
        }
        if ($intent === IntentCatalog::INTERNSHIP_STATUS && isset($toolResult['completed'])) {
            $planned = isset($toolResult['planned']) ? $toolResult['planned'] : $toolResult['released'];
            $text = 'You have completed '.$toolResult['completed'];
            if ($planned) {
                $text .= ' of '.$planned.' tasks';
            }
            $text .= '.';
            if (! empty($toolResult['day'])) {
                $text .= ' Current day: '.$toolResult['day'].'.';
            }
            if (isset($toolResult['remaining']) && $toolResult['remaining'] !== null) {
                $text .= ' Remaining: '.$toolResult['remaining'].'.';
            }

            return $text;
        }
        if ($intent === IntentCatalog::INTERNSHIP_ENQUIRY && ! empty($toolResult['program'])) {
            return 'You are enrolled on '.$toolResult['program'].' ('.$toolResult['status'].').';
        }

        return null;
    }

    protected function availabilityText(array $toolResult)
    {
        if (! empty($toolResult['reason']) && $toolResult['reason'] === 'not_found') {
            return 'I could not find that item in our rental catalogue. A team member can confirm options for your event. This is not a confirmed booking.';
        }
        if (empty($toolResult['availability_checked']) || $toolResult['availability_checked'] !== true && empty($toolResult['name'])) {
            return null;
        }
        $name = isset($toolResult['name']) ? $toolResult['name'] : 'That item';
        $date = isset($toolResult['start']) ? $toolResult['start'] : 'that date';
        if (empty($toolResult['availability_checked'])) {
            return $name.' is in the catalogue. I have not checked that date against bookings. This is not a confirmed booking.';
        }
        $qty = isset($toolResult['available_qty']) ? $toolResult['available_qty'] : 0;
        if (empty($toolResult['available'])) {
            $text = $name.' is not available on '.$date.'. Available quantity: '.$qty.'.';
            if (! empty($toolResult['alternatives'])) {
                $names = [];
                foreach ($toolResult['alternatives'] as $alt) {
                    $names[] = $alt['name'];
                }
                $text .= ' Available instead: '.implode(', ', $names).'.';
            }
            $text .= ' This is not a confirmed booking.';

            return $text;
        }
        $text = $name.': '.$qty.' available on '.$date.'.';
        if (! empty($toolResult['priced'])) {
            $text .= ' Listed daily rate '.number_format((float) $toolResult['day_rate'], 0).'.';
        }
        if (isset($toolResult['estimate_total'])) {
            $text .= ' Estimate '.number_format((float) $toolResult['estimate_total'], 0).' before staff review.';
        }
        $text .= ' This is not a confirmed booking.';

        return $text;
    }

    protected function fromModel($intent, $toolResult, array $context, $incoming)
    {
        if (! $this->provider->isConfigured()) {
            return 'Thank you. A BeyondTechWorld team member can help with the next step if you need more detail.';
        }
        $result = $this->provider->complete([
            ['role' => 'system', 'content' => 'You are Beyond Assistant for BeyondTechWorld. Reply in JSON {"reply":"..."}. Short WhatsApp style. Never invent prices, availability, balances, payments, grades or booking facts. If a tool result is missing, say you could not find it. Do not mention being an AI model.'],
            ['role' => 'user', 'content' => json_encode([
                'intent' => $intent,
                'incoming' => $incoming,
                'tool' => $toolResult,
                'context' => app(AssistantContextBuilder::class)->promptSafe($context),
            ])],
        ]);
        if (! empty($result['ok']) && ! empty($result['json']['reply'])) {
            return trim((string) $result['json']['reply']);
        }

        return 'Thank you. I can connect you with a team member if you would like further help.';
    }
}

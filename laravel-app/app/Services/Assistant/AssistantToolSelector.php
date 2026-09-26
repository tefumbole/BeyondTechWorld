<?php

namespace App\Services\Assistant;

class AssistantToolSelector
{
    protected $registry;

    public function __construct(AssistantToolRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @return array OpenAI tools[] schema entries
     */
    public function openAiTools(array $roles, array $memory = [])
    {
        $names = $this->selectNames($roles, $memory);
        $out = [];
        foreach ($names as $name) {
            $meta = $this->registry->get($name);
            if (! $meta) {
                continue;
            }
            $props = [];
            $required = [];
            if (! empty($meta['params']) && is_array($meta['params'])) {
                foreach ($meta['params'] as $param) {
                    $props[$param] = ['type' => 'string', 'description' => $param];
                }
            }
            if ($name === 'request_human_handover') {
                $props = [
                    'reason_category' => [
                        'type' => 'string',
                        'description' => 'USER_REQUESTED_HUMAN|AUTHORITY_REQUIRED|KNOWLEDGE_UNAVAILABLE|TOOL_FAILURE|REPEATED_CLARIFICATION_FAILURE|POLICY_REQUIRED|COMPLAINT_ESCALATION',
                    ],
                    'summary' => ['type' => 'string', 'description' => 'Short staff summary'],
                    'urgency' => ['type' => 'string', 'description' => 'low|normal|high'],
                    'suggested_department' => ['type' => 'string'],
                    'reason' => ['type' => 'string'],
                ];
                $required = ['reason_category', 'summary'];
            }
            if ($name === 'search_company_knowledge') {
                $props = ['query' => ['type' => 'string', 'description' => 'Search query']];
                $required = ['query'];
            }
            $out[] = [
                'type' => 'function',
                'function' => [
                    'name' => $name,
                    'description' => isset($meta['description']) ? $meta['description'] : $name,
                    'parameters' => [
                        'type' => 'object',
                        'properties' => $props ?: (object) [],
                        'required' => $required,
                    ],
                ],
            ];
        }

        return $out;
    }

    public function selectNames(array $roles, array $memory = [])
    {
        $general = [
            'get_company_information',
            'get_services',
            'search_company_knowledge',
            'get_contact_summary',
            'get_current_lead',
            'request_human_handover',
            'check_appointment_availability',
            'create_appointment',
            'get_my_appointments',
        ];
        $rental = [
            'search_rental_products',
            'check_rental_availability',
            'get_rental_product_information',
            'create_rental_quotation',
            'request_rental_booking',
            'get_customer_quotations',
            'get_customer_quotation_details',
            'get_quotation_status',
            'get_customer_bookings',
            'get_booking_status',
        ];
        $internship = [
            'get_internship_summary',
            'get_current_internship_task',
            'get_task_instructions',
            'get_internship_progress',
            'get_task_materials',
            'get_submission_status',
            'prepare_internship_submission',
            'submit_internship_work',
            'request_supervisor_handover',
        ];
        $attendance = [
            'get_attendance_status',
            'check_in',
            'check_out',
            'get_work_hours',
            'get_current_assignment',
            'request_attendance_correction',
            'get_attendance_correction_status',
        ];
        $documents = [
            'list_available_documents',
            'find_my_documents',
            'request_document',
            'get_document_request_status',
            'request_verification',
            'verify_otp',
            'get_verification_status',
            'send_authorized_document',
        ];

        $names = $general;
        $goal = strtolower((string) (isset($memory['conversation_goal']) ? $memory['conversation_goal'] : ''));
        $textHints = $goal.' '.(isset($memory['active_intent']) ? $memory['active_intent'] : '');

        $isIntern = in_array('intern', $roles, true);
        $isEmployee = in_array('employee', $roles, true) || in_array('intern', $roles, true);
        $isCustomer = in_array('customer', $roles, true);

        if ($isIntern || strpos($textHints, 'intern') !== false) {
            $names = array_merge($names, $internship);
        }
        if ($isEmployee || strpos($textHints, 'attendance') !== false) {
            $names = array_merge($names, $attendance);
        }
        if ($isCustomer || strpos($textHints, 'rental') !== false || strpos($textHints, 'event') !== false
            || ! empty($memory['event_type']) || ! empty($memory['equipment'])) {
            $names = array_merge($names, $rental);
        }
        if (! $isIntern && ! $isEmployee) {
            // Casual visitor: company + rental discovery
            $names = array_merge($names, $rental);
        }
        if (strpos($textHints, 'document') !== false || ! empty($memory['verification_pending']) || ! empty($memory['document_choice_pending'])) {
            $names = array_merge($names, $documents);
        }

        $valid = [];
        foreach (array_unique($names) as $name) {
            if ($this->registry->get($name)) {
                $valid[] = $name;
            }
        }

        return $valid;
    }
}

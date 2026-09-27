<?php

namespace App\Services\Assistant;

class AssistantToolRegistry
{
    public function all()
    {
        return [
            'get_contact_summary' => ['description' => 'Controlled summary of the current WhatsApp contact', 'sensitivity' => 'PUBLIC', 'write' => false, 'roles' => []],
            'get_company_information' => ['description' => 'Approved company information', 'sensitivity' => 'PUBLIC', 'write' => false, 'roles' => []],
            'search_company_knowledge' => ['description' => 'Search approved company knowledge FAQs and procedures', 'sensitivity' => 'PUBLIC', 'write' => false, 'roles' => [], 'params' => ['query']],
            'get_services' => ['description' => 'Approved service list', 'sensitivity' => 'PUBLIC', 'write' => false, 'roles' => []],
            'search_rental_products' => ['description' => 'Search BeyondTechWorld\'s actual rental inventory/catalogue. Use when the user asks what equipment Beyond owns, rents, stocks or has available. Do NOT use for general educational questions about equipment (e.g. what a line array is). Prefer search_event_products / build_event_solution for vague "I need speakers" event requests.', 'sensitivity' => 'PUBLIC', 'write' => false, 'roles' => [], 'params' => ['query']],
            'check_rental_availability' => ['description' => 'Check a SPECIFIC named product\'s stock for a date. Do NOT use for vague "speakers/sound for my wedding" — use build_event_solution instead.', 'sensitivity' => 'PUBLIC', 'write' => false, 'roles' => [], 'params' => ['query', 'qty', 'event_date']],
            'create_rental_quotation' => ['description' => 'Create a draft ERP quotation from checked rental lines or event solution', 'sensitivity' => 'PUBLIC', 'write' => true, 'roles' => []],
            'request_rental_booking' => ['description' => 'Record a draft booking for staff approval', 'sensitivity' => 'PUBLIC', 'write' => true, 'roles' => []],
            'get_rental_product_information' => ['description' => 'Catalogue details for one BeyondTechWorld product. Not for general product education.', 'sensitivity' => 'PUBLIC', 'write' => false, 'roles' => [], 'params' => ['product_id']],
            'get_sound_experience_options' => ['description' => 'Wedding/event sound modes: Playback, Piano Bar, Full Setup. Ask date first if missing, then this. After answer, call get_event_extras_options.', 'sensitivity' => 'PUBLIC', 'write' => false, 'roles' => []],
            'get_event_extras_options' => ['description' => 'Checkbox multi-select for Lights, Screens, Stage (after sound mode). Customer may select all that apply.', 'sensitivity' => 'PUBLIC', 'write' => false, 'roles' => []],
            'get_sound_packages' => ['description' => 'Return Basic/Standard/Premium sound package prices from ERP configuration.', 'sensitivity' => 'PUBLIC', 'write' => false, 'roles' => []],
            'get_lighting_packages' => ['description' => 'Lighting tiers: Basic (no moving heads), Standard (par + par robots), Premium (all lights). Use after customer selects Lights in extras.', 'sensitivity' => 'PUBLIC', 'write' => false, 'roles' => []],
            'get_package_details' => ['description' => 'Details and component requirements for one event package (category+code).', 'sensitivity' => 'PUBLIC', 'write' => false, 'roles' => [], 'params' => ['category', 'code']],
            'search_event_products' => ['description' => 'Search suitable Product catalogue alternatives for an event category (speakers, lighting, etc). Never force one unavailable SKU when the request is generic.', 'sensitivity' => 'PUBLIC', 'write' => false, 'roles' => [], 'params' => ['category', 'event_date']],
            'check_event_equipment_availability' => ['description' => 'Check whether package/equipment lines can be allocated on a date using qty-based availability (multiple events per day allowed).', 'sensitivity' => 'PUBLIC', 'write' => false, 'roles' => [], 'params' => ['event_date', 'sound_package', 'lighting_package']],
            'calculate_stage_price' => ['description' => 'Deterministic stage pricing: area = length_m × width_m × configured CFA/m². Never invent the math.', 'sensitivity' => 'PUBLIC', 'write' => false, 'roles' => [], 'params' => ['length_m', 'width_m']],
            'calculate_screen_price' => ['description' => 'Deterministic LED screen pricing: area = length_m × width_m × 60,000 CFA/m² (configurable). Example 3×2 = 6 m² × 60,000 = 360,000 CFA.', 'sensitivity' => 'PUBLIC', 'write' => false, 'roles' => [], 'params' => ['length_m', 'width_m']],
            'get_truss_options' => ['description' => 'Truss with/without roof package options and configured prices.', 'sensitivity' => 'PUBLIC', 'write' => false, 'roles' => []],
            'calculate_transport_price' => ['description' => 'Within-town transport pricing from configured rules, or TRANSPORT_QUOTE_REQUIRED outside town.', 'sensitivity' => 'PUBLIC', 'write' => false, 'roles' => [], 'params' => ['within_town', 'sound_package', 'lighting_package']],
            'build_event_solution' => ['description' => 'PRIMARY tool for event production enquiries. Builds package + inventory + availability + estimate. Pass sound_mode, lighting_package, screen_length_m/screen_width_m, stage sizes. Playback maps to Basic Sound package.', 'sensitivity' => 'PUBLIC', 'write' => false, 'roles' => [], 'params' => ['event_type', 'event_date', 'venue', 'guest_count', 'sound_mode', 'sound_package', 'lighting_package', 'want_screen', 'screen_length_m', 'screen_width_m', 'stage_length_m', 'stage_width_m', 'truss_package', 'within_town']],
            'calculate_event_estimate' => ['description' => 'Same as build_event_solution — returns commercial estimate summary.', 'sensitivity' => 'PUBLIC', 'write' => false, 'roles' => [], 'params' => ['event_type', 'event_date', 'venue', 'guest_count', 'sound_package', 'lighting_package', 'want_screen', 'screen_length_m', 'screen_width_m', 'stage_length_m', 'stage_width_m', 'truss_package', 'within_town']],
            'create_event_quotation_draft' => ['description' => 'Create ERP quotation draft from a built event solution (package commercial lines + available equipment). Does not final-approve.', 'sensitivity' => 'PUBLIC', 'write' => true, 'roles' => []],
            'generate_quotation_review' => ['description' => 'Issue secure customer review link for an AI draft quotation (awaiting client signature).', 'sensitivity' => 'PUBLIC', 'write' => true, 'roles' => [], 'params' => ['quotation_id']],
            'get_quotation_review_status' => ['description' => 'Customer review / workflow status for a quotation.', 'sensitivity' => 'PUBLIC', 'write' => false, 'roles' => [], 'params' => ['quotation_id']],
            'get_customer_quotations' => ['description' => 'Quotations for the recognized customer', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['customer']],
            'get_customer_quotation_details' => ['description' => 'Owned quotation lines with historical prices and a current price check', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['customer'], 'params' => ['quotation_id']],
            'get_quotation_status' => ['description' => 'Status of one quotation', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['customer'], 'params' => ['quotation_id']],
            'get_customer_bookings' => ['description' => 'Bookings for the recognized customer', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['customer']],
            'get_booking_status' => ['description' => 'Status of one booking', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['customer'], 'params' => ['booking_id']],
            'get_customer_payment_summary' => ['description' => 'Financial summary — Phase 3 blocked without verification', 'sensitivity' => 'VERIFIED', 'write' => false, 'roles' => ['customer']],
            'get_internship_summary' => ['description' => 'Active internship enrolment summary for this person. Do NOT use for general education about internships.', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['intern']],
            'get_current_internship_task' => ['description' => 'Current released internship task for this authenticated intern. Use for "what am I supposed to do today". Do NOT use for general learning questions like what a VLAN is.', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['intern']],
            'get_task_instructions' => ['description' => 'Instructions for the current released task', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['intern']],
            'get_internship_progress' => ['description' => 'Internship progress from ERP records for this person', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['intern']],
            'get_task_materials' => ['description' => 'Existing handbook or task file for the released task', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['intern']],
            'get_submission_status' => ['description' => 'Latest ERP submission and grade status for this intern', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['intern']],
            'prepare_internship_submission' => ['description' => 'Collect files for an existing assignment without grading', 'sensitivity' => 'RECOGNIZED', 'write' => true, 'roles' => ['intern']],
            'submit_internship_work' => ['description' => 'Create the existing ERP submission after confirmation', 'sensitivity' => 'RECOGNIZED', 'write' => true, 'roles' => ['intern']],
            'attach_submission_file' => ['description' => 'Attach a validated file to the open intake', 'sensitivity' => 'RECOGNIZED', 'write' => true, 'roles' => ['intern']],
            'attach_submission_link' => ['description' => 'Attach a GitHub URL to the open intake', 'sensitivity' => 'RECOGNIZED', 'write' => true, 'roles' => ['intern']],
            'request_supervisor_handover' => ['description' => 'Hand the chat to the supervisor', 'sensitivity' => 'RECOGNIZED', 'write' => true, 'roles' => ['intern']],
            'get_attendance_status' => ['description' => 'Current ERP attendance for this person', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['employee', 'intern']],
            'check_in' => ['description' => 'Open today\'s attendance at server time', 'sensitivity' => 'RECOGNIZED', 'write' => true, 'roles' => ['employee', 'intern']],
            'check_out' => ['description' => 'Close today\'s attendance and update the timesheet', 'sensitivity' => 'RECOGNIZED', 'write' => true, 'roles' => ['employee', 'intern']],
            'get_work_hours' => ['description' => 'Hours from attendance and timesheet records', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['employee', 'intern']],
            'get_current_assignment' => ['description' => 'Today\'s field assignment for this person', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['employee', 'intern']],
            'check_in_assignment' => ['description' => 'Check in to an assigned job', 'sensitivity' => 'RECOGNIZED', 'write' => true, 'roles' => ['employee', 'intern']],
            'check_out_assignment' => ['description' => 'Check out of an assigned job', 'sensitivity' => 'RECOGNIZED', 'write' => true, 'roles' => ['employee', 'intern']],
            'validate_assignment_location' => ['description' => 'Deterministic distance check for a shared location', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['employee', 'intern']],
            'request_attendance_correction' => ['description' => 'Ask staff to correct a past attendance time', 'sensitivity' => 'RECOGNIZED', 'write' => true, 'roles' => ['employee', 'intern']],
            'get_attendance_correction_status' => ['description' => 'Status of this person\'s correction requests', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['employee', 'intern']],
            'get_current_lead' => ['description' => 'Open WhatsApp lead for this contact', 'sensitivity' => 'PUBLIC', 'write' => false, 'roles' => []],
            'request_human_handover' => ['description' => 'Switch conversation to HUMAN', 'sensitivity' => 'PUBLIC', 'write' => true, 'roles' => []],
            'list_available_documents' => ['description' => 'Document categories this identity may request', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['customer', 'employee', 'intern']],
            'find_my_documents' => ['description' => 'Owned document choices for this identity', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['customer', 'employee', 'intern']],
            'request_document' => ['description' => 'Resolve and authorize one owned ERP document', 'sensitivity' => 'RECOGNIZED', 'write' => true, 'roles' => ['customer', 'employee', 'intern']],
            'get_document_request_status' => ['description' => 'Status of this contact document request', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['customer', 'employee', 'intern']],
            'request_verification' => ['description' => 'Start a hashed OTP challenge for a document', 'sensitivity' => 'RECOGNIZED', 'write' => true, 'roles' => ['customer', 'employee', 'intern']],
            'verify_otp' => ['description' => 'Check a pending OTP without revealing the code', 'sensitivity' => 'RECOGNIZED', 'write' => true, 'roles' => ['customer', 'employee', 'intern']],
            'get_verification_status' => ['description' => 'Whether a verification session is active', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['customer', 'employee', 'intern']],
            'send_authorized_document' => ['description' => 'Send a document that already passed ownership and verification', 'sensitivity' => 'RECOGNIZED', 'write' => true, 'roles' => ['customer', 'employee', 'intern']],
            'get_my_tenancy' => ['description' => 'Active tenancy for this identity', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['tenant']],
            'get_rent_balance' => ['description' => 'Rent balance from obligations and payments', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['tenant']],
            'get_rent_due_date' => ['description' => 'Next rent due date', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['tenant']],
            'get_rent_payment_history' => ['description' => 'Rent payments recorded for this tenancy', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['tenant']],
            'get_maintenance_requests' => ['description' => 'Maintenance requests for this tenancy', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['tenant']],
            'create_maintenance_request' => ['description' => 'Open a maintenance request', 'sensitivity' => 'RECOGNIZED', 'write' => true, 'roles' => ['tenant']],
            'get_maintenance_status' => ['description' => 'Status of one owned maintenance request', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['tenant']],
            'add_maintenance_attachment' => ['description' => 'Attach a validated photo or voice note', 'sensitivity' => 'RECOGNIZED', 'write' => true, 'roles' => ['tenant']],
            'request_tenant_document' => ['description' => 'Start Stage 7 retrieval for a tenant document', 'sensitivity' => 'RECOGNIZED', 'write' => true, 'roles' => ['tenant']],
            'list_supported_bill_types' => ['description' => 'Configured bill categories', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['customer', 'tenant']],
            'create_bill_payment_request' => ['description' => 'Open a bill payment request without taking payment', 'sensitivity' => 'RECOGNIZED', 'write' => true, 'roles' => ['customer', 'tenant']],
            'get_bill_payment_request' => ['description' => 'One owned bill request', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['customer', 'tenant']],
            'update_bill_payment_details' => ['description' => 'Add provider, account, and amount for review', 'sensitivity' => 'RECOGNIZED', 'write' => true, 'roles' => ['customer', 'tenant']],
            'confirm_bill_payment_request' => ['description' => 'Confirm details for staff review without debiting', 'sensitivity' => 'RECOGNIZED', 'write' => true, 'roles' => ['customer', 'tenant']],
            'get_bill_payment_status' => ['description' => 'Status of an owned bill request', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['customer', 'tenant']],
            'request_bill_payment_handover' => ['description' => 'Hand a bill request to staff', 'sensitivity' => 'RECOGNIZED', 'write' => true, 'roles' => ['customer', 'tenant']],
            'attach_bill_image' => ['description' => 'Store a bill image as provisional evidence', 'sensitivity' => 'RECOGNIZED', 'write' => true, 'roles' => ['customer', 'tenant']],
            'reject_payment_claim' => ['description' => 'Refuse a WhatsApp payment claim that is not an ERP payment', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['tenant']],
            'clarify_tenant_balance' => ['description' => 'Ask which balance the person means', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['tenant']],
            'get_ai_status' => ['description' => 'Owner AI status', 'sensitivity' => 'OWNER', 'write' => false, 'roles' => ['owner']],
            'set_ai_enabled' => ['description' => 'Owner enable or disable the assistant', 'sensitivity' => 'OWNER', 'write' => true, 'roles' => ['owner']],
            'set_ai_first' => ['description' => 'Owner AI-first for new chats', 'sensitivity' => 'OWNER', 'write' => true, 'roles' => ['owner']],
            'switch_eligible_conversations_to_ai' => ['description' => 'Owner confirmed bulk AI switch', 'sensitivity' => 'OWNER', 'write' => true, 'roles' => ['owner']],
            'get_conversations_needing_attention' => ['description' => 'Owner waiting conversations', 'sensitivity' => 'OWNER', 'write' => false, 'roles' => ['owner']],
            'get_open_leads_summary' => ['description' => 'Owner open leads', 'sensitivity' => 'OWNER', 'write' => false, 'roles' => ['owner']],
            'get_pending_quotation_summary' => ['description' => 'Owner pending quotations from ERP', 'sensitivity' => 'OWNER', 'write' => false, 'roles' => ['owner']],
            'get_failed_whatsapp_summary' => ['description' => 'Owner failed WhatsApp sends', 'sensitivity' => 'OWNER', 'write' => false, 'roles' => ['owner']],
            'check_appointment_availability' => ['description' => 'Configured appointment windows only', 'sensitivity' => 'PUBLIC', 'write' => false, 'roles' => []],
            'create_appointment' => ['description' => 'Create an ERP appointment from a chosen slot', 'sensitivity' => 'PUBLIC', 'write' => true, 'roles' => []],
            'get_my_appointments' => ['description' => 'Upcoming appointments for this contact', 'sensitivity' => 'PUBLIC', 'write' => false, 'roles' => []],
            'cancel_appointment' => ['description' => 'Cancel one appointment on this contact', 'sensitivity' => 'PUBLIC', 'write' => true, 'roles' => []],
            'reschedule_appointment' => ['description' => 'Move one appointment to a free configured slot', 'sensitivity' => 'PUBLIC', 'write' => true, 'roles' => []],
            'assign_conversation_to_me' => ['description' => 'Owner takes one conversation', 'sensitivity' => 'OWNER', 'write' => true, 'roles' => ['owner']],
            'return_conversation_to_ai' => ['description' => 'Owner returns one conversation to AI', 'sensitivity' => 'OWNER', 'write' => true, 'roles' => ['owner']],
        ];
    }

    public function get($name)
    {
        $all = $this->all();

        return isset($all[$name]) ? $all[$name] : null;
    }

    public function names()
    {
        return array_keys($this->all());
    }
}

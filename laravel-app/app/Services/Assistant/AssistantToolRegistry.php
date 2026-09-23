<?php

namespace App\Services\Assistant;

class AssistantToolRegistry
{
    public function all()
    {
        return [
            'get_contact_summary' => ['description' => 'Controlled summary of the current WhatsApp contact', 'sensitivity' => 'PUBLIC', 'write' => false, 'roles' => []],
            'get_company_information' => ['description' => 'Approved company information', 'sensitivity' => 'PUBLIC', 'write' => false, 'roles' => []],
            'get_services' => ['description' => 'Approved service list', 'sensitivity' => 'PUBLIC', 'write' => false, 'roles' => []],
            'search_rental_products' => ['description' => 'Search active rental catalogue products', 'sensitivity' => 'PUBLIC', 'write' => false, 'roles' => [], 'params' => ['query']],
            'check_rental_availability' => ['description' => 'Check stock and overlapping bookings for a date', 'sensitivity' => 'PUBLIC', 'write' => false, 'roles' => [], 'params' => ['query', 'qty', 'event_date']],
            'create_rental_quotation' => ['description' => 'Create a draft ERP quotation from checked rental lines', 'sensitivity' => 'PUBLIC', 'write' => true, 'roles' => []],
            'request_rental_booking' => ['description' => 'Record a draft booking for staff approval', 'sensitivity' => 'PUBLIC', 'write' => true, 'roles' => []],
            'get_rental_product_information' => ['description' => 'Catalogue details for one product', 'sensitivity' => 'PUBLIC', 'write' => false, 'roles' => [], 'params' => ['product_id']],
            'get_customer_quotations' => ['description' => 'Quotations for the recognized customer', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['customer']],
            'get_quotation_status' => ['description' => 'Status of one quotation', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['customer'], 'params' => ['quotation_id']],
            'get_customer_bookings' => ['description' => 'Bookings for the recognized customer', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['customer']],
            'get_booking_status' => ['description' => 'Status of one booking', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['customer'], 'params' => ['booking_id']],
            'get_customer_payment_summary' => ['description' => 'Financial summary — Phase 3 blocked without verification', 'sensitivity' => 'VERIFIED', 'write' => false, 'roles' => ['customer']],
            'get_internship_summary' => ['description' => 'Active internship enrolment summary', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['intern']],
            'get_current_internship_task' => ['description' => 'Current released internship task', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['intern']],
            'get_task_instructions' => ['description' => 'Instructions for the current released task', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['intern']],
            'get_internship_progress' => ['description' => 'Internship progress from ERP records', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['intern']],
            'get_task_materials' => ['description' => 'Existing handbook or task file for the released task', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['intern']],
            'get_submission_status' => ['description' => 'Latest ERP submission and grade status', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['intern']],
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

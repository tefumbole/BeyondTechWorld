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
            'get_rental_product_information' => ['description' => 'Catalogue details for one product', 'sensitivity' => 'PUBLIC', 'write' => false, 'roles' => [], 'params' => ['product_id']],
            'get_customer_quotations' => ['description' => 'Quotations for the recognized customer', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['customer']],
            'get_quotation_status' => ['description' => 'Status of one quotation', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['customer'], 'params' => ['quotation_id']],
            'get_customer_bookings' => ['description' => 'Bookings for the recognized customer', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['customer']],
            'get_booking_status' => ['description' => 'Status of one booking', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['customer'], 'params' => ['booking_id']],
            'get_customer_payment_summary' => ['description' => 'Financial summary — Phase 3 blocked without verification', 'sensitivity' => 'VERIFIED', 'write' => false, 'roles' => ['customer']],
            'get_internship_summary' => ['description' => 'Internship enrolment summary', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['intern']],
            'get_current_internship_task' => ['description' => 'Current released internship task', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['intern']],
            'get_internship_progress' => ['description' => 'Internship progress counts', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['intern']],
            'get_current_lead' => ['description' => 'Open WhatsApp lead for this contact', 'sensitivity' => 'PUBLIC', 'write' => false, 'roles' => []],
            'request_human_handover' => ['description' => 'Switch conversation to HUMAN', 'sensitivity' => 'PUBLIC', 'write' => true, 'roles' => []],
            'list_available_documents' => ['description' => 'List existing quotation PDFs only', 'sensitivity' => 'RECOGNIZED', 'write' => false, 'roles' => ['customer']],
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

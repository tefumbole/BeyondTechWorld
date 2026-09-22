<?php

namespace App\Assistant;

class IntentCatalog
{
    const GREETING = 'GREETING';
    const GENERAL_ENQUIRY = 'GENERAL_ENQUIRY';
    const COMPANY_INFORMATION = 'COMPANY_INFORMATION';
    const SERVICE_ENQUIRY = 'SERVICE_ENQUIRY';
    const RENTAL_ENQUIRY = 'RENTAL_ENQUIRY';
    const EQUIPMENT_AVAILABILITY = 'EQUIPMENT_AVAILABILITY';
    const PRICE_ENQUIRY = 'PRICE_ENQUIRY';
    const QUOTATION_REQUEST = 'QUOTATION_REQUEST';
    const BOOKING_STATUS = 'BOOKING_STATUS';
    const PAYMENT_ENQUIRY = 'PAYMENT_ENQUIRY';
    const BALANCE_ENQUIRY = 'BALANCE_ENQUIRY';
    const RECEIPT_REQUEST = 'RECEIPT_REQUEST';
    const CONTRACT_REQUEST = 'CONTRACT_REQUEST';
    const INTERNSHIP_ENQUIRY = 'INTERNSHIP_ENQUIRY';
    const INTERNSHIP_TASK = 'INTERNSHIP_TASK';
    const INTERNSHIP_STATUS = 'INTERNSHIP_STATUS';
    const EMPLOYEE_ENQUIRY = 'EMPLOYEE_ENQUIRY';
    const DOCUMENT_REQUEST = 'DOCUMENT_REQUEST';
    const APPOINTMENT_REQUEST = 'APPOINTMENT_REQUEST';
    const TECHNICAL_SUPPORT = 'TECHNICAL_SUPPORT';
    const HUMAN_REQUEST = 'HUMAN_REQUEST';
    const COMPLAINT = 'COMPLAINT';
    const UNKNOWN = 'UNKNOWN';

    const ACTION_ANSWER = 'ANSWER';
    const ACTION_TOOL = 'USE_TOOL';
    const ACTION_CLARIFY = 'CLARIFY';
    const ACTION_HANDOVER = 'HANDOVER';
    const ACTION_REFUSE = 'REFUSE';

    public static function all()
    {
        return [
            self::GREETING => ['requires_erp' => false, 'sensitivity' => 'PUBLIC'],
            self::GENERAL_ENQUIRY => ['requires_erp' => false, 'sensitivity' => 'PUBLIC'],
            self::COMPANY_INFORMATION => ['requires_erp' => false, 'sensitivity' => 'PUBLIC'],
            self::SERVICE_ENQUIRY => ['requires_erp' => false, 'sensitivity' => 'PUBLIC'],
            self::RENTAL_ENQUIRY => ['requires_erp' => true, 'sensitivity' => 'PUBLIC'],
            self::EQUIPMENT_AVAILABILITY => ['requires_erp' => true, 'sensitivity' => 'PUBLIC'],
            self::PRICE_ENQUIRY => ['requires_erp' => true, 'sensitivity' => 'PUBLIC'],
            self::QUOTATION_REQUEST => ['requires_erp' => true, 'sensitivity' => 'RECOGNIZED'],
            self::BOOKING_STATUS => ['requires_erp' => true, 'sensitivity' => 'RECOGNIZED'],
            self::PAYMENT_ENQUIRY => ['requires_erp' => true, 'sensitivity' => 'VERIFIED'],
            self::BALANCE_ENQUIRY => ['requires_erp' => true, 'sensitivity' => 'VERIFIED'],
            self::RECEIPT_REQUEST => ['requires_erp' => true, 'sensitivity' => 'VERIFIED'],
            self::CONTRACT_REQUEST => ['requires_erp' => true, 'sensitivity' => 'VERIFIED'],
            self::INTERNSHIP_ENQUIRY => ['requires_erp' => false, 'sensitivity' => 'PUBLIC'],
            self::INTERNSHIP_TASK => ['requires_erp' => true, 'sensitivity' => 'RECOGNIZED'],
            self::INTERNSHIP_STATUS => ['requires_erp' => true, 'sensitivity' => 'RECOGNIZED'],
            self::EMPLOYEE_ENQUIRY => ['requires_erp' => true, 'sensitivity' => 'PRIVILEGED'],
            self::DOCUMENT_REQUEST => ['requires_erp' => true, 'sensitivity' => 'RECOGNIZED'],
            self::APPOINTMENT_REQUEST => ['requires_erp' => false, 'sensitivity' => 'PUBLIC'],
            self::TECHNICAL_SUPPORT => ['requires_erp' => false, 'sensitivity' => 'PUBLIC'],
            self::HUMAN_REQUEST => ['requires_erp' => false, 'sensitivity' => 'PUBLIC'],
            self::COMPLAINT => ['requires_erp' => false, 'sensitivity' => 'PUBLIC'],
            self::UNKNOWN => ['requires_erp' => false, 'sensitivity' => 'PUBLIC'],
        ];
    }

    public static function meta($intent)
    {
        $all = self::all();

        return isset($all[$intent]) ? $all[$intent] : $all[self::UNKNOWN];
    }

    public static function isKnown($intent)
    {
        return array_key_exists($intent, self::all());
    }
}

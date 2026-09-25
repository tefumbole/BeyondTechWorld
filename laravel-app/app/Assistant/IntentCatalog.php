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
    const RENTAL_QUOTE = 'RENTAL_QUOTE';
    const RENTAL_CONFIRM = 'RENTAL_CONFIRM';
    const RENTAL_ACCEPT = 'RENTAL_ACCEPT';
    const RENTAL_REVISION = 'RENTAL_REVISION';
    const DISCOUNT_REQUEST = 'DISCOUNT_REQUEST';
    const BOOKING_STATUS = 'BOOKING_STATUS';
    const PAYMENT_ENQUIRY = 'PAYMENT_ENQUIRY';
    const BALANCE_ENQUIRY = 'BALANCE_ENQUIRY';
    const RECEIPT_REQUEST = 'RECEIPT_REQUEST';
    const CONTRACT_REQUEST = 'CONTRACT_REQUEST';
    const INTERNSHIP_ENQUIRY = 'INTERNSHIP_ENQUIRY';
    const INTERNSHIP_TASK = 'INTERNSHIP_TASK';
    const INTERNSHIP_MATERIAL = 'INTERNSHIP_MATERIAL';
    const INTERNSHIP_SUBMIT = 'INTERNSHIP_SUBMIT';
    const INTERNSHIP_STATUS = 'INTERNSHIP_STATUS';
    const ATTENDANCE_IN = 'ATTENDANCE_IN';
    const ATTENDANCE_OUT = 'ATTENDANCE_OUT';
    const ATTENDANCE_STATUS = 'ATTENDANCE_STATUS';
    const ATTENDANCE_HOURS = 'ATTENDANCE_HOURS';
    const ATTENDANCE_ASSIGNMENT = 'ATTENDANCE_ASSIGNMENT';
    const ATTENDANCE_CORRECTION = 'ATTENDANCE_CORRECTION';
    const EMPLOYEE_ENQUIRY = 'EMPLOYEE_ENQUIRY';
    const DOCUMENT_REQUEST = 'DOCUMENT_REQUEST';
    const VERIFY_OTP = 'VERIFY_OTP';
    const TENANT_BALANCE = 'TENANT_BALANCE';
    const TENANT_DUE = 'TENANT_DUE';
    const TENANT_PAYMENTS = 'TENANT_PAYMENTS';
    const TENANT_CLAIM = 'TENANT_CLAIM';
    const TENANT_DOCUMENT = 'TENANT_DOCUMENT';
    const TENANT_CLARIFY = 'TENANT_CLARIFY';
    const MAINTENANCE_CREATE = 'MAINTENANCE_CREATE';
    const MAINTENANCE_STATUS = 'MAINTENANCE_STATUS';
    const MAINTENANCE_ATTACH = 'MAINTENANCE_ATTACH';
    const BILL_REQUEST = 'BILL_REQUEST';
    const BILL_CONFIRM = 'BILL_CONFIRM';
    const BILL_STATUS = 'BILL_STATUS';
    const BILL_MEDIA = 'BILL_MEDIA';
    const APPOINTMENT_REQUEST = 'APPOINTMENT_REQUEST';
    const TECHNICAL_SUPPORT = 'TECHNICAL_SUPPORT';
    const PREVIOUS_QUOTATION = 'PREVIOUS_QUOTATION';
    const CALL_REQUEST = 'CALL_REQUEST';
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
            self::RENTAL_QUOTE => ['requires_erp' => true, 'sensitivity' => 'PUBLIC'],
            self::RENTAL_CONFIRM => ['requires_erp' => true, 'sensitivity' => 'PUBLIC'],
            self::RENTAL_ACCEPT => ['requires_erp' => true, 'sensitivity' => 'PUBLIC'],
            self::RENTAL_REVISION => ['requires_erp' => true, 'sensitivity' => 'PUBLIC'],
            self::DISCOUNT_REQUEST => ['requires_erp' => false, 'sensitivity' => 'PUBLIC'],
            self::BOOKING_STATUS => ['requires_erp' => true, 'sensitivity' => 'RECOGNIZED'],
            self::PAYMENT_ENQUIRY => ['requires_erp' => true, 'sensitivity' => 'VERIFIED'],
            self::BALANCE_ENQUIRY => ['requires_erp' => true, 'sensitivity' => 'VERIFIED'],
            self::RECEIPT_REQUEST => ['requires_erp' => true, 'sensitivity' => 'VERIFIED'],
            self::CONTRACT_REQUEST => ['requires_erp' => true, 'sensitivity' => 'VERIFIED'],
            self::INTERNSHIP_ENQUIRY => ['requires_erp' => false, 'sensitivity' => 'PUBLIC'],
            self::INTERNSHIP_TASK => ['requires_erp' => true, 'sensitivity' => 'RECOGNIZED'],
            self::INTERNSHIP_MATERIAL => ['requires_erp' => true, 'sensitivity' => 'RECOGNIZED'],
            self::INTERNSHIP_SUBMIT => ['requires_erp' => true, 'sensitivity' => 'RECOGNIZED'],
            self::INTERNSHIP_STATUS => ['requires_erp' => true, 'sensitivity' => 'RECOGNIZED'],
            self::ATTENDANCE_IN => ['requires_erp' => true, 'sensitivity' => 'RECOGNIZED'],
            self::ATTENDANCE_OUT => ['requires_erp' => true, 'sensitivity' => 'RECOGNIZED'],
            self::ATTENDANCE_STATUS => ['requires_erp' => true, 'sensitivity' => 'RECOGNIZED'],
            self::ATTENDANCE_HOURS => ['requires_erp' => true, 'sensitivity' => 'RECOGNIZED'],
            self::ATTENDANCE_ASSIGNMENT => ['requires_erp' => true, 'sensitivity' => 'RECOGNIZED'],
            self::ATTENDANCE_CORRECTION => ['requires_erp' => true, 'sensitivity' => 'RECOGNIZED'],
            self::EMPLOYEE_ENQUIRY => ['requires_erp' => true, 'sensitivity' => 'PRIVILEGED'],
            self::DOCUMENT_REQUEST => ['requires_erp' => true, 'sensitivity' => 'RECOGNIZED'],
            self::VERIFY_OTP => ['requires_erp' => true, 'sensitivity' => 'RECOGNIZED'],
            self::TENANT_BALANCE => ['requires_erp' => true, 'sensitivity' => 'RECOGNIZED'],
            self::TENANT_DUE => ['requires_erp' => true, 'sensitivity' => 'RECOGNIZED'],
            self::TENANT_PAYMENTS => ['requires_erp' => true, 'sensitivity' => 'RECOGNIZED'],
            self::TENANT_CLAIM => ['requires_erp' => true, 'sensitivity' => 'RECOGNIZED'],
            self::TENANT_DOCUMENT => ['requires_erp' => true, 'sensitivity' => 'RECOGNIZED'],
            self::TENANT_CLARIFY => ['requires_erp' => true, 'sensitivity' => 'RECOGNIZED'],
            self::MAINTENANCE_CREATE => ['requires_erp' => true, 'sensitivity' => 'RECOGNIZED'],
            self::MAINTENANCE_STATUS => ['requires_erp' => true, 'sensitivity' => 'RECOGNIZED'],
            self::MAINTENANCE_ATTACH => ['requires_erp' => true, 'sensitivity' => 'RECOGNIZED'],
            self::BILL_REQUEST => ['requires_erp' => true, 'sensitivity' => 'RECOGNIZED'],
            self::BILL_CONFIRM => ['requires_erp' => true, 'sensitivity' => 'RECOGNIZED'],
            self::BILL_STATUS => ['requires_erp' => true, 'sensitivity' => 'RECOGNIZED'],
            self::BILL_MEDIA => ['requires_erp' => true, 'sensitivity' => 'RECOGNIZED'],
            self::APPOINTMENT_REQUEST => ['requires_erp' => false, 'sensitivity' => 'PUBLIC'],
            self::TECHNICAL_SUPPORT => ['requires_erp' => false, 'sensitivity' => 'PUBLIC'],
            self::PREVIOUS_QUOTATION => ['requires_erp' => true, 'sensitivity' => 'RECOGNIZED'],
            self::CALL_REQUEST => ['requires_erp' => false, 'sensitivity' => 'PUBLIC'],
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

<?php

namespace App\WhatsApp;

class LeadCatalog
{
    const SOURCE_WHATSAPP = 'WHATSAPP';
    const SOURCE_WEBSITE = 'WEBSITE';
    const SOURCE_PHONE = 'PHONE';
    const SOURCE_REFERRAL = 'REFERRAL';
    const SOURCE_WALK_IN = 'WALK_IN';
    const SOURCE_SOCIAL = 'SOCIAL';
    const SOURCE_OTHER = 'OTHER';

    const STATUS_NEW = 'NEW';
    const STATUS_CONTACTED = 'CONTACTED';
    const STATUS_QUALIFIED = 'QUALIFIED';
    const STATUS_QUOTATION_REQUIRED = 'QUOTATION_REQUIRED';
    const STATUS_QUOTATION_SENT = 'QUOTATION_SENT';
    const STATUS_FOLLOW_UP = 'FOLLOW_UP';
    const STATUS_CONVERTED = 'CONVERTED';
    const STATUS_LOST = 'LOST';
    const STATUS_SPAM = 'SPAM';

    const PRIORITY_LOW = 'LOW';
    const PRIORITY_NORMAL = 'NORMAL';
    const PRIORITY_HIGH = 'HIGH';

    const CAT_EQUIPMENT_RENTAL = 'EQUIPMENT_RENTAL';
    const CAT_EVENT_PRODUCTION = 'EVENT_PRODUCTION';
    const CAT_AUDIO = 'AUDIO';
    const CAT_LIGHTING = 'LIGHTING';
    const CAT_LED_SCREEN = 'LED_SCREEN';
    const CAT_NETWORKING = 'NETWORKING';
    const CAT_IT_SUPPORT = 'IT_SUPPORT';
    const CAT_CYBERSECURITY = 'CYBERSECURITY';
    const CAT_CCTV = 'CCTV';
    const CAT_SOFTWARE = 'SOFTWARE_DEVELOPMENT';
    const CAT_TRAINING = 'TRAINING';
    const CAT_INTERNSHIP = 'INTERNSHIP';
    const CAT_PROPERTY = 'PROPERTY_FACILITY';
    const CAT_BILL_PAYMENT = 'BILL_PAYMENT';
    const CAT_GENERAL = 'GENERAL_ENQUIRY';
    const CAT_OTHER = 'OTHER';

    public static function sources()
    {
        return [
            self::SOURCE_WHATSAPP => 'WhatsApp',
            self::SOURCE_WEBSITE => 'Website',
            self::SOURCE_PHONE => 'Phone',
            self::SOURCE_REFERRAL => 'Referral',
            self::SOURCE_WALK_IN => 'Walk-in',
            self::SOURCE_SOCIAL => 'Social',
            self::SOURCE_OTHER => 'Other',
        ];
    }

    public static function statuses()
    {
        return [
            self::STATUS_NEW => 'New',
            self::STATUS_CONTACTED => 'Contacted',
            self::STATUS_QUALIFIED => 'Qualified',
            self::STATUS_QUOTATION_REQUIRED => 'Quotation required',
            self::STATUS_QUOTATION_SENT => 'Quotation sent',
            self::STATUS_FOLLOW_UP => 'Follow-up',
            self::STATUS_CONVERTED => 'Converted',
            self::STATUS_LOST => 'Lost',
            self::STATUS_SPAM => 'Spam',
        ];
    }

    public static function closedStatuses()
    {
        return [self::STATUS_CONVERTED, self::STATUS_LOST, self::STATUS_SPAM];
    }

    public static function priorities()
    {
        return [
            self::PRIORITY_LOW => 'Low',
            self::PRIORITY_NORMAL => 'Normal',
            self::PRIORITY_HIGH => 'High',
        ];
    }

    public static function categories()
    {
        return [
            self::CAT_EQUIPMENT_RENTAL => 'Equipment Rental',
            self::CAT_EVENT_PRODUCTION => 'Event Production',
            self::CAT_AUDIO => 'Audio',
            self::CAT_LIGHTING => 'Lighting',
            self::CAT_LED_SCREEN => 'LED Screen',
            self::CAT_NETWORKING => 'Networking',
            self::CAT_IT_SUPPORT => 'IT Support',
            self::CAT_CYBERSECURITY => 'Cybersecurity',
            self::CAT_CCTV => 'CCTV',
            self::CAT_SOFTWARE => 'Software Development',
            self::CAT_TRAINING => 'Training',
            self::CAT_INTERNSHIP => 'Internship',
            self::CAT_PROPERTY => 'Property/Facility',
            self::CAT_BILL_PAYMENT => 'Bill Payment',
            self::CAT_GENERAL => 'General Enquiry',
            self::CAT_OTHER => 'Other',
        ];
    }

    public static function label($map, $key)
    {
        $items = $map();

        return isset($items[$key]) ? $items[$key] : $key;
    }
}

<?php

namespace App\Support;

/**
 * Approved Twilio WhatsApp templates and the Beyond messages that match them.
 * Chat replies, documents, groups, and sign links stay on Wasender.
 */
class ApprovedWhatsAppTemplates
{
    public static function forHub()
    {
        return [
            [
                'name' => 'beyond_sale_receipt',
                'category' => 'Utility',
                'sends' => 'Full sale receipt: items, total, payment, billing, delivery, and letter reference. WhatsApp is still reviewing it. Until then the shorter sale confirmation is sent.',
                'used' => false,
            ],
            [
                'name' => 'sales_confirmation',
                'category' => 'Utility',
                'sends' => 'A recorded sale, until the full receipt template is approved',
                'used' => true,
            ],
            [
                'name' => 'reminder',
                'category' => 'Marketing',
                'sends' => 'Booking reminder, event reminder, and rental return reminder',
                'used' => true,
            ],
            [
                'name' => 'beyond_status_update',
                'category' => 'Marketing',
                'sends' => 'Application received, not proceeding, and agreement signed',
                'used' => true,
            ],
            [
                'name' => 'beyond_service_update',
                'category' => 'Marketing',
                'sends' => 'Late rental return',
                'used' => true,
            ],
            [
                'name' => 'beyond_announcement',
                'category' => 'Marketing',
                'sends' => 'Announcements and announcement reminders. WhatsApp is still reviewing it, so those messages stay on the linked session until it is approved.',
                'used' => false,
            ],
            [
                'name' => 'beyond_review_link',
                'category' => 'Marketing',
                'sends' => 'Not sent. The button opens one fixed address, not the person’s own link.',
                'used' => false,
            ],
            [
                'name' => 'booking_received',
                'category' => 'Marketing',
                'sends' => 'Not sent. The text is for Manukeza Booking & Tours.',
                'used' => false,
            ],
        ];
    }
}

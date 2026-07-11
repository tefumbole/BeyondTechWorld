<?php

namespace App\Services;

use Twilio\Rest\Client;

class WhatsAppService
{
    protected $twilio;

    public function __construct()
    {
        $this->twilio = new Client(env('TWILIO_SID'), env('TWILIO_AUTH_TOKEN'));
    }

    public function sendMessage($to, $message)
    {
        try {
//            $message = $this->twilio->messages->create(
//                "whatsapp:".$to, // Receiver's WhatsApp number
//                [
//                    "from" => env('TWILIO_WHATSAPP_FROM'), // Twilio WhatsApp number
//                    "contentSid" => "HT09b62399e946d995b21b70d505f54802", // Correct Template SID
//                    "contentVariables" => '{"1": "rehan"}', // Proper JSON format
//                    "contentLanguage" => "English (US)" // Correct Language and Locale
//                ]
//            );

            $message = $this->twilio->messages->create(
                "whatsapp:+923410060960", // Recipient's WhatsApp number
                [
                    "from" => env('TWILIO_WHATSAPP_FROM'), // Twilio WhatsApp number
                    "body" => "Hello! This is a non-template message from Twilio."
                ]
            );
            return $message->sid;
        } catch (\Exception $e) {
            return "Error: " . $e->getMessage();
        }
    }
}

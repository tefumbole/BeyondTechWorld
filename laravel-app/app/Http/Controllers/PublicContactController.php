<?php

namespace App\Http\Controllers;

use App\Services\BeyondWasenderService;
use App\Support\SiteContent;
use App\Support\WhatsAppPhone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PublicContactController extends Controller
{
    public function store(Request $request, BeyondWasenderService $whatsapp)
    {
        $data = $request->validate([
            'country_code' => 'required|string|max:10',
            'phone' => 'required|string|max:40',
            'full_name' => 'required|string|max:255',
            'message' => 'required|string|max:4000',
        ]);

        try {
            $phone = WhatsAppPhone::combine($data['country_code'], $data['phone']);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Please enter a valid WhatsApp number.',
            ], 422);
        }

        $name = trim($data['full_name']);
        $body = trim($data['message']);
        $displayPhone = $data['country_code'].' '.preg_replace('/\D+/', '', $data['phone']);

        $userText = "Hello {$name},\n\n"
            ."We received your message:\n\n"
            ."\"{$body}\"\n\n"
            ."An assistant will get in touch with you shortly.\n\n"
            ."— Mbole AI";

        $staffPhone = preg_replace('/\D+/', '', SiteContent::text('contact.phone', '+237675321739'));
        $staffText = "*New contact via Mbole AI*\n\n"
            ."*Name:* {$name}\n"
            ."*WhatsApp:* {$displayPhone}\n\n"
            ."*Message:*\n{$body}";

        $sentUser = $whatsapp->sendText($phone, $userText);
        $sentStaff = ['success' => false];
        if ($staffPhone !== '') {
            try {
                $sentStaff = $whatsapp->sendText($staffPhone, $staffText);
            } catch (\Throwable $e) {
                Log::warning('Mbole AI staff notify failed: '.$e->getMessage());
            }
        }

        if (empty($sentUser['success'])) {
            Log::warning('Mbole AI user confirm failed', ['phone' => $phone, 'error' => $sentUser['error'] ?? null]);

            return response()->json([
                'ok' => false,
                'message' => 'Could not send WhatsApp confirmation. Please try again or chat on WhatsApp directly.',
            ], 502);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Message sent. Check WhatsApp — we echoed your message and an assistant will follow up.',
            'staff_notified' => ! empty($sentStaff['success']),
        ]);
    }
}

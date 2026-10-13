<?php

return [
    /*
    | MAI Voice kill switch. A storage file overrides this without a deploy.
    | Missing file: this value. Default is off.
    */
    'enabled' => (bool) env('MAI_VOICE_ENABLED', false),

    /*
    | Phase 1I does not connect a carrier. The only provider that can be
    | constructed is the in-process stub. A live trunk is a later phase.
    */
    'provider' => 'stub',

    /*
    | scripted: test harness that keeps call state and calls approved tool names.
    | conversational: not opened in this phase; the gateway uses the safe fallback.
    */
    'engine' => env('MAI_VOICE_ENGINE', 'scripted'),

    'recording' => false,
    'customer_voice' => false,
    'transcribe' => (bool) env('MAI_VOICE_TRANSCRIBE', true),

    'max_duration_seconds' => (int) env('MAI_VOICE_MAX_CALL_SECONDS', env('MAI_VOICE_MAX_DURATION', 600)),

    /*
    | Twilio WhatsApp Business Calling. Outbound calls and the realtime
    | media bridge stay off until a sender is eligible and explicitly armed.
    | The auth token is read from the environment and is never written here.
    */
    'twilio' => [
        'account_sid' => env('TWILIO_SID', env('ACCOUNT_SID', '')),
        'auth_token' => env('TWILIO_AUTH_TOKEN', env('AUTH_TOKEN', '')),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM', ''),
        'voice_application_sid' => env('MAI_TWILIO_VOICE_APP_SID', ''),
        'realtime_bridge' => false,
        'stream_url' => env('MAI_TWILIO_STREAM_URL', ''),
        'outbound_calls' => false,
        'recording' => false,
    ],
    'max_concurrent' => (int) env('MAI_VOICE_MAX_CONCURRENT', 2),
    'max_calls_per_caller_per_hour' => (int) env('MAI_VOICE_MAX_PER_HOUR', 8),
    'max_silent_turns' => 3,
    'daily_seconds_alert' => (int) env('MAI_VOICE_DAILY_SECONDS_ALERT', 3600),

    'default_assistant' => 'MAI',
    'default_language' => 'en',

    'greetings' => [
        'en' => "Hello, thank you for calling :company. I'm :assistant, the virtual assistant. How may I help you?",
        'fr' => "Bonjour, merci d'appeler :company. Je suis :assistant, l'assistant virtuel. Comment puis-je vous aider ?",
    ],
    'ask_name' => [
        'en' => 'May I have your name?',
        'fr' => 'Puis-je avoir votre nom ?',
    ],
    'welcome_back' => [
        'en' => 'Welcome back, :name.',
        'fr' => 'Bon retour, :name.',
    ],
    'after_hours' => [
        'en' => "Our office is currently closed, but I can still help you or take a message. I'm :assistant, the virtual assistant for :company.",
        'fr' => "Nos bureaux sont actuellement fermés, mais je peux encore vous aider ou prendre un message. Je suis :assistant, l'assistant virtuel de :company.",
    ],
    'lines' => [
        'en' => [
            'goodbye' => 'Thank you for calling :company. Have a good evening.',
            'transfer_ok' => 'I am transferring you now. I will stop speaking once a person takes the call.',
            'transfer_fail' => "I'm sorry, the team isn't available to take the call right now. Would you like me to take a message?",
            'callback' => "I'll ask :who to call you back. I will not share a private number.",
            'message_saved' => 'Thank you. I have the message and the time. Someone can call you back.',
            'payment_refused' => 'I cannot confirm a payment from this call. A payment is confirmed only when the billing records say so.',
            'not_authorized' => 'I cannot do that from a phone call. It needs a verified identity, not the number you are calling from.',
            'no_price' => "I don't have an authoritative price for that.",
            'no_product' => "I don't see that in the company catalogue.",
            'ai_down' => "I'm sorry, I can't continue the assistant right now. I can transfer you if someone is available, or take a message.",
            'duration' => 'This call has reached its time limit. Thank you for calling :company.',
            'silence' => "I can't hear you. If you are still there, please tell me how I can help.",
            'clarify' => 'How may I help you?',
        ],
        'fr' => [
            'goodbye' => 'Merci d\'avoir appelé :company. Bonne soirée.',
            'transfer_ok' => 'Je vous transfère. Je me tais dès qu\'une personne prend l\'appel.',
            'transfer_fail' => "Je suis désolée, l'équipe ne peut pas prendre l'appel maintenant. Voulez-vous laisser un message ?",
            'callback' => 'Je demande à :who de vous rappeler. Je ne donne pas de numéro privé.',
            'message_saved' => 'Merci. J\'ai le message et l\'heure.',
            'payment_refused' => 'Je ne peux pas confirmer un paiement au téléphone. Seuls les enregistrements de facturation le confirment.',
            'not_authorized' => 'Je ne peux pas faire cela depuis un appel. Il faut une identité vérifiée, pas seulement le numéro.',
            'no_price' => "Je n'ai pas de prix autorisé pour cela.",
            'no_product' => "Je ne vois pas cela dans le catalogue.",
            'ai_down' => "Je suis désolée, je ne peux pas continuer pour le moment. Je peux transférer ou prendre un message.",
            'duration' => 'Cet appel a atteint sa durée maximale. Merci d\'avoir appelé :company.',
            'silence' => 'Je ne vous entends pas. Dites-moi comment je peux vous aider.',
            'clarify' => 'Comment puis-je vous aider ?',
        ],
    ],
];

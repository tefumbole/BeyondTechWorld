<?php

return [
    'enabled' => filter_var(env('WHATSAPP_ASSISTANT_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'provider' => strtolower((string) env('AI_PROVIDER', 'openai')),
    'api_key' => env('AI_API_KEY', ''),
    'api_url' => env('AI_API_URL', 'https://api.openai.com/v1/chat/completions'),
    'model' => env('AI_MODEL', 'gpt-4o-mini'),
    'timeout' => max(5, (int) env('AI_TIMEOUT', 20)),
    'max_output_tokens' => max(64, (int) env('AI_MAX_OUTPUT_TOKENS', 400)),
    'temperature' => (float) env('AI_TEMPERATURE', 0.2),
    'max_tool_iterations' => max(1, (int) env('AI_MAX_TOOL_ITERATIONS', 3)),
    'max_clarifications' => max(1, (int) env('AI_MAX_CLARIFICATIONS', 4)),
    'confidence_high' => (float) env('AI_CONFIDENCE_HIGH', 0.75),
    'confidence_low' => (float) env('AI_CONFIDENCE_LOW', 0.40),
    'identify' => filter_var(env('WHATSAPP_ASSISTANT_IDENTIFY', true), FILTER_VALIDATE_BOOLEAN),
    'display_name' => env('WHATSAPP_ASSISTANT_NAME', 'Beyond Assistant'),
    'max_replies_per_contact_hour' => max(1, (int) env('AI_MAX_REPLIES_PER_CONTACT_HOUR', 20)),
    'memory_ttl_hours' => max(1, (int) env('AI_MEMORY_TTL_HOURS', 12)),
    'history_limit' => max(2, (int) env('AI_HISTORY_LIMIT', 8)),
    // Draft quotations above this total are saved but not auto-sent. Staff review them.
    'rental_auto_quote_max' => (float) env('WHATSAPP_RENTAL_AUTO_QUOTE_MAX', 500000),
];

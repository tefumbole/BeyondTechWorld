<?php

namespace App\Services\Assistant;

/**
 * Resolve OpenAI / assistant credentials without printing secrets.
 */
class AssistantAiConfig
{
    public static function apiKey()
    {
        $candidates = [
            config('assistant.api_key'),
            getenv('OPENAI_API_KEY') ?: null,
            getenv('AI_API_KEY') ?: null,
            isset($_ENV['OPENAI_API_KEY']) ? $_ENV['OPENAI_API_KEY'] : null,
            isset($_ENV['AI_API_KEY']) ? $_ENV['AI_API_KEY'] : null,
        ];
        try {
            if (class_exists(\App\WhatsApp\WhatsAppSetting::class)) {
                $candidates[] = \App\WhatsApp\WhatsAppSetting::getValue('openai_api_key', '');
                $candidates[] = \App\WhatsApp\WhatsAppSetting::getValue('ai_api_key', '');
            }
        } catch (\Throwable $e) {
        }
        foreach ($candidates as $raw) {
            $key = trim((string) $raw);
            if ($key !== '') {
                return $key;
            }
        }

        return '';
    }

    public static function isConfigured()
    {
        return self::apiKey() !== '';
    }

    public static function providerName()
    {
        return strtolower((string) config('assistant.provider', 'openai'));
    }

    public static function model()
    {
        return (string) config('assistant.model', 'gpt-4o-mini');
    }

    public static function shouldUseOpenAi()
    {
        $name = self::providerName();

        return $name !== 'null' && self::isConfigured();
    }
}

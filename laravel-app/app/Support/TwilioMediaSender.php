<?php

namespace App\Support;

use App\Services\Messaging\TwilioTemplateSender;
use App\Services\TwilioWhatsAppService;

/**
 * Sends a file or a choice through a generic Twilio template when WhatsApp has approved it.
 */
class TwilioMediaSender
{
    public function trySend($phone, $localPath, $fileName = null, $caption = null)
    {
        $ext = strtolower(pathinfo((string) $localPath, PATHINFO_EXTENSION));
        $images = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $documents = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'];
        if (in_array($ext, $images, true)) {
            $configKey = 'content_sid_shared_image';
        } elseif ($ext === '' || in_array($ext, $documents, true)) {
            $configKey = 'content_sid_shared_document';
        } else {
            return ['success' => false, 'provider' => 'twilio', 'error' => 'This file type stays on the linked session.'];
        }

        if (! $this->approved($configKey)) {
            return ['success' => false, 'provider' => 'twilio', 'error' => 'Template is not approved yet.'];
        }

        $relative = TwilioMedia::relativePublicPath($localPath);
        if ($relative === '') {
            return ['success' => false, 'provider' => 'twilio', 'error' => 'The file is not on the public website.'];
        }

        $fileName = trim((string) ($fileName ?: basename($localPath)));
        $caption = (string) $caption;
        $sender = app(TwilioTemplateSender::class);
        $name = $this->name($caption);
        $organisation = WhatsAppMessage::companyName();
        $reference = LetterReference::extractFromText($caption) ?: '-';

        if ($configKey === 'content_sid_shared_image') {
            return $sender->sendSharedImage($phone, $name, $organisation, $fileName, $reference, $relative);
        }

        return $sender->sendSharedDocument($phone, $name, $organisation, $fileName, $reference, $relative);
    }

    public function tryChoice($phone, $name, $prompt, array $options, $reference = '-')
    {
        $parts = array_values(array_filter(array_map(function ($option) {
            return trim((string) $option);
        }, $options), function ($option) {
            return $option !== '';
        }));
        $prompt = trim((string) $prompt);
        $optionsText = $prompt;
        if (count($parts) > 0) {
            $optionsText = trim($prompt.($prompt !== '' ? ' ' : '').implode(' | ', $parts));
        }
        if ($optionsText === '') {
            return ['success' => false, 'provider' => 'twilio', 'error' => 'Choice text is empty.'];
        }

        return app(TwilioTemplateSender::class)->sendSharedChoice(
            $phone,
            trim((string) $name) !== '' ? $name : 'Friend',
            WhatsAppMessage::companyName(),
            $optionsText,
            trim((string) $reference) !== '' ? $reference : '-'
        );
    }

    protected function approved($configKey)
    {
        $sid = trim((string) config('services.whatsapp.'.$configKey, ''));

        return $sid !== '' && app(TwilioWhatsAppService::class)->contentApprovalStatus($sid) === 'approved';
    }

    protected function name($caption)
    {
        if (preg_match('/Dear\s+([^,\n]+)/i', (string) $caption, $match)) {
            $name = trim($match[1], " *_\t");
            if ($name !== '') {
                return $name;
            }
        }

        return 'Friend';
    }
}

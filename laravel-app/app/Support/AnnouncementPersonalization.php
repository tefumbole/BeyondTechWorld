<?php

namespace App\Support;

class AnnouncementPersonalization
{
    public static function personalize($template, array $vars)
    {
        if ($template === null || $template === '') {
            return '';
        }
        $result = $template;
        foreach ($vars as $key => $value) {
            $result = preg_replace('/\{' . preg_quote($key, '/') . '\}/i', (string) ($value ?? ''), $result);
        }

        return $result;
    }

    public static function recipientVars(array $person, $reference = '', $institution = 'Beyond Enterprise')
    {
        return [
            'Name' => $person['name'] ?? '',
            'name' => $person['name'] ?? '',
            'Phone' => $person['phone'] ?? '',
            'phone' => $person['phone'] ?? '',
            'Email' => $person['email'] ?? '',
            'email' => $person['email'] ?? '',
            'Address' => $person['address'] ?? '',
            'address' => $person['address'] ?? '',
            'date' => date('d M Y'),
            'reference' => $reference,
            'institution_name' => $institution,
        ];
    }

    /**
     * Wasender free-text announcement — same visual language as OTP
     * (status block, greeting, Reference/Date bullets, footer).
     */
    public static function buildMessage($announcement, array $person, $isCc = false)
    {
        $personalized = ! empty($announcement->personalized);
        if (! $personalized) {
            $person['name'] = '';
        }
        $institution = trim((string) ($announcement->header ?: WhatsAppMessage::companyName()));
        $reference = trim((string) ($announcement->reference ?? ''));
        $vars = self::recipientVars($person, $reference, $institution !== '' ? $institution : 'Beyond Enterprise');

        $body = trim(self::personalize($announcement->body ?: '', $vars));
        $footer = trim(self::personalize($announcement->footer ?: '', $vars));
        $name = trim((string) ($person['name'] ?? ''));

        $body = preg_replace('/^\s*Dear\s+[^,\n]*,\s*/iu', '', $body);
        $body = trim($body);
        $header = trim(self::personalize($announcement->header ?: '', $vars));

        $msg = '';
        if ($header !== '') {
            $msg .= '📢 *'.$header."*\n\n";
        }
        if ($personalized && $name !== '') {
            $msg .= 'Dear *'.$name."*,\n\n";
        } else {
            $msg .= "Hello,\n\n";
        }
        if ($isCc) {
            $msg .= "You have been copied on this announcement.\n\n";
        }
        if ($body !== '') {
            $msg .= $body."\n";
        }
        if ($footer !== '') {
            $msg .= "\n_".$footer."_";
        }
        $company = trim(WhatsAppMessage::companyName());
        if ($company !== '' && strcasecmp($company, $footer) !== 0 && strcasecmp($company, $header) !== 0) {
            $msg .= "\n\n_".$company.'_';
        }

        return trim($msg)."\n";
    }

    /**
     * Clean body for Twilio beyond_notice {{3}} — no Ref/header/subject wrappers
     * (those map to other template variables and the template already greets the client).
     */
    public static function buildTwilioBody($announcement, array $person, $isCc = false)
    {
        $settingsInstitution = $announcement->header ?: 'Beyond Enterprise';
        $vars = self::recipientVars($person, $announcement->reference ?: '', $settingsInstitution);
        $body = trim(self::personalize($announcement->body ?: '', $vars));
        $footer = trim(self::personalize($announcement->footer ?: '', $vars));

        $parts = [];
        if ($isCc) {
            $parts[] = 'You have been CC\'d on this announcement.';
        }
        if ($body !== '') {
            $parts[] = $body;
        }
        if ($footer !== '') {
            $parts[] = $footer;
        }

        $text = trim(implode("\n\n", $parts));
        // WhatsApp template variables are plain text — strip markdown emphasis.
        $text = preg_replace('/\*+/', '', $text);
        $text = preg_replace('/_+/', '', $text);
        $text = trim(preg_replace("/[ \t]+/", ' ', str_replace(["\r\n", "\r"], "\n", $text)));

        return $text;
    }
}

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

    public static function buildMessage($announcement, array $person, $isCc = false)
    {
        $settingsInstitution = $announcement->header ?: 'Beyond Enterprise';
        $vars = self::recipientVars($person, $announcement->reference ?: '', $settingsInstitution);
        $body = self::personalize($announcement->body ?: '', $vars);
        $header = self::personalize($announcement->header ?: '', $vars);
        $subject = self::personalize($announcement->subject ?: '', $vars);
        $footer = self::personalize($announcement->footer ?: '', $vars);

        $lines = [];
        if ($isCc) {
            $lines[] = "📨 *ANNOUNCEMENT CC*";
            $lines[] = "━━━━━━━━━━━━━━━";
            $lines[] = "";
            $lines[] = "Hello *" . ($person['name'] ?: 'Team') . "*,";
            $lines[] = "";
            $lines[] = "You have been CC'd on this announcement:";
            $lines[] = "";
        }

        if (! empty($announcement->reference)) {
            $lines[] = "Ref: *" . $announcement->reference . "*";
        }
        if ($header !== '') {
            $lines[] = "*" . $header . "*";
        }
        if ($subject !== '') {
            $lines[] = "_" . $subject . "_";
        }
        if ($body !== '') {
            $lines[] = "";
            $lines[] = $body;
        }
        if ($footer !== '') {
            $lines[] = "";
            $lines[] = $footer;
        }

        return implode("\n", $lines);
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

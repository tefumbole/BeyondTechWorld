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
}

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
        $parent = trim((string) ($person['parent'] ?? $person['Parent'] ?? $person['name'] ?? ''));
        $student = trim((string) ($person['student'] ?? $person['Student'] ?? $person['organization'] ?? ''));
        $className = trim((string) ($person['class'] ?? $person['Class'] ?? $person['class_name'] ?? ''));

        return [
            'Name' => $person['name'] ?? '',
            'name' => $person['name'] ?? '',
            'Parent' => $parent,
            'parent' => $parent,
            'Student' => $student,
            'student' => $student,
            'Class' => $className,
            'class' => $className,
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
        if (isset($person['kind']) && $person['kind'] === 'group') {
            $person['name'] = '';
        }
        $person['name'] = self::usableName(isset($person['name']) ? $person['name'] : '', isset($person['phone']) ? $person['phone'] : '');
        $institution = trim((string) ($announcement->header ?: ''));
        $reference = trim((string) ($announcement->reference ?? ''));
        $vars = self::recipientVars($person, $reference, $institution);
        if ($person['name'] !== '') {
            $vars['name'] = '*'.$person['name'].'*';
            $vars['Name'] = '*'.$person['name'].'*';
        }

        $body = trim(self::personalize($announcement->body ?: '', $vars));
        $footer = trim(self::personalize($announcement->footer ?: '', $vars));
        $name = trim((string) ($person['name'] ?? ''));

        $body = self::stripOpeningDear($body);
        if ($footer === '') {
            list($body, $lifted) = self::splitClosing($body);
            if ($lifted !== '') {
                $footer = $lifted;
                $lines = preg_split('/\n+/', trim($lifted));
                $lines = is_array($lines) ? array_filter($lines, function ($line) {
                    return trim((string) $line) !== '';
                }) : [];
                if (count($lines) < 3) {
                    $company = self::companyLine();
                    if ($company !== '' && stripos($footer, $company) === false) {
                        $footer .= "\n".$company;
                    }
                }
            }
        }
        $body = self::tidyBody($body);
        $header = trim(self::personalize($announcement->header ?: '', $vars));

        $msg = '';
        if ($header !== '') {
            $msg .= '📢 *'.$header."*\n\n";
        }
        if ($name !== '') {
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
        $signoff = $footer;
        if ($signoff === '') {
            $signoff = self::companyLine();
        }
        $signoff = self::italicSignoff($signoff);
        if ($signoff !== '') {
            $msg .= "\n\n".$signoff;
        }

        return trim($msg)."\n";
    }

    /**
     * A programmed reminder uses the same greeting, including the recipient's name.
     */
    public static function reminderMessage($announcement, array $person)
    {
        $body = ltrim(self::buildMessage($announcement, $person, false));

        return "⏰ *Reminder*\n\n".$body;
    }

    /**
     * Drop a leading "Dear …" line. The greeting is added with the recipient's name.
     */
    public static function stripOpeningDear($body)
    {
        $body = str_replace(["\r\n", "\r"], "\n", trim((string) $body));
        if (! preg_match('/^\s*Dear\b([^\n]*)/iu', $body, $match)) {
            return $body;
        }
        $line = trim($match[1]);
        $words = trim($line, " \t,.*_");
        if (strlen($words) > 80) {
            return $body;
        }
        $stripped = preg_replace('/^\s*Dear\b[^\n]*\n*/iu', '', $body, 1);

        return trim((string) $stripped);
    }

    /**
     * A closing such as "Sincerely." belongs with the footer, not in the paragraph.
     *
     * @return array{0:string,1:string}
     */
    public static function splitClosing($body)
    {
        $body = str_replace(["\r\n", "\r"], "\n", trim((string) $body));
        $pattern = '/(?:^|\n)([ \t]*(?:Sincerely|Yours faithfully|Yours sincerely|Kind regards|Best regards|Warm regards|Best wishes|Regards)\b[^\n]*(?:\n[ \t]*[^\n]+){0,4})\s*$/iu';
        if (! preg_match($pattern, $body, $match, PREG_OFFSET_CAPTURE)) {
            return [$body, ''];
        }
        $signoff = trim($match[1][0]);
        $main = trim(substr($body, 0, $match[1][1]));

        return [$main, $signoff];
    }

    public static function tidyBody($body)
    {
        $body = str_replace(["\r\n", "\r"], "\n", trim((string) $body));
        $body = preg_replace("/[ \t]+\n/", "\n", $body);
        $body = preg_replace("/\n{3,}/", "\n\n", (string) $body);

        return trim((string) $body);
    }

    protected static function companyLine()
    {
        try {
            if (! function_exists('app')) {
                return '';
            }

            return trim((string) WhatsAppMessage::companyName());
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * One italic WhatsApp line per footer line, so a typed footer stays the last lines.
     */
    public static function italicSignoff($text)
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim((string) $text));
        if ($text === '') {
            return '';
        }
        $lines = preg_split('/\n+/', $text);
        $out = [];
        foreach ($lines as $line) {
            $line = trim(str_replace('_', ' ', (string) $line));
            if ($line === '') {
                continue;
            }
            $out[] = '_'.$line.'_';
        }

        return implode("\n", $out);
    }

    public static function usableName($name, $phone = '')
    {
        $name = trim((string) $name);
        $compact = preg_replace('/[\s\-\(\)]+/', '', $name);
        $digits = preg_replace('/\D+/', '', $name);
        $phoneDigits = preg_replace('/\D+/', '', (string) $phone);
        if ($name === '' || preg_match('/^\d+$/', $digits) && $digits === preg_replace('/\D+/', '', $compact)) {
            return '';
        }
        if ($phoneDigits !== '' && $digits === $phoneDigits) {
            return '';
        }
        if (preg_match('/^\+?\d{8,}$/', $compact)) {
            return '';
        }
        $upper = strtoupper($name);
        if (in_array($upper, ['N/A', 'NA', 'NAN', 'NULL', 'NONE', 'NO WHATSAPP NAME', 'NO NAME'], true)) {
            return '';
        }

        return $name;
    }

    /**
     * Clean body for Twilio beyond_notice {{3}} — no Ref/header/subject wrappers
     * (those map to other template variables and the template already greets the client).
     */
    public static function buildTwilioBody($announcement, array $person, $isCc = false)
    {
        $settingsInstitution = $announcement->header ?: 'Beyond Enterprise';
        $person['name'] = self::usableName(isset($person['name']) ? $person['name'] : '', isset($person['phone']) ? $person['phone'] : '');
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

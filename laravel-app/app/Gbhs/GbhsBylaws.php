<?php

namespace App\Gbhs;

class GbhsBylaws
{
    public static function lines()
    {
        return [
            'GBHS Kumbo Ex-Students Association',
            'Short bylaws',
            'Version 2026-1',
            '',
            '1. Name',
            'The association is the GBHS Kumbo Ex-Students Association,',
            'for former students of Government Bilingual High School Kumbo.',
            '',
            '2. Membership',
            'A former student may apply with a phone number and the',
            'details the association asks for. Membership begins when',
            'the application is approved and a membership number is issued.',
            '',
            '3. Annual registration',
            'Each member registers once every calendar year.',
            'The executive sets the annual registration amount.',
            'A member who does not register that year stays on the roll',
            'but is not in good standing until the registration is paid.',
            '',
            '4. Conduct',
            'Members treat one another with respect.',
            'Nobody collects money in the association name',
            'unless the executive has agreed.',
            '',
            '5. Money',
            'A contribution is received only after the association',
            'or the payment provider confirms it.',
            'Cancelled and reversed amounts are not counted as received.',
            '',
            '6. Meetings',
            'The executive calls a general meeting at least once a year.',
            '',
            '7. Changes',
            'These bylaws change only when a general meeting agrees',
            'and a new version is published.',
        ];
    }

    public static function file()
    {
        return 'gbhs/bylaws/gbhs-bylaws-2026.pdf';
    }

    public static function ensureFile()
    {
        $relative = self::file();
        $path = public_path($relative);
        if (is_file($path)) {
            return $relative;
        }
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        file_put_contents($path, self::pdf(self::lines()));

        return $relative;
    }

    public static function publish()
    {
        if (! GbhsTenant::ready()) {
            return;
        }
        $file = self::ensureFile();
        if (GbhsBylawVersion::where('status', 'published')->whereNotNull('file')->exists()) {
            return;
        }
        GbhsBylawVersion::create([
            'version' => '2026-1',
            'effective_on' => '2026-10-17',
            'file' => $file,
            'status' => 'published',
        ]);
    }

    protected static function pdf(array $lines)
    {
        $commands = "BT /F1 12 Tf 50 780 Td 16 TL\n";
        foreach ($lines as $line) {
            $commands .= '('.self::escape($line).") Tj T*\n";
        }
        $commands .= "ET\n";
        $objects = [
            "1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj\n",
            "2 0 obj<< /Type /Pages /Count 1 /Kids [3 0 R] >>endobj\n",
            "3 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources<< /Font<< /F1 5 0 R >> >> >>endobj\n",
            "4 0 obj<< /Length ".strlen($commands)." >>stream\n".$commands."endstream\nendobj\n",
            "5 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>endobj\n",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";

        return $pdf;
    }

    protected static function escape($text)
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string) $text);
    }
}

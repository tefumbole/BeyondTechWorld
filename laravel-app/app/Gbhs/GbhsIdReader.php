<?php

namespace App\Gbhs;

class GbhsIdReader
{
    /**
     * Read a temporary image, then the caller deletes it.
     * Returns empty strings when no reader is available.
     */
    public function read($path)
    {
        return $this->parse($this->ocr($path));
    }

    public function parse($text)
    {
        $text = str_replace("\r", "\n", (string) $text);
        $name = $this->nameFromLabels($text);
        $number = $this->numberFromLabels($text);
        $issued = $this->issuedOn($text);
        $mrz = $this->fromMrz($text);
        if ($name === '' && $mrz['name'] !== '') {
            $name = $mrz['name'];
        }
        if ($number === '' && $mrz['number'] !== '') {
            $number = $mrz['number'];
        }

        return [
            'name' => $name,
            'number' => $number,
            'issued_on' => $issued,
            'read' => $name !== '' || $number !== '' || $issued !== '',
        ];
    }

    protected function ocr($path)
    {
        if (! is_string($path) || ! is_file($path) || ! function_exists('shell_exec')) {
            return '';
        }
        $binary = trim((string) @shell_exec('command -v tesseract 2>/dev/null'));
        if ($binary === '') {
            return '';
        }
        $command = escapeshellarg($binary).' '.escapeshellarg($path).' stdout -l eng --psm 6 2>/dev/null';
        $text = @shell_exec($command);

        return is_string($text) ? $text : '';
    }

    protected function nameFromLabels($text)
    {
        $surname = $this->labelValue($text, 'surname|nom|family name|last name');
        $given = $this->labelValue($text, 'given names|given name|pr[eé]noms|first names|first name');
        if ($surname !== '' || $given !== '') {
            return trim($given.' '.$surname);
        }

        return $this->labelValue($text, 'full name|name');
    }

    protected function labelValue($text, $labels)
    {
        $pattern = '/(?:^|\n)\s*(?:'.$labels.')\s*[:\-]\s*([A-Za-z][A-Za-z\'\- ]{1,60})/i';
        if (! preg_match($pattern, $text, $match)) {
            return '';
        }
        $value = trim(preg_replace('/\s+/', ' ', $match[1]));
        $value = preg_replace('/\b(date|passport|nationality|sex|place|number)\b.*$/i', '', $value);

        return trim($value);
    }

    protected function numberFromLabels($text)
    {
        $patterns = [
            '/passport\s*(?:no|number|n[o\x{00B0}])\s*[:\.]?\s*([A-Z0-9][A-Z0-9\-]{4,20})/iu',
            '/(?:id(?:entification)?|document|cni)\s*(?:no|number|card)?\s*[:\.]?\s*([A-Z0-9][A-Z0-9\-]{4,20})/iu',
            '/(?:^|\n)\s*N(?:\x{00B0}|\x{00BA}|o|\.)?\s*[:\.]?\s*([0-9]{6,14})/iu',
        ];
        $match = [];
        $found = false;
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $found = true;
                break;
            }
        }
        if (! $found) {
            return '';
        }

        return strtoupper(preg_replace('/[^A-Z0-9]/', '', $match[1]));
    }

    protected function issuedOn($text)
    {
        $patterns = [
            '/(?:date of issue|date of issuance|date d.?emission|issued(?: on)?|delivr[eé](?: le)?|date of delivery)\s*[:\-]?\s*(\d{1,2}[\/\.\-]\d{1,2}[\/\.\-]\d{2,4})/i',
            '/(?:date of issue|date of issuance|issued(?: on)?|delivr[eé](?: le)?)\s*[:\-]?\s*(\d{1,2}\s+[A-Za-z]{3,9}\s+\d{4})/i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $date = $this->normalizeDate($match[1]);
                if ($date !== '') {
                    return $date;
                }
            }
        }

        return '';
    }

    protected function fromMrz($text)
    {
        $name = '';
        $number = '';
        $flat = strtoupper($text);
        if (preg_match('/P[<K][A-Z]{3}([A-Z<]+)<<([A-Z<]+)/', $flat, $match)) {
            $surname = trim(preg_replace('/\s+/', ' ', str_replace('<', ' ', $match[1])));
            $given = trim(preg_replace('/\s+/', ' ', str_replace('<', ' ', $match[2])));
            $name = trim($given.' '.$surname);
        }
        if (preg_match('/\n([A-Z0-9<]{44})/', "\n".$flat, $match)) {
            $line = $match[1];
            if (strpos($line, 'P<') !== 0 && strpos($line, 'PK') !== 0) {
                $number = rtrim(substr($line, 0, 9), '<');
                $number = preg_replace('/[^A-Z0-9]/', '', $number);
            }
        }
        if ($number === '' && preg_match('/P<[A-Z]{3}[A-Z<]+\n([A-Z0-9<]{9})/', $flat, $match)) {
            $number = rtrim($match[1], '<');
        }

        return ['name' => $name, 'number' => $number];
    }

    protected function normalizeDate($raw)
    {
        $raw = trim($raw);
        if (preg_match('/^(\d{1,2})[\/\.\-](\d{1,2})[\/\.\-](\d{2,4})$/', $raw, $match)) {
            $day = (int) $match[1];
            $month = (int) $match[2];
            $year = (int) $match[3];
            if ($year < 100) {
                $year += $year >= 70 ? 1900 : 2000;
            }
            if ($day <= 12 && $month > 12) {
                $swap = $day;
                $day = $month;
                $month = $swap;
            }
            if (! checkdate($month, $day, $year)) {
                return '';
            }

            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }
        $stamp = strtotime($raw);
        if ($stamp === false) {
            return '';
        }

        return date('Y-m-d', $stamp);
    }
}

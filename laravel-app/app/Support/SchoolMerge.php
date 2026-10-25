<?php

namespace App\Support;

use App\LetterOrganization;

/**
 * Parent / student / class merge for school letters such as St. Frederick.
 *
 * A customer stores the parent as the name, the student as the company name,
 * and the class as the city. A WhatsApp name written as
 * "Parent | Student | Class" is read the same way.
 */
class SchoolMerge
{
    public static function isSchool($name = '', $subject = '', $body = '', $header = '')
    {
        $hay = strtolower(trim($name.' '.$subject.' '.$header.' '.$body));
        if ($hay === '') {
            return false;
        }
        if (strpos($hay, 'fredrick') !== false || strpos($hay, 'frederick') !== false) {
            return true;
        }

        return (bool) preg_match('/[\[{]\s*(parent|student|class|child)(_name)?\s*[\}\]]/i', $body.' '.$subject.' '.$header);
    }

    public static function matchesAnnouncement($announcement)
    {
        if (! $announcement) {
            return false;
        }

        return self::isSchool(
            isset($announcement->template_name) ? $announcement->template_name : '',
            isset($announcement->subject) ? $announcement->subject : '',
            isset($announcement->body) ? $announcement->body : '',
            trim((isset($announcement->header) ? $announcement->header : '').' '.(isset($announcement->footer) ? $announcement->footer : ''))
        );
    }

    /**
     * @return array{parent:string,student:string,class:string}
     */
    public static function values(array $person)
    {
        $parsed = self::parseName(isset($person['name']) ? $person['name'] : '');
        $parent = self::clean(self::first($person, ['parent', 'Parent']));
        $student = self::clean(self::first($person, ['student', 'Student', 'company_name', 'organization']));
        $className = self::clean(self::first($person, ['class', 'Class', 'class_name', 'city']));
        if ($student === '') {
            $student = self::clean(isset($person['column1']) ? $person['column1'] : '');
        }
        if ($className === '') {
            $className = self::clean(isset($person['column2']) ? $person['column2'] : '');
        }
        if ($parent === '' && $parsed['parent'] !== '') {
            $parent = $parsed['parent'];
        }
        if ($student === '' && $parsed['student'] !== '') {
            $student = $parsed['student'];
        }
        if ($className === '' && $parsed['class'] !== '') {
            $className = $parsed['class'];
        }
        if ($parent === '') {
            $parent = self::clean(isset($person['customer_name']) ? $person['customer_name'] : '');
        }
        if ($parent === '') {
            $fallback = isset($person['name']) ? $person['name'] : '';
            if ($parsed['parent'] !== '') {
                $fallback = $parsed['parent'];
            }
            $parent = AnnouncementPersonalization::usableName($fallback, isset($person['phone']) ? $person['phone'] : '');
        }

        return [
            'parent' => $parent,
            'student' => $student,
            'class' => $className,
        ];
    }

    public static function decorate(array $person)
    {
        $parsed = self::parseName(isset($person['name']) ? $person['name'] : '');
        $values = self::values($person);
        $person['parent'] = $values['parent'];
        $person['student'] = $values['student'];
        $person['class'] = $values['class'];
        if ($parsed['parent'] !== '' && $parsed['student'] !== '') {
            $person['name'] = $values['parent'] !== '' ? $values['parent'] : $parsed['parent'];
        }

        return $person;
    }

    /**
     * Replace {Parent}, [Student], {class_name}, and the same family of tokens.
     * An empty value leaves the token in place so a missing student stays visible.
     */
    public static function fill($text, array $person, $reference = '')
    {
        $text = (string) $text;
        if ($text === '') {
            return '';
        }
        $values = self::values($person);
        $name = $values['parent'] !== ''
            ? $values['parent']
            : AnnouncementPersonalization::usableName(isset($person['name']) ? $person['name'] : '', isset($person['phone']) ? $person['phone'] : '');
        $map = [
            'parent' => $values['parent'],
            'parent_name' => $values['parent'],
            'student' => $values['student'],
            'student_name' => $values['student'],
            'child' => $values['student'],
            'child_name' => $values['student'],
            'class' => $values['class'],
            'class_name' => $values['class'],
            'name' => $name,
            'phone' => isset($person['phone']) ? $person['phone'] : (isset($person['phone_number']) ? $person['phone_number'] : ''),
            'phone_number' => isset($person['phone_number']) ? $person['phone_number'] : (isset($person['phone']) ? $person['phone'] : ''),
            'email' => isset($person['email']) ? $person['email'] : '',
            'address' => isset($person['address']) ? $person['address'] : '',
            'date' => date('d M Y'),
            'reference' => (string) $reference,
        ];
        foreach ($map as $key => $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            $text = preg_replace('/[\[{]\s*'.preg_quote($key, '/').'\s*[\}\]]/i', $value, $text);
        }

        return $text;
    }

    /**
     * Plain letter used for the attached PDF.
     *
     * @return array{heading:string,reference:string,date:string,subject:string,body:string,footer:string}
     */
    public static function documentParts($announcement, array $person)
    {
        $person = self::decorate($person);
        $reference = trim((string) (isset($announcement->reference) ? $announcement->reference : ''));
        $subject = trim(self::fill(isset($announcement->subject) ? $announcement->subject : '', $person, $reference));
        $body = trim(self::fill(isset($announcement->body) ? $announcement->body : '', $person, $reference));
        $footer = trim(self::fill(isset($announcement->footer) ? $announcement->footer : '', $person, $reference));
        $header = trim(self::fill(isset($announcement->header) ? $announcement->header : '', $person, $reference));
        $heading = self::schoolTitle($header."\n".$subject."\n".$body."\n".$footer);
        if ($heading === '') {
            $heading = $header !== '' ? $header : $subject;
        }
        if ($footer !== '' && stripos($body, $footer) !== false) {
            $footer = '';
        }

        return [
            'heading' => $heading,
            'reference' => $reference,
            'date' => date('d M Y'),
            'subject' => $subject,
            'body' => $body,
            'footer' => $footer,
        ];
    }

    /**
     * Write the filled letter and return its path, or null when it cannot be built.
     */
    public static function writePdf($announcement, array $person)
    {
        if (! self::matchesAnnouncement($announcement) || ! function_exists('app')) {
            return null;
        }
        try {
            $parts = self::documentParts($announcement, $person);
            if (trim($parts['body'].$parts['subject']) === '') {
                return null;
            }
            $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) (isset($announcement->id) ? $announcement->id : 'draft'));
            $dir = storage_path('app/school-letters/'.$id);
            if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
                return null;
            }
            $phone = preg_replace('/\D+/', '', (string) (isset($person['phone']) ? $person['phone'] : ''));
            $path = $dir.'/'.($phone !== '' ? $phone : 'letter').'.pdf';
            $letterhead = self::letterhead();
            $pdf = \PDF::loadView('pdf.school_letter', [
                'parts' => $parts,
                'headerImage' => $letterhead['header'],
                'footerImage' => $letterhead['footer'],
            ])->setPaper('a4', 'portrait');
            $pdf->save($path);

            return is_file($path) ? $path : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function fileName(array $person)
    {
        $student = self::clean(isset($person['student']) ? $person['student'] : '');
        $slug = trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $student), '-');
        if ($slug === '') {
            $slug = 'Letter';
        }

        return 'St-Frederick-'.$slug.'.pdf';
    }

    public static function schoolTitle($text)
    {
        if (preg_match("/St\\.?\\s*Fred(?:e)?rick(?:'s)?(?:\\s+College)?(?:,\\s*[^\\n.]+)?/i", (string) $text, $match)) {
            return trim($match[0]);
        }

        return '';
    }

    /**
     * @return array{parent:string,student:string,class:string}
     */
    public static function parseName($name)
    {
        $empty = ['parent' => '', 'student' => '', 'class' => ''];
        $name = trim((string) $name);
        if ($name === '' || ! preg_match('/\s(?:\||\/|—|–|-)\s/u', $name)) {
            return $empty;
        }
        $parts = preg_split('/\s+(?:\||\/|—|–|-)\s+/u', $name);
        if (! is_array($parts)) {
            return $empty;
        }
        $parts = array_values(array_filter(array_map(function ($part) {
            return self::clean($part);
        }, $parts), function ($part) {
            return $part !== '';
        }));
        if (count($parts) < 2) {
            return $empty;
        }

        return [
            'parent' => $parts[0],
            'student' => $parts[1],
            'class' => isset($parts[2]) ? $parts[2] : '',
        ];
    }

    public static function clean($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $upper = strtoupper($value);
        if (in_array($upper, ['N/A', 'NA', 'NAN', 'NULL', 'NONE', 'UNTITLED', '-', '.', 'NO NAME'], true)) {
            return '';
        }

        return $value;
    }

    protected static function first(array $person, array $keys)
    {
        foreach ($keys as $key) {
            if (isset($person[$key]) && trim((string) $person[$key]) !== '') {
                return $person[$key];
            }
        }

        return '';
    }

    /**
     * @return array{header:?string,footer:?string}
     */
    protected static function letterhead()
    {
        $empty = ['header' => null, 'footer' => null];
        try {
            if (! class_exists(LetterOrganization::class)) {
                return $empty;
            }
            $org = LetterOrganization::query()
                ->where('name', 'like', '%fredrick%')
                ->orWhere('name', 'like', '%frederick%')
                ->orderBy('id')
                ->first();
            if (! $org) {
                return $empty;
            }
            $resolved = Letterhead::forOrganization($org);

            return [
                'header' => ! empty($resolved['header_path']) ? $resolved['header_path'] : null,
                'footer' => ! empty($resolved['footer_path']) ? $resolved['footer_path'] : null,
            ];
        } catch (\Throwable $e) {
            return $empty;
        }
    }
}

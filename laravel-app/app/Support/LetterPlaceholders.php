<?php

namespace App\Support;

use App\Application;
use App\Services\InternshipAcceptanceLetterService;

class LetterPlaceholders
{
    /**
     * Build replacement map for letter / PDF personalization.
     * Supports standard tokens plus internship acceptance tokens.
     *
     * @param  object|null  $recipient
     * @return array<string, string>
     */
    public static function map($recipient): array
    {
        $r = self::enrich($recipient) ?: (object) [];

        $get = function ($key) use ($r) {
            if (is_array($r)) {
                return (string) ($r[$key] ?? '');
            }

            return (string) ($r->{$key} ?? '');
        };

        $map = [
            '[name]' => $get('name'),
            '[phone_number]' => $get('phone_number'),
            '[email]' => $get('email'),
            '[address]' => $get('address'),
            '[school]' => $get('school'),
            '[system_name]' => $get('system_name'),
            '[program]' => $get('program'),
            '[supervisors]' => $get('supervisors'),
            '[start_date]' => $get('start_date'),
            '[end_date]' => $get('end_date'),
            '[duration]' => $get('duration'),
            '[password]' => $get('password') !== '' ? $get('password') : InternshipAcceptanceLetterService::DEFAULT_PASSWORD,
            '[username]' => $get('username') !== '' ? $get('username') : $get('phone_number'),
        ];

        for ($i = 1; $i <= 10; $i++) {
            $val = $get('column'.$i);
            $map['[Column'.$i.']'] = $val;
            $map['[column'.$i.']'] = $val;
        }

        return $map;
    }

    /**
     * Merge internship application fields onto a thin directory recipient.
     *
     * @param  object|array|null  $recipient
     * @return object|array|null
     */
    public static function enrich($recipient)
    {
        if ($recipient === null) {
            return null;
        }

        $asArray = is_array($recipient);
        $get = function ($key) use ($recipient, $asArray) {
            if ($asArray) {
                return $recipient[$key] ?? null;
            }

            return $recipient->{$key} ?? null;
        };

        $program = trim((string) ($get('program') ?? ''));
        $password = trim((string) ($get('password') ?? ''));
        $supervisors = trim((string) ($get('supervisors') ?? ''));
        if ($program !== '' && $password !== '' && $supervisors !== '') {
            return $recipient;
        }

        $application = self::resolveApplication($recipient);
        if (! $application) {
            if ($password === '' && ! $asArray) {
                $recipient->password = InternshipAcceptanceLetterService::DEFAULT_PASSWORD;
            } elseif ($password === '' && $asArray) {
                $recipient['password'] = InternshipAcceptanceLetterService::DEFAULT_PASSWORD;
            }

            return $recipient;
        }

        $payload = app(InternshipAcceptanceLetterService::class)->buildRecipientPayload($application);

        if ($asArray) {
            foreach ($payload as $key => $value) {
                if (! isset($recipient[$key]) || trim((string) $recipient[$key]) === '') {
                    $recipient[$key] = $value;
                }
            }
            if (empty($recipient['phone_number']) && ! empty($recipient['phone'])) {
                $recipient['phone_number'] = $recipient['phone'];
            }

            return $recipient;
        }

        foreach ($payload as $key => $value) {
            $current = isset($recipient->{$key}) ? trim((string) $recipient->{$key}) : '';
            if ($current === '') {
                $recipient->{$key} = $value;
            }
        }
        if (empty($recipient->phone_number) && ! empty($recipient->phone)) {
            $recipient->phone_number = $recipient->phone;
        }

        return $recipient;
    }

    /**
     * @param  object|array  $recipient
     */
    protected static function resolveApplication($recipient): ?Application
    {
        $asArray = is_array($recipient);
        $id = $asArray
            ? ($recipient['directory_id'] ?? $recipient['id'] ?? null)
            : ($recipient->directory_id ?? $recipient->id ?? null);

        if (is_string($id) && strpos($id, 'applicant:') === 0) {
            $appId = substr($id, strlen('applicant:'));
            if ($appId !== '') {
                $app = Application::with(['job', 'internshipProgram'])->find($appId);
                if ($app) {
                    return $app;
                }
            }
        }

        $email = strtolower(trim((string) ($asArray
            ? ($recipient['email'] ?? '')
            : ($recipient->email ?? ''))));
        if ($email === '') {
            return null;
        }

        return Application::with(['job', 'internshipProgram'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * @param  string|null  $text
     * @param  object|null  $recipient
     */
    public static function replace($text, $recipient): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        return strtr((string) $text, self::map($recipient));
    }
}

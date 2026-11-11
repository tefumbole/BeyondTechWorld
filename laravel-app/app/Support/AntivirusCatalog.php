<?php

namespace App\Support;

use App\Antivirus\AntivirusRequest;

class AntivirusCatalog
{
    const CHIEF_ROLE = 'Chief of Administration and Finance';

    public static function regions()
    {
        return [
            'Adamawa',
            'Centre',
            'East',
            'Far North',
            'Littoral',
            'North',
            'North-West',
            'South',
            'South-West',
            'West',
        ];
    }

    public static function requesterRoles()
    {
        return [
            'engineer' => 'Engineer',
            'it_technician' => 'IT Technician',
            'assistant_engineer' => 'Assistant Engineer',
            'other' => 'Other',
        ];
    }

    public static function approverRoles()
    {
        return [
            'administrator' => 'Administrator',
            'assistant_finance' => 'Assistant Administrator of Finance',
            'assistant_personnel' => 'Assistant Administrator of Personnel',
            'other' => 'Other',
        ];
    }

    public static function roleLabel($kind, $key, $other = null)
    {
        $map = $kind === 'approver' ? self::approverRoles() : self::requesterRoles();
        $label = isset($map[$key]) ? $map[$key] : (string) $key;
        $other = trim((string) $other);
        if ($key === 'other' && $other !== '') {
            return 'Other ('.$other.')';
        }

        return $label;
    }

    public static function samePhone($left, $right)
    {
        $a = substr(preg_replace('/\D/', '', (string) $left), -9);
        $b = substr(preg_replace('/\D/', '', (string) $right), -9);

        return strlen($a) >= 8 && $a === $b;
    }

    public static function fingerprint(AntivirusRequest $row)
    {
        $payload = implode('|', [
            preg_replace('/\D/', '', (string) $row->requester_phone),
            mb_strtolower(trim((string) $row->requester_name)),
            (string) $row->requester_role,
            mb_strtolower(trim((string) $row->requester_role_other)),
            (string) $row->region,
            mb_strtolower(trim((string) $row->station)),
            (int) $row->quantity,
            (int) $row->year,
        ]);

        return hash('sha256', $payload);
    }

    public static function approverList(array $people)
    {
        $parts = [];
        foreach ($people as $person) {
            $parts[] = $person['name'].' ('.self::roleLabel('approver', $person['role'], isset($person['role_other']) ? $person['role_other'] : '').')';
        }
        if (count($parts) === 0) {
            return '';
        }
        if (count($parts) === 1) {
            return $parts[0];
        }

        $last = array_pop($parts);

        return implode(', ', $parts).' and '.$last;
    }

    public static function submittedMessage(AntivirusRequest $row, $approverLine)
    {
        return self::cbcMessage(
            'Anti Virus Approval Request',
            $row->requester_name,
            'Your request for Anti Virus licences has been submitted.',
            [
                'Station' => $row->station,
                'Approver' => $approverLine,
                'Number of licences' => (string) $row->quantity,
            ],
            'After approval it will be sent to the Chief of Administration and Finance.',
            $row->reference
        );
    }

    public static function approverInviteMessage(AntivirusRequest $row, $link, $approverName = '')
    {
        $role = self::roleLabel('requester', $row->requester_role, $row->requester_role_other);
        $dear = trim((string) $approverName) !== '' ? $approverName : 'Approver';

        return self::cbcMessage(
            'Anti Virus Approval Request',
            $dear,
            $row->requester_name.', '.$role.' at '.$row->station.', has requested Anti Virus licences. Please review and sign here: '.$link,
            [
                'Station' => $row->station,
                'Requester' => $row->requester_name.', '.$role,
                'Number of licences' => (string) $row->quantity,
            ],
            '',
            $row->reference
        );
    }

    public static function stationApprovedMessage(AntivirusRequest $row)
    {
        return self::cbcMessage(
            'Anti Virus Approval',
            $row->requester_name,
            'Your Anti Virus request has received station approval.',
            [
                'Station' => $row->station,
                'Number of licences' => (string) $row->quantity,
            ],
            'It will be sent to the Chief of Administration and Finance.',
            $row->reference
        );
    }

    public static function chiefForwardMessage(AntivirusRequest $row, $dashboardUrl)
    {
        return self::cbcMessage(
            'Anti Virus Approval Request',
            'Chief of Administration and Finance',
            $row->requester_name.' at '.$row->station.' requested Anti Virus licences. Station approval is complete.',
            [
                'Station' => $row->station,
                'Requester' => $row->requester_name,
                'Number of licences' => (string) $row->quantity,
            ],
            "Open Antivirus Requests to review and sign:\n".$dashboardUrl,
            $row->reference
        );
    }

    public static function finalApprovalMessage(AntivirusRequest $row, $link, $name = '')
    {
        $dear = trim((string) $name) !== '' ? $name : $row->requester_name;

        return self::cbcMessage(
            'Anti Virus Approval',
            $dear,
            'The Chief of Administration and Finance has approved this Anti Virus request.',
            [
                'Station' => $row->station,
                'Number of licences' => (string) $row->quantity,
            ],
            "View the signed approval:\n".$link,
            $row->reference
        );
    }

    public static function receiptMessage(AntivirusRequest $row, $link)
    {
        return self::cbcMessage(
            'Anti Virus Receipt',
            $row->requester_name,
            'Please confirm how many Anti Virus licences '.$row->station.' received.',
            [
                'Station' => $row->station,
                'Approved quantity' => (string) $row->quantity,
            ],
            "Confirm here:\n".$link,
            $row->reference
        );
    }

    public static function declinedMessage(AntivirusRequest $row)
    {
        return self::cbcMessage(
            'Anti Virus Request Declined',
            $row->requester_name,
            'Your Anti Virus request was declined.',
            [
                'Station' => $row->station,
                'Reason' => (string) $row->decline_reason,
            ],
            '',
            $row->reference
        );
    }

    private static function cbcMessage($title, $name, $intro, array $facts, $closing, $reference)
    {
        $text = WhatsAppMessage::statusBlock('🛡️', $title);
        $text .= 'Dear *'.trim((string) $name)."*,\n\n";
        $text .= trim((string) $intro)."\n\n";
        foreach ($facts as $label => $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            $text .= WhatsAppMessage::bullet($label, $value);
        }
        if (trim((string) $closing) !== '') {
            $text .= "\n".trim((string) $closing)."\n";
        }
        if (trim((string) $reference) !== '') {
            $text .= "\n".WhatsAppMessage::bullet('Reference', $reference);
        }
        $text .= "\n_CBC Health Services_";

        return $text;
    }
}

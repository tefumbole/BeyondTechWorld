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
        return 'Your request for '.$row->quantity.' antivirus licences for '.$row->station.' for '.$row->year
            .' has been submitted. It has been sent to '.$approverLine
            .'. After station approval, it will be forwarded to the Chief of Administration and Finance. Ref '.$row->reference.'.';
    }

    public static function approverInviteMessage(AntivirusRequest $row, $link)
    {
        $role = self::roleLabel('requester', $row->requester_role, $row->requester_role_other);

        return $row->requester_name.', '.$role.' at '.$row->station.', has requested '.$row->quantity
            .' antivirus licences for '.$row->year.'. Please review and sign here: '.$link;
    }

    public static function stationApprovedMessage(AntivirusRequest $row)
    {
        return 'Your antivirus request for '.$row->station
            .' has received station approval and has been forwarded to the Chief of Administration and Finance. Ref '.$row->reference.'.';
    }

    public static function chiefForwardMessage(AntivirusRequest $row, $dashboardUrl)
    {
        return $row->requester_name.' at '.$row->station.' ('.$row->region.') requested '.$row->quantity
            .' antivirus licences for '.$row->year.'. Station approval is complete. Ref '.$row->reference
            .'. Open Antivirus Requests to review and sign: '.$dashboardUrl;
    }

    public static function finalApprovalMessage(AntivirusRequest $row, $link)
    {
        return 'The Chief of Administration and Finance has approved '.$row->quantity
            .' antivirus licences for '.$row->station.' for '.$row->year.'. View the signed approval: '.$link;
    }

    public static function receiptMessage(AntivirusRequest $row, $link)
    {
        return 'Please confirm how many antivirus licences '.$row->station.' received for '.$row->year
            .'. The approved quantity is '.$row->quantity.'. Confirm here: '.$link;
    }

    public static function declinedMessage(AntivirusRequest $row)
    {
        return 'Your antivirus request for '.$row->station.' ('.$row->reference.') was declined. Reason: '.$row->decline_reason;
    }
}

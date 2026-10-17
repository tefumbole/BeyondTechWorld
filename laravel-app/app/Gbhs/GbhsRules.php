<?php

namespace App\Gbhs;

class GbhsRules
{
    const REPORTED_TOTAL = 9685000;

    public static function mayStorePersonal($phoneVerified)
    {
        return (bool) $phoneVerified;
    }

    /**
     * Existing customers keep their pricing group. A new customer with no group
     * can be placed in GBHS EX-students.
     */
    public static function pricingGroupId($currentGroupId, $gbhsGroupId)
    {
        if ($currentGroupId && (int) $currentGroupId !== (int) $gbhsGroupId) {
            return (int) $currentGroupId;
        }
        if ($currentGroupId) {
            return (int) $currentGroupId;
        }

        return $gbhsGroupId ? (int) $gbhsGroupId : null;
    }

    public static function shouldCreateMember($existingMemberId)
    {
        return ! $existingMemberId;
    }

    public static function countsAsReceived(array $row)
    {
        $class = isset($row['classification']) ? $row['classification'] : '';
        if ($class === 'reversal' || $class === 'in_kind') {
            return false;
        }

        return true;
    }

    public static function summarize(array $rows, $reported = self::REPORTED_TOTAL)
    {
        $monetary = 0;
        $verified = 0;
        $unverified = 0;
        $cancelled = 0;
        $inKind = 0;
        $inKindQty = 0;
        foreach ($rows as $row) {
            $class = isset($row['classification']) ? $row['classification'] : '';
            $amount = (int) (isset($row['amount']) ? $row['amount'] : 0);
            if ($class === 'reversal') {
                $cancelled += $amount;
                continue;
            }
            if ($class === 'in_kind') {
                $inKind += (int) (isset($row['in_kind_value']) ? $row['in_kind_value'] : 0);
                $inKindQty += (int) (isset($row['in_kind_qty']) ? $row['in_kind_qty'] : 0);
                continue;
            }
            $monetary += $amount;
            if (! empty($row['verified'])) {
                $verified += $amount;
            } else {
                $unverified += $amount;
            }
        }

        return [
            'reported' => (int) $reported,
            'monetary' => $monetary,
            'verified' => $verified,
            'unverified' => $unverified,
            'cancelled' => $cancelled,
            'in_kind' => $inKind,
            'in_kind_qty' => $inKindQty,
            'difference' => (int) $reported - $monetary,
        ];
    }

    public static function paidFromBrowserReturn($providerConfirmed)
    {
        return (bool) $providerConfirmed;
    }

    /**
     * One send per confirmed @g.us JID. Already-sent targets are left out.
     */
    public static function groupTargets(array $jids, array $alreadySent)
    {
        $out = [];
        foreach ($jids as $jid) {
            $jid = trim((string) $jid);
            if (! preg_match('/@g\.us$/', $jid)) {
                continue;
            }
            if (in_array($jid, $alreadySent, true) || in_array($jid, $out, true)) {
                continue;
            }
            $out[] = $jid;
        }

        return $out;
    }
}

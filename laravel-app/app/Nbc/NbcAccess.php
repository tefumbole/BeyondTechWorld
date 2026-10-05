<?php

namespace App\Nbc;

class NbcAccess
{
    public static function keys()
    {
        return [
            'nbc.profile' => 'Own profile',
            'nbc.practice.view' => 'View practices',
            'nbc.attendance.self' => 'Clock in and out',
            'events.view' => 'View events',
            'announcements.view' => 'View announcements',
            'announcements' => 'Write announcements',
            'events' => 'Write the program and events',
            'tasks' => 'Task manager',
            'letters' => 'Letters',
            'quotations' => 'Quotations',
            'whatsapp' => 'WhatsApp notices',
            'nbc.practice.manage' => 'Manage practices',
            'nbc.attendance.review' => 'Review attendance',
            'nbc.people' => 'People and approvals',
            'nbc.permissions' => 'Change permissions',
            'nbc.bylaws' => 'Edit the bylaws',
        ];
    }

    public static function defaults($role)
    {
        $member = [
            'nbc.profile', 'nbc.practice.view', 'nbc.attendance.self',
            'events.view', 'announcements.view',
        ];
        $leader = array_merge($member, [
            'announcements', 'events', 'tasks', 'letters', 'quotations', 'whatsapp',
            'nbc.practice.manage', 'nbc.attendance.review',
        ]);
        $owner = array_merge($leader, ['nbc.people', 'nbc.permissions', 'nbc.bylaws']);
        if ($role === 'owner') {
            return $owner;
        }
        if ($role === 'leader') {
            return $leader;
        }

        return $member;
    }

    public static function allows(NbcMember $member, $key)
    {
        $list = $member->permissionList();

        return in_array($key, $list, true);
    }
}

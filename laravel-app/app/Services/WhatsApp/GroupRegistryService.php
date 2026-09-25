<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\User;
use App\WhatsApp\WhatsAppGroup;
use Illuminate\Support\Facades\Schema;

class GroupRegistryService
{
    public function discover()
    {
        if (! Schema::hasTable('whatsapp_groups')) {
            return [];
        }
        $provider = app(WhatsAppProviderInterface::class);
        $listed = method_exists($provider, 'listGroups') ? $provider->listGroups() : ['groups' => []];
        $groups = isset($listed['groups']) && is_array($listed['groups']) ? $listed['groups'] : [];
        $rows = [];
        foreach ($groups as $group) {
            $jid = isset($group['jid']) ? (string) $group['jid'] : '';
            if ($jid === '' || substr($jid, -5) !== '@g.us') {
                continue;
            }
            $row = WhatsAppGroup::firstOrNew(['group_jid' => $jid]);
            if (! $row->exists) {
                $row->enabled = false;
                $row->mode = WhatsAppGroup::OFF;
            }
            if (! empty($group['name'])) {
                $row->name = $group['name'];
            }
            if (! empty($group['description'])) {
                $row->description = $group['description'];
            }
            $row->save();
            $rows[] = $row;
        }

        return $rows;
    }

    public function upsertFromMessage(array $parsed)
    {
        $jid = isset($parsed['group_jid']) ? (string) $parsed['group_jid'] : '';
        if ($jid === '') {
            return null;
        }
        $row = WhatsAppGroup::firstOrNew(['group_jid' => $jid]);
        if (! $row->exists) {
            $row->enabled = false;
            $row->mode = WhatsAppGroup::OFF;
            $row->name = isset($parsed['group_name']) ? $parsed['group_name'] : $jid;
            $row->save();
        }

        return $row;
    }

    public function enable(WhatsAppGroup $group, $mode, User $owner)
    {
        $allowed = [WhatsAppGroup::OFF, WhatsAppGroup::MONITOR, WhatsAppGroup::MENTION, WhatsAppGroup::ACTIVE];
        if (! in_array($mode, $allowed, true)) {
            $mode = WhatsAppGroup::MONITOR;
        }
        $group->enabled = $mode !== WhatsAppGroup::OFF;
        if ($group->enabled && ($group->mode === WhatsAppGroup::OFF || $group->mode === null || $group->mode === '')) {
            $mode = $mode === WhatsAppGroup::OFF ? WhatsAppGroup::MONITOR : $mode;
        }
        $group->mode = $group->enabled ? $mode : WhatsAppGroup::OFF;
        $group->save();
        app(OwnerCommandService::class)->audit($group->id, $owner->id, $group->enabled ? 'group_enabled' : 'group_disabled', $group->mode);

        return $group;
    }

    public function listText()
    {
        $this->discover();
        $rows = WhatsAppGroup::orderBy('name')->get();
        if ($rows->isEmpty()) {
            return 'No WhatsApp groups have been discovered. Monitoring is off until you enable one.';
        }
        $lines = ['Groups:'];
        foreach ($rows as $row) {
            $lines[] = ($row->name ?: $row->group_jid).' — '.($row->enabled ? $row->mode : 'OFF');
        }

        return implode("\n", $lines);
    }

    public function command($text, User $owner)
    {
        $t = strtolower($text);
        $mode = WhatsAppGroup::MONITOR;
        if (strpos($t, 'stop monitoring') !== false) {
            $mode = WhatsAppGroup::OFF;
        } elseif (strpos($t, 'mention only') !== false) {
            $mode = WhatsAppGroup::MENTION;
        } elseif (strpos($t, 'ai active') !== false || strpos($t, 'activate') !== false) {
            $mode = WhatsAppGroup::ACTIVE;
        }
        $name = trim(preg_replace('/\b(monitor|stop monitoring|ai mention only|mention only|ai active|group)\b/i', ' ', $text));
        $name = trim(preg_replace('/\s+/', ' ', $name));
        $group = $this->findByName($name);
        if (! $group) {
            return 'I could not find that group. Send GROUPS to see discovered groups. None are monitored until you enable one.';
        }
        $this->enable($group, $mode, $owner);
        app(OwnerCommandService::class)->audit($group->id, $owner->id, 'mode_changed', $group->mode);

        return ($group->name ?: 'That group').' is now '.$group->mode.'.';
    }

    public function findByName($name)
    {
        if ($name === '') {
            return null;
        }
        $rows = WhatsAppGroup::where('name', 'like', '%'.$name.'%')->get();
        if ($rows->count() !== 1) {
            return null;
        }

        return $rows->first();
    }
}

<?php

namespace App\Services\WhatsApp;

use App\Services\BeyondWasenderService;
use App\WhatsApp\WhatsAppContact;
use App\WhatsApp\WhatsAppGroupParticipant;
use Illuminate\Support\Facades\Schema;

class GroupContactExportService
{
    public function allRows()
    {
        $wasender = app(BeyondWasenderService::class);
        $listed = $wasender->listGroups();
        if (empty($listed['success'])) {
            return [
                'success' => false,
                'error' => isset($listed['error']) ? $listed['error'] : 'Could not list WhatsApp groups.',
                'rows' => [],
            ];
        }
        $groups = isset($listed['groups']) ? $listed['groups'] : [];
        $rows = [];
        foreach ($groups as $group) {
            $jid = isset($group['jid']) ? (string) $group['jid'] : '';
            $name = trim((string) (isset($group['name']) ? $group['name'] : ''));
            if ($name === '') {
                $name = $jid;
            }
            $fetched = $jid !== '' ? $wasender->groupParticipants($jid) : ['success' => false, 'participants' => []];
            $people = isset($fetched['participants']) ? $fetched['participants'] : [];
            if (! $people) {
                $rows[] = [
                    'group' => $name,
                    'phone' => '',
                    'name' => '',
                    'role' => '',
                    'whatsapp_id' => isset($fetched['error']) ? (string) $fetched['error'] : 'No members returned. Reconnect the Wasender session if this group should have contacts.',
                ];
                continue;
            }
            foreach ($people as $person) {
                $phone = isset($person['phone']) ? (string) $person['phone'] : '';
                $display = isset($person['name']) ? (string) $person['name'] : '';
                if ($display === '' && $phone !== '') {
                    $display = $this->knownName($phone);
                }
                $rows[] = [
                    'group' => $name,
                    'phone' => $phone,
                    'name' => $display,
                    'role' => isset($person['role']) ? $person['role'] : 'member',
                    'whatsapp_id' => isset($person['whatsapp_id']) ? $person['whatsapp_id'] : '',
                ];
            }
        }

        return ['success' => true, 'rows' => $rows, 'groups' => count($groups)];
    }

    public function memberships()
    {
        $wasender = app(BeyondWasenderService::class);
        $listed = $wasender->listGroups();
        if (empty($listed['success'])) {
            $saved = $this->readDirectory();
            if ($saved) {
                $rows = [];
                foreach ($saved as $jid => $known) {
                    $name = trim((string) (isset($known['name']) ? $known['name'] : ''));
                    if ($name === '') {
                        continue;
                    }
                    $rows[] = [
                        'name' => $name,
                        'jid' => (string) $jid,
                        'members' => isset($known['members']) ? (int) $known['members'] : null,
                        'known' => true,
                    ];
                }
                usort($rows, function ($a, $b) {
                    return strcasecmp($a['name'], $b['name']);
                });
                if ($rows) {
                    return ['success' => true, 'groups' => $rows];
                }
            }

            return [
                'success' => false,
                'error' => isset($listed['error']) ? $listed['error'] : 'Could not list WhatsApp groups.',
                'groups' => [],
            ];
        }
        $groups = isset($listed['groups']) ? $listed['groups'] : [];
        $saved = $this->readDirectory();
        $rows = [];
        foreach ($groups as $group) {
            $jid = isset($group['jid']) ? (string) $group['jid'] : '';
            $known = isset($saved[$jid]) ? $saved[$jid] : [];
            $name = trim((string) (isset($known['name']) ? $known['name'] : ''));
            if ($name === '') {
                $name = trim((string) (isset($group['name']) ? $group['name'] : ''));
            }
            $count = isset($known['members']) ? $known['members'] : (isset($group['member_count']) ? $group['member_count'] : null);
            $named = $name !== '' && strpos($name, '@g.us') === false;
            $rows[] = [
                'name' => $named ? $name : '',
                'jid' => $jid,
                'members' => $count === null ? null : (int) $count,
                'known' => $named,
            ];
        }
        usort($rows, function ($a, $b) {
            if ($a['known'] !== $b['known']) {
                return $a['known'] ? -1 : 1;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        return ['success' => true, 'groups' => $rows];
    }

    public function namedGroups()
    {
        $rows = [];
        foreach ($this->readDirectory() as $jid => $known) {
            $name = trim((string) (isset($known['name']) ? $known['name'] : ''));
            if ($name === '' || substr((string) $jid, -5) !== '@g.us') {
                continue;
            }
            $rows[] = [
                'jid' => (string) $jid,
                'name' => $name,
                'members' => isset($known['members']) ? (int) $known['members'] : null,
            ];
        }
        usort($rows, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return $rows;
    }

    public function enrich(array $jids)
    {
        $saved = $this->readDirectory();
        $wasender = app(BeyondWasenderService::class);
        $rows = [];
        $fetched = 0;
        $rateLimited = false;
        foreach ($jids as $jid) {
            $jid = trim((string) $jid);
            if ($jid === '' || substr($jid, -5) !== '@g.us') {
                continue;
            }
            if (! empty($saved[$jid]['name'])) {
                $rows[] = [
                    'jid' => $jid,
                    'name' => $saved[$jid]['name'],
                    'members' => isset($saved[$jid]['members']) ? (int) $saved[$jid]['members'] : null,
                ];
                continue;
            }
            if ($fetched >= 8 || $rateLimited) {
                continue;
            }
            $profile = $wasender->groupProfile($jid);
            $fetched++;
            if (! empty($profile['rate_limited'])) {
                $rateLimited = true;
                continue;
            }
            $name = trim((string) (isset($profile['name']) ? $profile['name'] : ''));
            if ($name === '') {
                continue;
            }
            $members = isset($profile['members']) ? (int) $profile['members'] : null;
            $saved[$jid] = ['name' => $name, 'members' => $members];
            $rows[] = ['jid' => $jid, 'name' => $name, 'members' => $members];
        }
        $this->writeDirectory($saved);

        return [
            'success' => true,
            'groups' => $rows,
            'retry_after' => $rateLimited ? 60 : ($fetched >= 8 ? 60 : 0),
        ];
    }

    public function rowsForGroup($jid)
    {
        $jid = trim((string) $jid);
        $profile = app(BeyondWasenderService::class)->groupProfile($jid);
        $name = trim((string) $profile['name']);
        if ($name !== '') {
            $saved = $this->readDirectory();
            $saved[$jid] = [
                'name' => $name,
                'members' => isset($profile['members']) ? (int) $profile['members'] : null,
            ];
            $this->writeDirectory($saved);
        }
        if ($name === '') {
            $name = $jid;
        }
        $people = isset($profile['participants']) ? $profile['participants'] : [];
        if (! $people && empty($profile['success'])) {
            return [
                'success' => false,
                'error' => isset($profile['error']) ? $profile['error'] : 'Could not read this group.',
                'rows' => [],
                'name' => $name,
            ];
        }
        $rows = [];
        foreach ($people as $person) {
            $phone = isset($person['phone']) ? (string) $person['phone'] : '';
            $display = isset($person['name']) ? (string) $person['name'] : '';
            if ($display === '' && $phone !== '') {
                $display = $this->knownName($phone);
            }
            $rows[] = [
                'group' => $name,
                'phone' => $phone,
                'name' => $display,
                'role' => isset($person['role']) ? $person['role'] : 'member',
                'whatsapp_id' => isset($person['whatsapp_id']) ? $person['whatsapp_id'] : '',
            ];
        }

        return ['success' => true, 'rows' => $rows, 'name' => $name];
    }

    protected function directoryPath()
    {
        return storage_path('app/whatsapp-group-directory.json');
    }

    protected function readDirectory()
    {
        $path = $this->directoryPath();
        if (! is_file($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function writeDirectory(array $saved)
    {
        $path = $this->directoryPath();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($path, json_encode($saved));
    }

    protected function knownName($phone)
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return '';
        }
        if (Schema::hasTable('whatsapp_contacts')) {
            $contact = WhatsAppContact::where('normalized_phone', $digits)->first();
            if ($contact && trim((string) $contact->wa_name) !== '') {
                return trim((string) $contact->wa_name);
            }
        }
        if (Schema::hasTable('whatsapp_group_participants')) {
            $saved = WhatsAppGroupParticipant::where('phone', $digits)->whereNotNull('display_name')->first();
            if ($saved && trim((string) $saved->display_name) !== '') {
                return trim((string) $saved->display_name);
            }
        }

        return '';
    }
}

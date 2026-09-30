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
                'uses' => isset($known['uses']) ? (int) $known['uses'] : 0,
            ];
        }
        usort($rows, function ($a, $b) {
            if ($a['uses'] !== $b['uses']) {
                return $b['uses'] - $a['uses'];
            }
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

    public function announcementGroups()
    {
        $summary = $this->memberships();
        $groups = isset($summary['groups']) ? $summary['groups'] : [];
        if ($groups) {
            return $groups;
        }
        $rows = [];
        foreach ($this->namedGroups() as $group) {
            $rows[] = [
                'name' => $group['name'],
                'jid' => $group['jid'],
                'members' => $group['members'],
                'known' => true,
            ];
        }

        return $rows;
    }

    public function enrich(array $jids)
    {
        $saved = $this->readDirectory();
        $rows = [];
        foreach ($jids as $jid) {
            $jid = trim((string) $jid);
            if ($jid === '' || empty($saved[$jid]['name'])) {
                continue;
            }
            $rows[] = [
                'jid' => $jid,
                'name' => $saved[$jid]['name'],
                'members' => isset($saved[$jid]['members']) ? (int) $saved[$jid]['members'] : null,
            ];
        }

        return [
            'success' => true,
            'groups' => $rows,
            'retry_after' => 15,
        ];
    }

    public function rememberUse($jid)
    {
        $jid = trim((string) $jid);
        if ($jid === '') {
            return;
        }
        $saved = $this->readDirectory();
        $row = isset($saved[$jid]) && is_array($saved[$jid]) ? $saved[$jid] : [];
        $row['uses'] = (isset($row['uses']) ? (int) $row['uses'] : 0) + 1;
        $saved[$jid] = $row;
        $this->writeDirectory($saved);
    }

    public function unresolvedJids()
    {
        $listed = app(BeyondWasenderService::class)->listGroups();
        if (empty($listed['success'])) {
            return null;
        }
        $groups = isset($listed['groups']) ? $listed['groups'] : [];
        $saved = $this->readDirectory();
        $pending = [];
        foreach ($groups as $group) {
            $jid = isset($group['jid']) ? (string) $group['jid'] : '';
            if ($jid === '') {
                continue;
            }
            $name = isset($saved[$jid]['name']) ? trim((string) $saved[$jid]['name']) : '';
            if ($name === '') {
                $pending[] = $jid;
            }
        }

        return $pending;
    }

    public function resolveNext($limit = 5)
    {
        $pending = $this->unresolvedJids();
        if ($pending === null) {
            return 1;
        }
        if (! $pending) {
            return 0;
        }
        $saved = $this->readDirectory();
        $now = time();
        $ready = [];
        foreach ($pending as $jid) {
            $attempted = isset($saved[$jid]['attempted_at']) ? (int) $saved[$jid]['attempted_at'] : 0;
            if ($now - $attempted >= 180) {
                $ready[] = $jid;
            }
        }
        if (! $ready) {
            return count($pending);
        }
        $wasender = app(BeyondWasenderService::class);
        $done = 0;
        foreach (array_slice($ready, 0, max(1, (int) $limit)) as $jid) {
            $row = isset($saved[$jid]) && is_array($saved[$jid]) ? $saved[$jid] : [];
            $row['attempted_at'] = $now;
            $profile = $wasender->groupProfile($jid);
            if (! empty($profile['rate_limited'])) {
                $saved[$jid] = $row;
                $this->writeDirectory($saved);
                break;
            }
            $name = trim((string) (isset($profile['name']) ? $profile['name'] : ''));
            if ($name !== '') {
                $row['name'] = $name;
                $row['members'] = isset($profile['members']) ? (int) $profile['members'] : null;
                $people = isset($profile['participants']) ? $profile['participants'] : [];
                $contacts = [];
                foreach ($people as $person) {
                    $phone = trim((string) (isset($person['phone']) ? $person['phone'] : ''));
                    if ($phone === '') {
                        continue;
                    }
                    $display = trim((string) (isset($person['name']) ? $person['name'] : ''));
                    if ($display === '') {
                        $display = $this->knownName($phone);
                    }
                    $contacts[] = [
                        'phone' => $phone,
                        'name' => $display,
                        'role' => isset($person['role']) ? $person['role'] : 'member',
                    ];
                }
                if ($contacts) {
                    $row['members'] = count($contacts);
                    $this->writeMembers($jid, $contacts);
                }
                $done++;
            }
            $saved[$jid] = $row;
        }
        $this->writeDirectory($saved);

        return count($this->unresolvedJids() ?: []);
    }

    public function scheduleResolve()
    {
        $pending = $this->unresolvedJids();
        if ($pending === []) {
            return;
        }
        if (! \Illuminate\Support\Facades\Cache::add('wa_group_resolve', 1, 90)) {
            return;
        }
        \App\Jobs\ResolveWhatsAppGroupsJob::dispatch()
            ->onConnection('database')
            ->onQueue('whatsapp');
    }

    public function rowsForGroup($jid)
    {
        $jid = trim((string) $jid);
        $cached = $this->readMembers($jid);
        $saved = $this->readDirectory();
        $savedName = isset($saved[$jid]['name']) ? trim((string) $saved[$jid]['name']) : '';
        if ($cached && $savedName !== '') {
            $this->rememberUse($jid);
            $rows = [];
            foreach ($cached as $person) {
                $rows[] = [
                    'group' => $savedName,
                    'phone' => isset($person['phone']) ? $person['phone'] : '',
                    'name' => isset($person['name']) ? $person['name'] : '',
                    'role' => isset($person['role']) ? $person['role'] : 'member',
                    'whatsapp_id' => '',
                ];
            }
            $named = $this->withRegisteredNames($rows);
            if ($this->namesChanged($cached, $named)) {
                $this->writeMembers($jid, $named);
            }

            return ['success' => true, 'rows' => $named, 'name' => $savedName];
        }
        $profile = app(BeyondWasenderService::class)->groupProfile($jid);
        $name = trim((string) $profile['name']);
        if ($name !== '') {
            $saved = $this->readDirectory();
            $existing = isset($saved[$jid]) && is_array($saved[$jid]) ? $saved[$jid] : [];
            $existing['name'] = $name;
            $existing['members'] = isset($profile['members']) ? (int) $profile['members'] : null;
            $saved[$jid] = $existing;
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
        $rows = $this->withRegisteredNames($rows);
        if ($rows && $name !== '' && strpos($name, '@g.us') === false) {
            $this->writeMembers($jid, $rows);
            $this->rememberUse($jid);
        }

        return ['success' => true, 'rows' => $rows, 'name' => $name];
    }

    /**
     * Prefer the name a person saved on their own WhatsApp profile.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function withRegisteredNames(array $rows)
    {
        $map = $this->profileNames();
        foreach ($rows as $i => $row) {
            $phone = isset($row['phone']) ? (string) $row['phone'] : '';
            $registered = $this->registeredWhatsAppName($phone, $map);
            if ($registered !== '') {
                $rows[$i]['name'] = $registered;
                continue;
            }
            $current = trim((string) (isset($row['name']) ? $row['name'] : ''));
            $digits = preg_replace('/\D+/', '', $phone);
            if ($current === '' || $current === $digits || preg_match('/^\d+$/', $current)) {
                $known = $this->knownName($phone);
                $rows[$i]['name'] = ($known !== '' && ! preg_match('/^\d+$/', $known)) ? $known : '';
            }
        }

        return $rows;
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

    protected function membersPath($jid)
    {
        return storage_path('app/whatsapp-group-members/'.md5($jid).'.json');
    }

    protected function readMembers($jid)
    {
        $path = $this->membersPath($jid);
        if (! is_file($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function writeMembers($jid, array $contacts)
    {
        $path = $this->membersPath($jid);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($path, json_encode(array_values($contacts)));
    }

    protected function namesChanged(array $before, array $after)
    {
        foreach ($after as $i => $row) {
            $previous = isset($before[$i]['name']) ? trim((string) $before[$i]['name']) : '';
            $next = isset($row['name']) ? trim((string) $row['name']) : '';
            if ($next !== '' && $next !== $previous) {
                return true;
            }
        }

        return false;
    }

    protected function profileNames()
    {
        $path = storage_path('app/whatsapp-profile-names.json');
        $cached = [];
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) {
                $cached = $decoded;
            }
            if ($cached && (time() - filemtime($path)) < 12 * 3600) {
                return $cached;
            }
        }
        $listed = app(BeyondWasenderService::class)->listContacts();
        if (empty($listed['success']) || empty($listed['contacts'])) {
            return $cached;
        }
        $map = [];
        foreach ($listed['contacts'] as $contact) {
            $digits = preg_replace('/\D+/', '', (string) (isset($contact['phone']) ? $contact['phone'] : ''));
            $label = trim((string) (isset($contact['wa_name']) ? $contact['wa_name'] : ''));
            if ($label === '') {
                $label = trim((string) (isset($contact['name']) ? $contact['name'] : ''));
            }
            if ($digits === '' || $label === '' || preg_match('/^\d+$/', $label) || strcasecmp($label, $digits) === 0) {
                continue;
            }
            $map[$digits] = $label;
            if (strlen($digits) > 9) {
                $map[substr($digits, -9)] = $label;
            }
        }
        if ($map) {
            $dir = dirname($path);
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            file_put_contents($path, json_encode($map));

            return $map;
        }

        return $cached;
    }

    protected function registeredWhatsAppName($phone, array $map)
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return '';
        }
        if (isset($map[$digits]) && ! preg_match('/^\d+$/', (string) $map[$digits])) {
            return (string) $map[$digits];
        }
        $tail = substr($digits, -9);
        if ($tail !== '' && isset($map[$tail]) && ! preg_match('/^\d+$/', (string) $map[$tail])) {
            return (string) $map[$tail];
        }

        return '';
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

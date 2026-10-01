<?php

namespace App\Services\WhatsApp;

use App\Services\BeyondWasenderService;
use App\WhatsApp\WhatsAppContact;
use App\WhatsApp\WhatsAppGroupParticipant;
use Illuminate\Support\Facades\Schema;

class GroupContactExportService
{
    protected $othersCache = null;

    protected $contactList = null;

    protected $contactNames = null;

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
        $seen = [];
        $rows = [];
        $directoryChanged = false;
        foreach ($groups as $group) {
            $jid = isset($group['jid']) ? (string) $group['jid'] : '';
            $known = isset($saved[$jid]) ? $saved[$jid] : [];
            $name = trim((string) (isset($known['name']) ? $known['name'] : ''));
            if ($name === '') {
                $name = trim((string) (isset($group['name']) ? $group['name'] : ''));
            }
            $count = isset($known['members']) ? $known['members'] : (isset($group['member_count']) ? $group['member_count'] : null);
            $named = $name !== '' && strpos($name, '@g.us') === false;
            if ($jid !== '' && ! isset($saved[$jid])) {
                $saved[$jid] = ['attempted_at' => 0];
                $directoryChanged = true;
            }
            $seen[$jid] = true;
            $rows[] = [
                'name' => $named ? $name : '',
                'jid' => $jid,
                'members' => $count === null ? null : (int) $count,
                'known' => $named,
                'uses' => isset($known['uses']) ? (int) $known['uses'] : 0,
                'last_sent' => isset($known['last_sent_at']) ? (int) $known['last_sent_at'] : 0,
            ];
        }
        foreach ($saved as $jid => $known) {
            $jid = (string) $jid;
            if (isset($seen[$jid]) || substr($jid, -5) !== '@g.us') {
                continue;
            }
            $name = trim((string) (isset($known['name']) ? $known['name'] : ''));
            $named = $name !== '' && strpos($name, '@g.us') === false;
            $rows[] = [
                'name' => $named ? $name : '',
                'jid' => $jid,
                'members' => isset($known['members']) ? (int) $known['members'] : null,
                'known' => $named,
                'uses' => isset($known['uses']) ? (int) $known['uses'] : 0,
                'last_sent' => isset($known['last_sent_at']) ? (int) $known['last_sent_at'] : 0,
            ];
        }
        if ($directoryChanged) {
            $this->writeDirectory($saved);
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
        $this->backfillLastSent();
        $summary = $this->memberships();
        $groups = isset($summary['groups']) ? $summary['groups'] : [];
        if ($groups) {
            usort($groups, function ($a, $b) {
                $left = isset($a['last_sent']) ? (int) $a['last_sent'] : 0;
                $right = isset($b['last_sent']) ? (int) $b['last_sent'] : 0;
                if ($left !== $right) {
                    return $right - $left;
                }
                if ($a['known'] !== $b['known']) {
                    return $a['known'] ? -1 : 1;
                }

                return strcasecmp($a['name'], $b['name']);
            });

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

    public function rememberSent($jid, $at = null)
    {
        $jid = trim((string) $jid);
        if (substr($jid, -5) !== '@g.us') {
            return;
        }
        $at = $at ? (int) $at : time();
        $saved = $this->readDirectory();
        $row = isset($saved[$jid]) && is_array($saved[$jid]) ? $saved[$jid] : [];
        $previous = isset($row['last_sent_at']) ? (int) $row['last_sent_at'] : 0;
        if ($at < $previous) {
            return;
        }
        $row['last_sent_at'] = $at;
        $row['uses'] = (isset($row['uses']) ? (int) $row['uses'] : 0) + 1;
        $saved[$jid] = $row;
        $this->writeDirectory($saved);
    }

    protected function backfillLastSent()
    {
        if (! \Illuminate\Support\Facades\Cache::add('wa_group_sent_backfill', 1, 86400)) {
            return;
        }
        if (! \Illuminate\Support\Facades\Schema::hasTable('wa_announcements')) {
            return;
        }
        $saved = $this->readDirectory();
        $changed = false;
        $rows = \App\WaAnnouncement::query()->orderByDesc('id')->limit(300)->get(['recipients_json', 'created_at', 'updated_at']);
        foreach ($rows as $announcement) {
            $people = json_decode((string) $announcement->recipients_json, true);
            if (! is_array($people)) {
                continue;
            }
            $at = strtotime((string) ($announcement->updated_at ?: $announcement->created_at)) ?: 0;
            if ($at <= 0) {
                continue;
            }
            foreach ($people as $person) {
                if (! is_array($person)) {
                    continue;
                }
                $jid = isset($person['group_jid']) ? trim((string) $person['group_jid']) : '';
                if (substr($jid, -5) !== '@g.us') {
                    continue;
                }
                $previous = isset($saved[$jid]['last_sent_at']) ? (int) $saved[$jid]['last_sent_at'] : 0;
                if ($at <= $previous) {
                    continue;
                }
                $row = isset($saved[$jid]) && is_array($saved[$jid]) ? $saved[$jid] : [];
                $row['last_sent_at'] = $at;
                $saved[$jid] = $row;
                $changed = true;
            }
        }
        if ($changed) {
            $this->writeDirectory($saved);
        }
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

    public function syncMissingMembers($limit = 3)
    {
        $listed = app(BeyondWasenderService::class)->listGroups();
        if (empty($listed['success'])) {
            return 1;
        }
        $saved = $this->readDirectory();
        $missing = [];
        $groups = isset($listed['groups']) ? $listed['groups'] : [];
        foreach ($groups as $group) {
            $jid = isset($group['jid']) ? (string) $group['jid'] : '';
            if ($jid === '' || is_file($this->membersPath($jid))) {
                continue;
            }
            $missing[] = $jid;
        }
        if (! $missing) {
            return 0;
        }
        $now = time();
        $ready = [];
        foreach ($missing as $jid) {
            $attempted = isset($saved[$jid]['members_attempted_at']) ? (int) $saved[$jid]['members_attempted_at'] : 0;
            if ($now - $attempted >= 180) {
                $ready[] = $jid;
            }
        }
        if (! $ready) {
            return count($missing);
        }
        $wasender = app(BeyondWasenderService::class);
        foreach (array_slice($ready, 0, max(1, (int) $limit)) as $jid) {
            $row = isset($saved[$jid]) && is_array($saved[$jid]) ? $saved[$jid] : [];
            $row['members_attempted_at'] = $now;
            $profile = $wasender->groupProfile($jid);
            if (! empty($profile['rate_limited'])) {
                $saved[$jid] = $row;
                $this->writeDirectory($saved);

                return count($missing);
            }
            $name = trim((string) (isset($profile['name']) ? $profile['name'] : ''));
            if ($name !== '' && (empty($row['name']) || strpos((string) $row['name'], '@g.us') !== false)) {
                $row['name'] = $name;
            }
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
            if ($contacts || ! empty($profile['success'])) {
                if ($contacts) {
                    $row['members'] = count($contacts);
                    $this->writeMembers($jid, $contacts);
                }
            }
            $saved[$jid] = $row;
        }
        $this->writeDirectory($saved);
        $left = 0;
        foreach ($missing as $jid) {
            if (! is_file($this->membersPath($jid))) {
                $left++;
            }
        }

        return $left;
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
        $map = $this->profileNames(false);
        $others = $this->namesSavedByOthers();
        foreach ($rows as $i => $row) {
            $phone = isset($row['phone']) ? (string) $row['phone'] : '';
            $display = $this->savedDisplayName($phone);
            if ($display !== '') {
                $rows[$i]['name'] = $display;
                continue;
            }
            $current = trim((string) (isset($row['name']) ? $row['name'] : ''));
            if ($this->isPersonName($current, $phone)) {
                continue;
            }
            $registered = $this->registeredWhatsAppName($phone, $map);
            if ($this->isPersonName($registered, $phone)) {
                $rows[$i]['name'] = $registered;
                continue;
            }
            $digits = preg_replace('/\D+/', '', $phone);
            $tail = $digits !== '' ? substr($digits, -9) : '';
            if ($tail !== '' && isset($others[$tail]) && $this->isPersonName($others[$tail], $phone)) {
                $rows[$i]['name'] = $others[$tail];
                continue;
            }
            $known = $this->knownName($phone);
            if ($this->isPersonName($known, $phone)) {
                $rows[$i]['name'] = $known;
            }
        }

        return $rows;
    }

    public function scheduleContactNames($jid)
    {
        $jid = trim((string) $jid);
        if (substr($jid, -5) !== '@g.us') {
            return;
        }
        if (! \Illuminate\Support\Facades\Cache::add('wa_contact_names_'.$jid, 1, 20)) {
            return;
        }
        \App\Jobs\ResolveWhatsAppContactNamesJob::dispatch($jid)
            ->onConnection('database')
            ->onQueue('whatsapp');
    }

    public function resolveContactNames($jid = null, $limit = 12)
    {
        $jid = trim((string) $jid);
        $files = [];
        if (substr($jid, -5) === '@g.us') {
            $path = $this->membersPath($jid);
            if (is_file($path)) {
                $files[$jid] = $path;
            }
        } else {
            foreach ($this->readDirectory() as $id => $row) {
                if (substr((string) $id, -5) !== '@g.us') {
                    continue;
                }
                $path = $this->membersPath($id);
                if (is_file($path)) {
                    $files[(string) $id] = $path;
                }
            }
        }
        $this->registerResolvedContacts();
        $others = $this->savedNames();
        $map = $this->profileNames(false);
        $used = 0;
        $remaining = 0;
        foreach ($files as $groupJid => $path) {
            $people = json_decode((string) file_get_contents($path), true);
            if (! is_array($people)) {
                continue;
            }
            $dirty = false;
            foreach ($people as $i => $person) {
                if (! is_array($person)) {
                    continue;
                }
                $phone = isset($person['phone']) ? (string) $person['phone'] : '';
                $current = trim((string) (isset($person['name']) ? $person['name'] : ''));
                $display = $this->savedDisplayName($phone);
                if ($display !== '') {
                    if ($current !== $display) {
                        $people[$i]['name'] = $display;
                        $people[$i]['name_edited'] = 1;
                        $dirty = true;
                    }
                    continue;
                }
                if ($this->isPersonName($current, $phone)) {
                    $this->rememberContact($phone, $current);
                    continue;
                }
                $known = $this->knownName($phone);
                if ($this->isPersonName($known, $phone)) {
                    $people[$i]['name'] = $known;
                    unset($people[$i]['name_checked'], $people[$i]['name_attempts']);
                    $dirty = true;
                    continue;
                }
                $checked = isset($person['name_checked']) ? (int) $person['name_checked'] : 0;
                if ($checked && (time() - $checked) < 30 * 60) {
                    continue;
                }
                if ($used >= (int) $limit) {
                    $remaining++;
                    continue;
                }
                $used++;
                $resolved = $this->resolvePersonName($phone, $map, $others);
                if ($resolved === null) {
                    $attempts = isset($person['name_attempts']) ? (int) $person['name_attempts'] : 0;
                    $attempts++;
                    $people[$i]['name_attempts'] = $attempts;
                    if ($attempts >= 4) {
                        $people[$i]['name_checked'] = time();
                        $people[$i]['name_attempts'] = 0;
                    }
                    $remaining++;
                    $dirty = true;
                    continue;
                }
                unset($people[$i]['name_attempts']);
                if ($resolved !== '') {
                    $people[$i]['name'] = $resolved;
                    $this->rememberContact($phone, $resolved);
                    unset($people[$i]['name_checked']);
                    $digits = preg_replace('/\D+/', '', $phone);
                    if ($digits !== '') {
                        $map[$digits] = $resolved;
                        if (strlen($digits) > 9) {
                            $map[substr($digits, -9)] = $resolved;
                        }
                        $others[substr($digits, -9)] = $resolved;
                    }
                    $this->othersCache = $others;
                } else {
                    $people[$i]['name_checked'] = time();
                }
                $dirty = true;
                usleep(200000);
            }
            if ($dirty) {
                $this->writeMembers($groupJid, $people);
            }
        }
        if ($map) {
            $this->writeProfileNames($map);
        }
        $this->fillAnnouncementRecipients(null, false);

        return $remaining;
    }

    public function resolveUncheckedGroup($jid, $limit = 20)
    {
        $jid = trim((string) $jid);
        $people = $this->readMembers($jid);
        $dirty = false;
        foreach ($people as $i => $person) {
            if (! is_array($person)) {
                continue;
            }
            $phone = isset($person['phone']) ? (string) $person['phone'] : '';
            $name = isset($person['name']) ? (string) $person['name'] : '';
            if ($this->isPersonName($name, $phone)) {
                continue;
            }
            unset($people[$i]['name_checked'], $people[$i]['name_attempts']);
            $dirty = true;
        }
        if ($dirty) {
            $this->writeMembers($jid, $people);
        }
        $remaining = $this->resolveContactNames($jid, $limit);
        $this->fillAnnouncementRecipients(null, true);

        return $remaining;
    }

    public function localPersonName($phone)
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return '';
        }
        $display = $this->savedDisplayName($digits);
        if ($display !== '') {
            return $display;
        }
        $fromMap = $this->registeredWhatsAppName($phone, $this->profileNames(false));
        if ($this->isPersonName($fromMap, $digits)) {
            return $fromMap;
        }
        $tail = substr($digits, -9);
        $others = $this->savedNames();
        if ($tail !== '' && isset($others[$tail]) && $this->isPersonName($others[$tail], $digits)) {
            return $others[$tail];
        }
        $known = $this->knownName($phone);
        if ($this->isPersonName($known, $digits)) {
            return $known;
        }

        return '';
    }

    public function nameForPhone($phone, $live = false)
    {
        $local = $this->localPersonName($phone);
        if ($local !== '') {
            return $local;
        }
        if (! $live || $this->recentlyChecked($phone)) {
            return '';
        }
        $resolved = $this->resolvePersonName($phone, $this->profileNames(false), $this->savedNames());
        if (! is_string($resolved) || ! $this->isPersonName($resolved, $phone)) {
            return '';
        }
        $this->storeResolvedName($phone, $resolved);

        return $resolved;
    }

    public function fillAnnouncementRecipients($announcement = null, $live = false)
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('wa_announcements')) {
            return 0;
        }
        $query = \App\WaAnnouncement::query()->where('status', '!=', 'deleted')->orderByDesc('updated_at');
        if ($announcement) {
            $query->where('id', $announcement->id);
        } else {
            $query->limit(120);
        }
        $updated = 0;
        $liveBudget = 20;
        foreach ($query->get() as $row) {
            $changed = false;
            foreach (['recipients_json', 'cc_json'] as $field) {
                $people = json_decode((string) $row->{$field}, true);
                if (! is_array($people)) {
                    continue;
                }
                foreach ($people as $i => $person) {
                    if (! is_array($person)) {
                        continue;
                    }
                    $phone = isset($person['phone']) ? (string) $person['phone'] : '';
                    $current = trim((string) (isset($person['name']) ? $person['name'] : ''));
                    if (! empty($person['name_edited']) && $this->isPersonName($current, $phone)) {
                        continue;
                    }
                    if ($this->isPersonName($current, $phone)) {
                        continue;
                    }
                    $label = $this->localPersonName($phone);
                    if ($label === '' && $live && $liveBudget > 0) {
                        $liveBudget--;
                        $label = $this->nameForPhone($phone, true);
                    }
                    if (! $this->isPersonName($label, $phone)) {
                        continue;
                    }
                    $people[$i]['name'] = $label;
                    $people[$i]['wa_name'] = $label;
                    $changed = true;
                }
                if ($changed) {
                    $row->{$field} = json_encode(array_values($people));
                }
            }
            if ($changed) {
                $row->save();
                $updated++;
            }
        }

        return $updated;
    }

    public function contactNameRows($jid)
    {
        $people = $this->readMembers($jid);
        $rows = [];
        foreach ($people as $person) {
            if (! is_array($person)) {
                continue;
            }
            $phone = isset($person['phone']) ? (string) $person['phone'] : '';
            $name = trim((string) (isset($person['name']) ? $person['name'] : ''));
            $checked = isset($person['name_checked']) ? (int) $person['name_checked'] : 0;
            $rows[] = [
                'phone' => $phone,
                'name' => $this->isPersonName($name, $phone) ? $name : '',
                'pending' => ! $this->isPersonName($name, $phone) && ! ($checked && (time() - $checked) < 6 * 3600),
            ];
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

    protected function profileNames($allowRefresh = true)
    {
        $path = storage_path('app/whatsapp-profile-names.json');
        $cached = [];
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) {
                $cached = $decoded;
            }
            if (! $allowRefresh || ($cached && (time() - filemtime($path)) < 12 * 3600)) {
                return $cached;
            }
        }
        if (! $allowRefresh) {
            return $cached;
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
        if ($cached) {
            $map = array_merge($cached, $map);
        }
        if ($map) {
            $this->writeProfileNames($map);

            return $map;
        }

        return $cached;
    }

    protected function writeProfileNames(array $map)
    {
        $path = storage_path('app/whatsapp-profile-names.json');
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($path, json_encode($map));
    }

    protected function profileMisses()
    {
        $path = storage_path('app/whatsapp-profile-misses.json');
        if (! is_file($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function writeProfileMisses(array $misses)
    {
        $path = storage_path('app/whatsapp-profile-misses.json');
        file_put_contents($path, json_encode($misses));
    }

    protected function lookupRegisteredName($phone)
    {
        $digits = ltrim(preg_replace('/\D+/', '', (string) $phone), '0');
        if ($digits === '' || ! app(BeyondWasenderService::class)->isConfigured()) {
            return '';
        }
        $base = rtrim(config('services.whatsapp.wasender_base_url', 'https://wasenderapi.com/api'), '/');
        $ch = curl_init($base.'/contacts/'.rawurlencode($digits));
        curl_setopt_array($ch, [
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '.config('services.whatsapp.wasender_api_key'),
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 20,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($err || $http >= 400 || ! is_string($body)) {
            return null;
        }
        $decoded = json_decode($body, true);
        $data = is_array($decoded) && isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : (is_array($decoded) ? $decoded : []);
        foreach (['notify', 'pushName', 'pushname', 'verifiedName', 'name'] as $key) {
            $value = trim((string) (isset($data[$key]) ? $data[$key] : ''));
            if ($value !== '' && strcasecmp($value, $digits) !== 0 && ! preg_match('/^\d+$/', $value)) {
                return $value;
            }
        }

        return '';
    }

    protected function resolvePersonName($phone, array $map, array $others)
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        $fromMap = $this->registeredWhatsAppName($phone, $map);
        if ($this->isPersonName($fromMap, $digits)) {
            return $fromMap;
        }
        $record = $this->lookupContactRecord($digits);
        if (is_array($record)) {
            if ($this->isPersonName($record['profile'], $digits)) {
                return $record['profile'];
            }
            if ($this->isPersonName($record['book'], $digits)) {
                return $record['book'];
            }
        }
        $tail = $digits !== '' ? substr($digits, -9) : '';
        if ($tail !== '' && isset($others[$tail]) && $this->isPersonName($others[$tail], $digits)) {
            return $others[$tail];
        }
        $known = $this->knownName($phone);
        if ($this->isPersonName($known, $digits)) {
            return $known;
        }
        $savedOnPhone = $this->uniqueAddressBookName($digits);
        if ($this->isPersonName($savedOnPhone, $digits)) {
            return $savedOnPhone;
        }
        if (! $this->isCameroon($digits)) {
            return $record === null ? null : '';
        }
        try {
            $hit = app(\App\Services\MobileMoneyHolderService::class)->lookup($digits);
        } catch (\Throwable $e) {
            return null;
        }
        $name = isset($hit['name']) ? trim((string) $hit['name']) : '';
        if ($this->isPersonName($name, $digits)) {
            return $name;
        }

        return $record === null ? null : '';
    }

    protected function recentlyChecked($phone)
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        $tail = strlen($digits) >= 8 ? substr($digits, -9) : '';
        if ($digits === '') {
            return false;
        }
        $paths = glob(storage_path('app/whatsapp-group-members/*.json'));
        if (! is_array($paths)) {
            return false;
        }
        $saw = false;
        foreach ($paths as $path) {
            $people = json_decode((string) file_get_contents($path), true);
            if (! is_array($people)) {
                continue;
            }
            foreach ($people as $person) {
                if (! is_array($person)) {
                    continue;
                }
                $rowPhone = preg_replace('/\D+/', '', (string) (isset($person['phone']) ? $person['phone'] : ''));
                if ($rowPhone !== $digits && ($tail === '' || substr($rowPhone, -9) !== $tail)) {
                    continue;
                }
                $saw = true;
                $checked = isset($person['name_checked']) ? (int) $person['name_checked'] : 0;
                if (! $checked || (time() - $checked) >= 6 * 3600) {
                    return false;
                }
            }
        }

        return $saw;
    }

    protected function savedNames()
    {
        if ($this->othersCache === null) {
            $this->othersCache = $this->namesSavedByOthers();
        }

        return $this->othersCache;
    }

    protected function storeResolvedName($phone, $name)
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        $name = trim((string) $name);
        if ($digits === '' || ! $this->isPersonName($name, $digits)) {
            return;
        }
        $tail = substr($digits, -9);
        $paths = glob(storage_path('app/whatsapp-group-members/*.json'));
        if (is_array($paths)) {
            foreach ($paths as $path) {
                $people = json_decode((string) file_get_contents($path), true);
                if (! is_array($people)) {
                    continue;
                }
                $dirty = false;
                foreach ($people as $i => $person) {
                    if (! is_array($person)) {
                        continue;
                    }
                    $rowPhone = preg_replace('/\D+/', '', (string) (isset($person['phone']) ? $person['phone'] : ''));
                    if ($rowPhone !== $digits && ($tail === '' || substr($rowPhone, -9) !== $tail)) {
                        continue;
                    }
                    if ($this->isPersonName(isset($person['name']) ? $person['name'] : '', $rowPhone)) {
                        continue;
                    }
                    $people[$i]['name'] = $name;
                    unset($people[$i]['name_checked'], $people[$i]['name_attempts']);
                    $dirty = true;
                }
                if ($dirty) {
                    file_put_contents($path, json_encode(array_values($people)));
                }
            }
        }
        $map = $this->profileNames(false);
        $map[$digits] = $name;
        if ($tail !== '') {
            $map[$tail] = $name;
        }
        $this->writeProfileNames($map);
        $this->rememberContact($phone, $name);
        $others = $this->savedNames();
        if ($tail !== '') {
            $others[$tail] = $name;
            $this->othersCache = $others;
        }
    }

    protected function uniqueAddressBookName($digits)
    {
        $digits = preg_replace('/\D+/', '', (string) $digits);
        $tail = strlen($digits) >= 8 ? substr($digits, -8) : '';
        if ($tail === '') {
            return '';
        }
        if ($this->contactList === null) {
            $listed = app(BeyondWasenderService::class)->listContacts();
            $this->contactList = (isset($listed['contacts']) && is_array($listed['contacts'])) ? $listed['contacts'] : [];
        }
        $exact = [];
        $suffix = [];
        foreach ($this->contactList as $contact) {
            if (! is_array($contact)) {
                continue;
            }
            $phone = preg_replace('/\D+/', '', (string) (isset($contact['phone']) ? $contact['phone'] : ''));
            $label = trim((string) (isset($contact['wa_name']) ? $contact['wa_name'] : ''));
            if (! $this->isPersonName($label, $phone)) {
                $label = trim((string) (isset($contact['name']) ? $contact['name'] : ''));
            }
            if (! $this->isPersonName($label, $phone)) {
                continue;
            }
            if ($phone === $digits) {
                $exact[$label] = true;
            } elseif (substr($phone, -8) === $tail) {
                $suffix[$label] = true;
            }
        }
        if (count($exact) === 1) {
            return (string) array_keys($exact)[0];
        }
        if (! $exact && count($suffix) === 1) {
            return (string) array_keys($suffix)[0];
        }

        return '';
    }

    protected function lookupContactRecord($phone)
    {
        $digits = ltrim(preg_replace('/\D+/', '', (string) $phone), '0');
        if ($digits === '' || ! app(BeyondWasenderService::class)->isConfigured()) {
            return ['profile' => '', 'book' => ''];
        }
        $base = rtrim(config('services.whatsapp.wasender_base_url', 'https://wasenderapi.com/api'), '/');
        $ch = curl_init($base.'/contacts/'.rawurlencode($digits));
        curl_setopt_array($ch, [
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '.config('services.whatsapp.wasender_api_key'),
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 15,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($err || $http >= 400 || ! is_string($body)) {
            return null;
        }
        $decoded = json_decode($body, true);
        $data = is_array($decoded) && isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : [];
        $profile = '';
        foreach (['notify', 'pushName', 'pushname', 'verifiedName'] as $key) {
            $value = trim((string) (isset($data[$key]) ? $data[$key] : ''));
            if ($this->isPersonName($value, $digits)) {
                $profile = $value;
                break;
            }
        }
        $book = trim((string) (isset($data['name']) ? $data['name'] : ''));

        return [
            'profile' => $profile,
            'book' => $this->isPersonName($book, $digits) ? $book : '',
        ];
    }

    protected function namesSavedByOthers()
    {
        $map = [];
        $paths = glob(storage_path('app/whatsapp-group-members/*.json'));
        if (! is_array($paths)) {
            return $map;
        }
        foreach ($paths as $path) {
            $people = json_decode((string) file_get_contents($path), true);
            if (! is_array($people)) {
                continue;
            }
            foreach ($people as $person) {
                if (! is_array($person)) {
                    continue;
                }
                $phone = preg_replace('/\D+/', '', (string) (isset($person['phone']) ? $person['phone'] : ''));
                $name = trim((string) (isset($person['name']) ? $person['name'] : ''));
                if (! $this->isPersonName($name, $phone)) {
                    continue;
                }
                $tail = substr($phone, -9);
                if ($tail !== '' && ! isset($map[$tail])) {
                    $map[$tail] = $name;
                }
            }
        }

        return $map;
    }

    protected function isPersonName($name, $phone = '')
    {
        $name = trim((string) $name);
        if ($name === '' || preg_match('/^\d+$/', $name) || preg_match('/^\+?\d{8,}$/', $name)) {
            return false;
        }
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits !== '' && strcasecmp($name, $digits) === 0) {
            return false;
        }
        $upper = strtoupper($name);

        return ! in_array($upper, ['N/A', 'NA', 'NAN', 'NULL', 'NONE', 'NO WHATSAPP NAME', 'NO NAME'], true);
    }

    protected function isCameroon($digits)
    {
        $digits = preg_replace('/\D+/', '', (string) $digits);
        if (strpos($digits, '237') === 0 && strlen($digits) >= 11) {
            return true;
        }

        return strlen($digits) === 9 && isset($digits[0]) && $digits[0] === '6';
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

    public function registerResolvedContacts($force = false)
    {
        if (! Schema::hasTable('whatsapp_contacts')) {
            return 0;
        }
        if (! $force && ! \Illuminate\Support\Facades\Cache::add('wa_contacts_registered', 1, 6 * 3600)) {
            return 0;
        }
        $saved = 0;
        $paths = glob(storage_path('app/whatsapp-group-members/*.json'));
        if (is_array($paths)) {
            foreach ($paths as $path) {
                $people = json_decode((string) file_get_contents($path), true);
                if (! is_array($people)) {
                    continue;
                }
                foreach ($people as $person) {
                    if (! is_array($person)) {
                        continue;
                    }
                    $phone = isset($person['phone']) ? (string) $person['phone'] : '';
                    $name = isset($person['name']) ? (string) $person['name'] : '';
                    if ($this->rememberContact($phone, $name)) {
                        $saved++;
                    }
                }
            }
        }
        foreach ($this->profileNames(false) as $phone => $name) {
            if ($this->rememberContact($phone, $name)) {
                $saved++;
            }
        }

        return $saved;
    }

    public function savedDisplayName($phone)
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return '';
        }
        $saved = $this->readDisplayEdits();
        if (isset($saved[$digits]) && $this->isPersonName($saved[$digits], $digits)) {
            return $saved[$digits];
        }
        $tail = substr($digits, -9);
        if ($tail !== '' && isset($saved[$tail]) && $this->isPersonName($saved[$tail], $digits)) {
            return $saved[$tail];
        }

        return '';
    }

    public function saveDisplayName($jid, $phone, $name)
    {
        $jid = trim((string) $jid);
        $digits = preg_replace('/\D+/', '', (string) $phone);
        $name = trim((string) $name);
        if (substr($jid, -5) !== '@g.us') {
            throw new \InvalidArgumentException('Choose a WhatsApp group.');
        }
        if ($digits === '' || ! $this->isPersonName($name, $digits)) {
            throw new \InvalidArgumentException('Enter the name this number should show. A phone number cannot be the display name.');
        }
        $people = $this->readMembers($jid);
        $found = false;
        foreach ($people as $i => $person) {
            if (! is_array($person)) {
                continue;
            }
            $rowPhone = preg_replace('/\D+/', '', (string) (isset($person['phone']) ? $person['phone'] : ''));
            $tail = substr($digits, -9);
            if ($rowPhone !== $digits && ($tail === '' || substr($rowPhone, -9) !== $tail)) {
                continue;
            }
            $people[$i]['name'] = $name;
            $people[$i]['name_edited'] = 1;
            unset($people[$i]['name_checked'], $people[$i]['name_attempts']);
            $found = true;
        }
        if (! $found) {
            throw new \InvalidArgumentException('That number is not in this group.');
        }
        $this->writeMembers($jid, $people);
        $this->storeDisplayEdit($digits, $name);
        $this->applyDisplayToMemberFiles($digits, $name);
        $this->rememberContact($digits, $name, true);
        $this->applyDisplayToAnnouncements($digits, $name);

        return $name;
    }

    public function rememberContact($phone, $name, $force = false)
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        $name = trim((string) $name);
        if ($digits === '' || ! $this->isPersonName($name, $digits) || ! Schema::hasTable('whatsapp_contacts')) {
            return false;
        }
        $map = $this->contactNameMap();
        if (! $force && isset($map[$digits]) && $this->isPersonName($map[$digits], $digits)) {
            return false;
        }
        $contact = WhatsAppContact::where('normalized_phone', $digits)->first();
        if (! $contact) {
            $contact = new WhatsAppContact();
            $contact->normalized_phone = $digits;
            $contact->display_phone = '+'.$digits;
        }
        $existing = trim((string) $contact->wa_name);
        if (! $force && $this->isPersonName($existing, $digits)) {
            $this->contactNames[$digits] = $existing;
            if (strlen($digits) > 9) {
                $this->contactNames[substr($digits, -9)] = $existing;
            }

            return false;
        }
        $contact->wa_name = $name;
        if (trim((string) $contact->display_phone) === '') {
            $contact->display_phone = '+'.$digits;
        }
        $contact->save();
        $this->contactNames[$digits] = $name;
        if (strlen($digits) > 9) {
            $this->contactNames[substr($digits, -9)] = $name;
        }

        return true;
    }

    protected function contactNameMap()
    {
        if ($this->contactNames !== null) {
            return $this->contactNames;
        }
        $this->contactNames = [];
        if (! Schema::hasTable('whatsapp_contacts')) {
            return $this->contactNames;
        }
        $rows = WhatsAppContact::query()->get(['normalized_phone', 'wa_name']);
        foreach ($rows as $row) {
            $digits = preg_replace('/\D+/', '', (string) $row->normalized_phone);
            $name = trim((string) $row->wa_name);
            if ($digits === '' || ! $this->isPersonName($name, $digits)) {
                continue;
            }
            $this->contactNames[$digits] = $name;
            if (strlen($digits) > 9) {
                $this->contactNames[substr($digits, -9)] = $name;
            }
        }

        return $this->contactNames;
    }

    protected function knownName($phone)
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return '';
        }
        $map = $this->contactNameMap();
        if (isset($map[$digits]) && $this->isPersonName($map[$digits], $digits)) {
            return $map[$digits];
        }
        $tail = substr($digits, -9);
        if ($tail !== '' && isset($map[$tail]) && $this->isPersonName($map[$tail], $digits)) {
            return $map[$tail];
        }
        if (Schema::hasTable('whatsapp_group_participants')) {
            $saved = WhatsAppGroupParticipant::where('phone', $digits)->whereNotNull('display_name')->first();
            if ($saved && $this->isPersonName($saved->display_name, $digits)) {
                $name = trim((string) $saved->display_name);
                $this->rememberContact($digits, $name);

                return $name;
            }
        }

        return '';
    }

    protected function displayEditsPath()
    {
        return storage_path('app/whatsapp-display-names.json');
    }

    protected function readDisplayEdits()
    {
        $path = $this->displayEditsPath();
        if (! is_file($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function storeDisplayEdit($digits, $name)
    {
        $saved = $this->readDisplayEdits();
        $saved[$digits] = $name;
        if (strlen($digits) > 9) {
            $saved[substr($digits, -9)] = $name;
        }
        $path = $this->displayEditsPath();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($path, json_encode($saved));
    }

    protected function applyDisplayToMemberFiles($digits, $name)
    {
        $tail = substr($digits, -9);
        $paths = glob(storage_path('app/whatsapp-group-members/*.json'));
        if (! is_array($paths)) {
            return;
        }
        foreach ($paths as $path) {
            $people = json_decode((string) file_get_contents($path), true);
            if (! is_array($people)) {
                continue;
            }
            $dirty = false;
            foreach ($people as $i => $person) {
                if (! is_array($person)) {
                    continue;
                }
                $rowPhone = preg_replace('/\D+/', '', (string) (isset($person['phone']) ? $person['phone'] : ''));
                if ($rowPhone !== $digits && ($tail === '' || substr($rowPhone, -9) !== $tail)) {
                    continue;
                }
                $people[$i]['name'] = $name;
                $people[$i]['name_edited'] = 1;
                unset($people[$i]['name_checked'], $people[$i]['name_attempts']);
                $dirty = true;
            }
            if ($dirty) {
                file_put_contents($path, json_encode(array_values($people)));
            }
        }
    }

    protected function applyDisplayToAnnouncements($digits, $name)
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('wa_announcements')) {
            return;
        }
        $tail = substr($digits, -9);
        $rows = \App\WaAnnouncement::query()->where('status', '!=', 'deleted')->orderByDesc('id')->limit(200)->get();
        foreach ($rows as $row) {
            $changed = false;
            foreach (['recipients_json', 'cc_json'] as $field) {
                $people = json_decode((string) $row->{$field}, true);
                if (! is_array($people)) {
                    continue;
                }
                foreach ($people as $i => $person) {
                    if (! is_array($person)) {
                        continue;
                    }
                    $rowPhone = preg_replace('/\D+/', '', (string) (isset($person['phone']) ? $person['phone'] : ''));
                    if ($rowPhone !== $digits && ($tail === '' || substr($rowPhone, -9) !== $tail)) {
                        continue;
                    }
                    $people[$i]['name'] = $name;
                    $people[$i]['wa_name'] = $name;
                    $people[$i]['name_edited'] = true;
                    $changed = true;
                }
                if ($changed) {
                    $row->{$field} = json_encode(array_values($people));
                }
            }
            if ($changed) {
                $row->save();
            }
        }
    }
}

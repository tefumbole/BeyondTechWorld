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

<?php

namespace App\Services\Cloud;

use App\Cloud\CloudTenantType;
use App\Cloud\CloudWhatsAppConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Links a customer company's own WhatsApp by QR code.
 * The session belongs to that company. It never uses BeyondTechWorld's Wasender number.
 */
class CloudLocalWhatsAppLink
{
    public function applies()
    {
        $tenant = $this->tenant();

        return $tenant && $tenant->type === CloudTenantType::CUSTOMER;
    }

    public function panel()
    {
        if (! $this->applies()) {
            return null;
        }
        $state = $this->state(false);

        return [
            'status' => $state['status'],
            'phone' => $state['phone'],
            'connected' => $state['status'] === 'CONNECTED',
            'awaiting' => $state['status'] === 'AWAITING_QR',
            'label' => $this->label($state['status']),
        ];
    }

    public function sessionStatus()
    {
        if (! $this->applies()) {
            return null;
        }
        $state = $this->state(false);
        $connected = $state['status'] === 'CONNECTED';

        return [
            'connected' => $connected,
            'status' => $connected ? 'CONNECTED' : 'NOT_CONNECTED',
            'session_name' => $connected && $state['phone'] ? $state['phone'] : null,
            'configured' => $connected,
            'error' => $connected ? null : 'WhatsApp is not connected for this company.',
        ];
    }

    public function groups()
    {
        if (! $this->applies()) {
            return null;
        }
        $state = $this->state(false);
        if ($state['status'] !== 'CONNECTED') {
            return [
                'success' => false,
                'local' => true,
                'error' => 'WhatsApp is not connected for this company.',
                'groups' => [],
            ];
        }
        $payload = $this->request('GET', '/sessions/'.$this->tenantId().'/groups', 25);
        $groups = [];
        foreach ((array) (isset($payload['groups']) ? $payload['groups'] : []) as $group) {
            if (! is_array($group)) {
                continue;
            }
            $jid = isset($group['jid']) ? (string) $group['jid'] : '';
            $name = isset($group['name']) ? trim((string) $group['name']) : '';
            if (substr($jid, -5) !== '@g.us' || $name === '') {
                continue;
            }
            $groups[] = [
                'name' => $name,
                'jid' => $jid,
                'members' => isset($group['members']) ? (int) $group['members'] : 0,
                'known' => true,
            ];
        }

        return [
            'success' => ! empty($payload['success']),
            'local' => true,
            'error' => empty($payload['success']) ? (isset($payload['error']) ? $payload['error'] : 'Could not load groups.') : null,
            'groups' => $groups,
        ];
    }

    public function groupRows($jid)
    {
        if (! $this->applies()) {
            return null;
        }
        $jid = trim((string) $jid);
        $state = $this->state(false);
        if ($state['status'] !== 'CONNECTED') {
            return [
                'success' => false,
                'error' => 'WhatsApp is not connected for this company.',
                'rows' => [],
                'name' => 'Group',
            ];
        }
        $payload = $this->request('GET', '/sessions/'.$this->tenantId().'/group?jid='.rawurlencode($jid), 25);
        $name = isset($payload['name']) ? trim((string) $payload['name']) : '';
        if ($name === '') {
            $name = 'Group';
        }
        if (empty($payload['success'])) {
            return [
                'success' => false,
                'error' => isset($payload['error']) ? $payload['error'] : 'Could not read this group.',
                'rows' => [],
                'name' => $name,
            ];
        }
        $rows = [];
        foreach ((array) (isset($payload['participants']) ? $payload['participants'] : []) as $person) {
            if (! is_array($person)) {
                continue;
            }
            $rows[] = [
                'group' => $name,
                'phone' => isset($person['phone']) ? (string) $person['phone'] : '',
                'name' => isset($person['name']) ? (string) $person['name'] : '',
                'role' => isset($person['role']) ? $person['role'] : 'member',
                'excluded' => 0,
                'whatsapp_id' => '',
            ];
        }

        return ['success' => true, 'rows' => $rows, 'name' => $name];
    }

    public function start()
    {
        $tenant = $this->requireCustomer();
        if (! app(CloudModuleAccessService::class)->canWriteCapability($tenant, 'messaging')) {
            throw new \RuntimeException('Messaging is not active for this company.');
        }
        $key = 'cloud-wa-link-starts:'.$tenant->id;
        $attempts = (int) Cache::get($key, 0);
        if ($attempts >= 5) {
            throw new \RuntimeException('Too many WhatsApp connection attempts. Please wait.');
        }
        Cache::put($key, $attempts + 1, now()->addHour());
        $payload = $this->request('POST', '/sessions', 25, ['tenant_id' => (int) $tenant->id]);
        if (! $payload || empty($payload['status'])) {
            throw new \RuntimeException('WhatsApp linking is not ready yet. Try again in a moment.');
        }
        $this->remember($payload);

        return $payload;
    }

    public function disconnect()
    {
        $this->requireCustomer();
        $payload = $this->request('DELETE', '/sessions/'.$this->tenantId(), 12);
        $this->remember([
            'status' => 'DISCONNECTED',
            'phone' => null,
        ]);

        return $payload;
    }

    public function state($withQr)
    {
        $blank = ['status' => 'NOT_CONNECTED', 'phone' => '', 'qr' => null];
        if (! $this->applies()) {
            return $blank;
        }
        $path = '/sessions/'.$this->tenantId();
        if ($withQr) {
            $path .= '?qr=1';
        }
        $payload = $this->request('GET', $path, 4);
        if (! $payload || empty($payload['status'])) {
            return $blank;
        }
        $this->remember($payload);

        return [
            'status' => (string) $payload['status'],
            'phone' => isset($payload['phone']) ? (string) $payload['phone'] : '',
            'qr' => $withQr && isset($payload['qr']) ? $payload['qr'] : null,
        ];
    }

    public function label($status)
    {
        if ($status === 'CONNECTED') {
            return 'Connected';
        }
        if ($status === 'AWAITING_QR') {
            return 'Waiting for the scan';
        }
        if ($status === 'ERROR') {
            return 'The link failed. Show the code again.';
        }

        return 'Not connected';
    }

    protected function remember(array $payload)
    {
        $tenant = $this->tenant();
        if (! $tenant || ! Schema::hasTable('cloud_whatsapp_connections')) {
            return;
        }
        $status = isset($payload['status']) ? (string) $payload['status'] : 'NOT_CONNECTED';
        if (! in_array($status, ['AWAITING_QR', 'CONNECTED', 'DISCONNECTED', 'ERROR'], true)) {
            return;
        }
        $connection = CloudWhatsAppConnection::where('cloud_tenant_id', $tenant->id)->orderBy('id', 'desc')->first();
        if (! $connection) {
            $connection = new CloudWhatsAppConnection();
            $connection->cloud_tenant_id = $tenant->id;
        }
        if ($connection->provider === 'wasender' || $connection->credentials_reference === 'services.whatsapp.wasender_api_key') {
            return;
        }
        $connection->provider = 'local';
        $connection->provider_connection_id = 'local-'.$tenant->id;
        $connection->credentials_reference = 'local';
        $connection->status = $status;
        $connection->display_name = $tenant->system_name ?: $tenant->name;
        if (! empty($payload['phone'])) {
            $connection->phone_number = (string) $payload['phone'];
        }
        if ($status === 'CONNECTED' && ! $connection->connected_at) {
            $connection->connected_at = now();
        }
        $connection->last_health_check_at = now();
        $connection->save();
    }

    protected function requireCustomer()
    {
        $tenant = $this->tenant();
        if (! $tenant || $tenant->type !== CloudTenantType::CUSTOMER) {
            throw new \RuntimeException('WhatsApp linking is only for a company account.');
        }

        return $tenant;
    }

    protected function tenant()
    {
        $context = app(CloudTenantContext::class);

        return $context->has() ? $context->tenant() : null;
    }

    protected function tenantId()
    {
        $tenant = $this->tenant();

        return $tenant ? (int) $tenant->id : 0;
    }

    protected function request($method, $path, $timeout, array $body = null)
    {
        $transport = config('cloud.whatsapp.link_transport');
        if (is_callable($transport)) {
            return call_user_func($transport, $method, $path, $body);
        }
        if (app()->runningUnitTests()) {
            return null;
        }
        $token = $this->token();
        $base = rtrim((string) config('cloud.whatsapp.link_url', 'http://127.0.0.1:3921'), '/');
        $ch = curl_init($base.$path);
        $headers = [
            'Authorization: Bearer '.$token,
            'Accept: application/json',
        ];
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($body !== null) {
            $encoded = json_encode($body);
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
        }
        $raw = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $http < 200 || $http >= 300) {
            return null;
        }
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function token()
    {
        $path = storage_path('app/whatsapp-link/token');
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        if (! is_file($path)) {
            file_put_contents($path, bin2hex(random_bytes(32)));
            chmod($path, 0600);
        }

        return trim((string) file_get_contents($path));
    }
}

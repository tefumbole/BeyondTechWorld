<?php

namespace App\Services\Messaging;

use App\Cloud\CloudTenant;
use App\Jobs\SendSmsAttemptJob;
use App\Services\Cloud\CloudModuleAccessService;
use App\Support\WhatsAppPhone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One logical notification, then channel attempts.
 * WhatsApp sending stays on the existing router. This class records that outcome
 * and sends SMS only through the provider registry.
 */
class MessagingHub
{
    protected $ledger;
    protected $segments;
    protected $providers;

    public function __construct(SmsCreditLedger $ledger, SmsSegmentEstimator $segments, SmsProviderRegistry $providers)
    {
        $this->ledger = $ledger;
        $this->segments = $segments;
        $this->providers = $providers;
    }

    public function notify(CloudTenant $tenant, array $input, array $whatsappOutcome = null)
    {
        $this->assertCanSend($tenant);
        $phone = WhatsAppPhone::normalize($input['phone']);
        $correlation = trim((string) $input['correlation_id']);
        if ($correlation === '') {
            throw new \InvalidArgumentException('A notification needs a correlation id.');
        }
        $existing = DB::table('cloud_messaging_notifications')
            ->where('cloud_tenant_id', $tenant->id)
            ->where('correlation_id', $correlation)
            ->first();
        if ($existing) {
            return $existing;
        }

        $purpose = strtoupper((string) $input['purpose']);
        $preference = strtoupper(isset($input['preference']) ? $input['preference'] : 'AUTO');
        $body = $this->bodyFor($tenant, $input);
        $stored = $purpose === 'OTP' ? 'OTP redacted' : $body;
        $id = DB::table('cloud_messaging_notifications')->insertGetId([
            'cloud_tenant_id' => $tenant->id,
            'correlation_id' => $correlation,
            'purpose' => $purpose,
            'preference' => $preference,
            'recipient' => $phone,
            'normalized_phone' => $phone,
            'status' => 'QUEUED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($preference === 'WHATSAPP' || $preference === 'AUTO') {
            $this->recordWhatsApp($tenant->id, $id, $whatsappOutcome);
        }
        if ($this->smsWanted($preference, $whatsappOutcome)) {
            $this->queueSms($tenant, $id, $phone, $body, $stored, ! empty($input['defer']));
        } else {
            $this->finishNotification($id);
        }

        return DB::table('cloud_messaging_notifications')->where('id', $id)->first();
    }

    public function quotationText($company, $number, $secureLink)
    {
        $link = (string) $secureLink;
        if (preg_match('#/(invoice|quotation)/\d+/?(\?|$)#', $link) && stripos($link, 'token') === false) {
            throw new \RuntimeException('SMS links must use a secure token.');
        }

        return trim($company).': Your quotation '.$number.' is ready. View securely: '.$link;
    }

    public function executeSmsAttempt($attemptId)
    {
        $attempt = DB::table('cloud_messaging_attempts')->where('id', $attemptId)->first();
        if (! $attempt || $attempt->channel !== 'SMS') {
            return;
        }
        if (in_array($attempt->status, ['SENT', 'DELIVERED', 'SENDING', 'FAILED'], true)) {
            return;
        }
        $claimed = DB::table('cloud_messaging_attempts')->where('id', $attemptId)->where('status', 'QUEUED')->update([
            'status' => 'SENDING',
            'updated_at' => now(),
        ]);
        if (! $claimed) {
            return;
        }
        $tenant = CloudTenant::find($attempt->cloud_tenant_id);
        $message = DB::table('cloud_sms_messages')->where('attempt_id', $attemptId)->first();
        if (! $tenant || ! $message) {
            return;
        }
        if (! $this->writable($tenant)) {
            $this->failAttempt($attempt, $message, 'entitlement', 'Messaging is not active for this company.', null);
            return;
        }
        $connection = $this->connection($tenant->id);
        if (! $connection || ! $connection->sending_enabled || $connection->status !== 'ACTIVE') {
            $this->failAttempt($attempt, $message, 'sms_not_configured', 'SMS is not configured for this company.', null);
            return;
        }
        if ($this->blocked($tenant->id, $message, $attempt)) {
            return;
        }
        $sendBody = $message->body;
        if ($message->body === 'OTP redacted') {
            $sendBody = (string) Cache::pull('messaging-otp-body:'.$attemptId);
        }
        $estimate = $this->segments->estimate($sendBody);
        $units = max(1, (int) $estimate['segments']);
        if (! $this->withinDailyLimit($connection, $units) || ! $this->withinValidationCeiling($units)) {
            $this->failAttempt($attempt, $message, 'daily_limit', 'The SMS safety limit was reached.', null);
            return;
        }
        $reference = 'attempt:'.$attempt->id;
        try {
            $this->ledger->reserve($tenant->id, $units, $reference, $attempt->notification_id);
        } catch (InsufficientSmsCredit $e) {
            $this->failAttempt($attempt, $message, 'insufficient_credit', $e->getMessage(), null);
            return;
        }
        $provider = $this->providers->resolve($connection->provider);
        $result = $provider->send([
            'to' => '+'.$message->normalized_recipient,
            'from' => $connection->sender_id,
            'body' => $sendBody,
            'reference' => $reference,
        ]);
        if (empty($result['accepted'])) {
            if (in_array(isset($result['failure_code']) ? $result['failure_code'] : '', ['provider_timeout', 'provider_rejected'], true)) {
                $this->markConnectionHealth($connection->id, 'DEGRADED');
            }
            $this->ledger->release($tenant->id, $units, $reference, $attempt->notification_id);
            $this->failAttempt(
                $attempt,
                $message,
                isset($result['failure_code']) ? $result['failure_code'] : 'send_failed',
                'The SMS provider did not accept the message.',
                isset($result['failure_class']) ? $result['failure_class'] : 'temporary'
            );
            return;
        }
        $this->ledger->markConsumed($tenant->id, $reference, $attempt->notification_id);
        $charge = $this->customerCharge($connection, $units);
        $saved = [
            'status' => 'SENT',
            'provider' => $provider->name(),
            'sender' => $connection->sender_id,
            'provider_message_id' => $result['provider_message_id'],
            'segments' => $units,
            'encoding' => $estimate['encoding'],
            'customer_charge' => $charge['amount'],
            'currency' => $charge['currency'],
            'provider_cost' => isset($result['provider_cost']) ? $result['provider_cost'] : null,
            'sent_at' => now(),
            'raw_provider_status' => isset($result['raw_status']) ? $result['raw_status'] : $result['status'],
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('cloud_sms_messages', 'provider_units')) {
            $saved['provider_units'] = isset($result['provider_units']) ? $result['provider_units'] : null;
        }
        DB::table('cloud_sms_messages')->where('id', $message->id)->update($saved);
        $this->markConnectionHealth($connection->id, 'ACTIVE');
        DB::table('cloud_messaging_attempts')->where('id', $attemptId)->update([
            'status' => 'SENT',
            'provider' => $provider->name(),
            'provider_message_id' => $result['provider_message_id'],
            'updated_at' => now(),
        ]);
        $this->finishNotification($attempt->notification_id);
    }

    public function applyDelivery($provider, $providerMessageId, $status, array $meta = [])
    {
        $message = DB::table('cloud_sms_messages')
            ->where('provider', $provider)
            ->where('provider_message_id', $providerMessageId)
            ->first();
        if (! $message) {
            return false;
        }
        $status = strtoupper((string) $status);
        $raw = isset($meta['raw_status']) ? $meta['raw_status'] : $status;
        if ($status === 'UNKNOWN') {
            DB::table('cloud_sms_messages')->where('id', $message->id)->update([
                'raw_provider_status' => $raw,
                'updated_at' => now(),
            ]);
            return true;
        }
        if ($message->status === $status) {
            return true;
        }
        if ($message->status === 'SENT' && $status === 'DELIVERED') {
            $saved = [
                'status' => 'DELIVERED',
                'raw_provider_status' => $raw,
                'delivered_at' => now(),
                'updated_at' => now(),
            ];
            if (isset($meta['provider_cost'])) {
                $saved['provider_cost'] = $meta['provider_cost'];
            }
            if (Schema::hasColumn('cloud_sms_messages', 'provider_units') && isset($meta['provider_units'])) {
                $saved['provider_units'] = $meta['provider_units'];
            }
            DB::table('cloud_sms_messages')->where('id', $message->id)->update($saved);
            $this->markReceipt($message);
            DB::table('cloud_messaging_attempts')->where('id', $message->attempt_id)->update([
                'status' => 'DELIVERED',
                'updated_at' => now(),
            ]);
            return true;
        }
        if ($message->status === 'SENT' && $status === 'FAILED') {
            DB::table('cloud_sms_messages')->where('id', $message->id)->update([
                'status' => 'FAILED',
                'raw_provider_status' => $status,
                'failure_code' => 'delivery_failed',
                'failed_at' => now(),
                'updated_at' => now(),
            ]);
            return true;
        }

        return true;
    }

    public function usage($tenantId)
    {
        return DB::table('cloud_sms_messages')
            ->where('cloud_tenant_id', $tenantId)
            ->orderByDesc('id')
            ->get()
            ->map(function ($row) {
                $row->masked_recipient = $this->mask($row->normalized_recipient);

                return $row;
            });
    }

    public function mask($phone)
    {
        $phone = (string) $phone;
        if (strlen($phone) < 6) {
            return '****';
        }

        return substr($phone, 0, 4).'****'.substr($phone, -2);
    }

    public function renderTemplate($body, array $variables)
    {
        $allowed = ['customer_name', 'quotation_number', 'amount', 'company_name', 'secure_link'];

        return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', function ($match) use ($variables, $allowed) {
            $key = strtolower($match[1]);
            if (! in_array($key, $allowed, true)) {
                return '';
            }

            return isset($variables[$key]) ? (string) $variables[$key] : '';
        }, (string) $body);
    }

    protected function queueSms(CloudTenant $tenant, $notificationId, $phone, $sendBody, $storedBody, $defer)
    {
        $attemptId = DB::table('cloud_messaging_attempts')->insertGetId([
            'cloud_tenant_id' => $tenant->id,
            'notification_id' => $notificationId,
            'channel' => 'SMS',
            'status' => 'QUEUED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('cloud_sms_messages')->insert([
            'cloud_tenant_id' => $tenant->id,
            'notification_id' => $notificationId,
            'attempt_id' => $attemptId,
            'recipient' => $phone,
            'normalized_recipient' => $phone,
            'provider' => 'pending',
            'body' => $storedBody,
            'status' => 'QUEUED',
            'segments' => max(1, (int) $this->segments->estimate($sendBody)['segments']),
            'queued_at' => now(),
            'currency' => 'XAF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($storedBody === 'OTP redacted') {
            Cache::put('messaging-otp-body:'.$attemptId, $sendBody, 10);
        }
        if (! $defer) {
            SendSmsAttemptJob::dispatch($tenant->id, $attemptId);
        }
    }

    protected function recordWhatsApp($tenantId, $notificationId, array $outcome = null)
    {
        $status = 'SKIPPED';
        $class = null;
        if ($outcome) {
            $status = ! empty($outcome['success']) ? 'SENT' : 'FAILED';
            $class = isset($outcome['failure_class']) ? $outcome['failure_class'] : null;
        }
        DB::table('cloud_messaging_attempts')->insert([
            'cloud_tenant_id' => $tenantId,
            'notification_id' => $notificationId,
            'channel' => 'WHATSAPP',
            'status' => $status,
            'failure_class' => $class,
            'provider' => 'existing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function smsWanted($preference, array $outcome = null)
    {
        if ($preference === 'SMS') {
            return true;
        }
        if ($preference !== 'AUTO' || ! $outcome || ! empty($outcome['success'])) {
            return false;
        }
        $class = isset($outcome['failure_class']) ? $outcome['failure_class'] : '';
        if ($class === 'permanent_recipient') {
            return (bool) config('messaging.sms.fallback_on_permanent_recipient');
        }
        if ($class === 'temporary') {
            return (bool) config('messaging.sms.fallback_on_temporary');
        }

        return false;
    }

    protected function blocked($tenantId, $message, $attempt)
    {
        $notification = DB::table('cloud_messaging_notifications')->where('id', $attempt->notification_id)->first();
        $purpose = $notification ? $notification->purpose : '';
        $pref = DB::table('cloud_messaging_preferences')
            ->where('cloud_tenant_id', $tenantId)
            ->where('normalized_phone', $message->normalized_recipient)
            ->first();
        if ($pref && ! $pref->sms_allowed) {
            $this->failAttempt($attempt, $message, 'sms_not_allowed', 'This recipient does not accept SMS.', null);
            return true;
        }
        $suppressed = DB::table('cloud_sms_suppressions')
            ->where('cloud_tenant_id', $tenantId)
            ->where('normalized_phone', $message->normalized_recipient)
            ->exists();
        if ($purpose === 'MARKETING' && ($suppressed || ! $pref || ! $pref->marketing_consent)) {
            $this->failAttempt($attempt, $message, 'opt_out', 'Marketing SMS is suppressed for this number.', null);
            return true;
        }
        if ($purpose === 'OTP') {
            $key = 'messaging-otp:'.$tenantId.':'.$message->normalized_recipient;
            $count = (int) Cache::get($key, 0) + 1;
            Cache::put($key, $count, 60);
            $limit = (int) config('services.whatsapp.otp_hourly_limit', 5);
            if ($count > $limit) {
                $this->failAttempt($attempt, $message, 'otp_rate_limit', 'OTP SMS rate limit reached.', null);
                return true;
            }
        }

        return false;
    }

    protected function withinDailyLimit($connection, $units)
    {
        if ($connection->daily_segment_limit === null) {
            return true;
        }
        $used = (int) DB::table('cloud_sms_messages')
            ->where('cloud_tenant_id', $connection->cloud_tenant_id)
            ->whereIn('status', ['SENT', 'DELIVERED', 'SENDING'])
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('segments');

        return ($used + $units) <= (int) $connection->daily_segment_limit;
    }

    protected function withinValidationCeiling($units)
    {
        if (config('messaging.sms.driver') !== 'infobip') {
            return true;
        }
        $ceiling = config('messaging.sms.validation_segment_ceiling');
        if ($ceiling === null) {
            return true;
        }
        $used = (int) DB::table('cloud_sms_messages')
            ->where('provider', 'infobip')
            ->whereIn('status', ['SENT', 'DELIVERED', 'SENDING'])
            ->sum('segments');

        return ($used + $units) <= (int) $ceiling;
    }

    protected function markConnectionHealth($connectionId, $status)
    {
        if (! Schema::hasColumn('cloud_sms_connections', 'health_status')) {
            return;
        }
        DB::table('cloud_sms_connections')->where('id', $connectionId)->update([
            'health_status' => $status,
            $status === 'ACTIVE' ? 'last_success_at' : 'last_failure_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function markReceipt($message)
    {
        if (! Schema::hasColumn('cloud_sms_connections', 'last_receipt_at')) {
            return;
        }
        DB::table('cloud_sms_connections')
            ->where('cloud_tenant_id', $message->cloud_tenant_id)
            ->where('provider', $message->provider)
            ->update([
                'last_receipt_at' => now(),
                'updated_at' => now(),
            ]);
    }

    protected function customerCharge($connection, $units)
    {
        $price = DB::table('cloud_sms_prices')
            ->where('active', true)
            ->where('currency', $connection->currency ?: 'XAF')
            ->where('country', $connection->country ?: '')
            ->orderByDesc('id')
            ->first();
        if (! $price) {
            return ['amount' => null, 'currency' => $connection->currency ?: 'XAF'];
        }

        return [
            'amount' => ((float) $price->unit_price + (float) $price->markup) * $units,
            'currency' => $price->currency,
        ];
    }

    protected function bodyFor(CloudTenant $tenant, array $input)
    {
        if (! empty($input['template_id'])) {
            $template = DB::table('cloud_messaging_templates')->where('id', $input['template_id'])->first();
            if (! $template || ($template->cloud_tenant_id !== null && (int) $template->cloud_tenant_id !== (int) $tenant->id)) {
                throw new \RuntimeException('That template is not available to this company.');
            }

            return $this->renderTemplate($template->body, isset($input['variables']) ? $input['variables'] : []);
        }

        return (string) $input['body'];
    }

    protected function assertCanSend(CloudTenant $tenant)
    {
        if (! $this->writable($tenant)) {
            throw new \RuntimeException('Messaging is not active for this company.');
        }
    }

    protected function writable(CloudTenant $tenant)
    {
        return app(CloudModuleAccessService::class)->canWriteCapability($tenant, 'messaging');
    }

    protected function connection($tenantId)
    {
        return DB::table('cloud_sms_connections')->where('cloud_tenant_id', $tenantId)->orderByDesc('id')->first();
    }

    protected function failAttempt($attempt, $message, $code, $reason, $class)
    {
        DB::table('cloud_messaging_attempts')->where('id', $attempt->id)->update([
            'status' => 'FAILED',
            'failure_code' => $code,
            'failure_class' => $class,
            'updated_at' => now(),
        ]);
        DB::table('cloud_sms_messages')->where('id', $message->id)->update([
            'status' => 'FAILED',
            'failure_code' => $code,
            'failure_reason' => $reason,
            'failed_at' => now(),
            'updated_at' => now(),
        ]);
        $this->finishNotification($attempt->notification_id);
    }

    protected function finishNotification($notificationId)
    {
        $open = DB::table('cloud_messaging_attempts')
            ->where('notification_id', $notificationId)
            ->whereIn('status', ['QUEUED', 'SENDING'])
            ->exists();
        if ($open) {
            return;
        }
        $sent = DB::table('cloud_messaging_attempts')
            ->where('notification_id', $notificationId)
            ->whereIn('status', ['SENT', 'DELIVERED'])
            ->exists();
        DB::table('cloud_messaging_notifications')->where('id', $notificationId)->update([
            'status' => $sent ? 'SENT' : 'FAILED',
            'updated_at' => now(),
        ]);
    }
}

<?php

namespace App\Services\Property;

use App\Property\BillPaymentRequest;
use App\Property\MaintenanceRequest;
use App\Property\PropertyUnit;
use App\Property\RentPayment;
use App\Property\Tenancy;
use Illuminate\Support\Facades\DB;

class PropertyWhatsAppService
{
    protected $billing;
    protected $maintenance;
    protected $bills;
    protected $log;

    public function __construct(
        RentBillingService $billing,
        MaintenanceService $maintenance,
        BillPaymentService $bills,
        PropertyActivityLogger $log
    ) {
        $this->billing = $billing;
        $this->maintenance = $maintenance;
        $this->bills = $bills;
        $this->log = $log;
    }

    public function handle($tool, array $params, array $context)
    {
        if (in_array($tool, ['list_supported_bill_types', 'create_bill_payment_request', 'get_bill_payment_request', 'update_bill_payment_details', 'confirm_bill_payment_request', 'get_bill_payment_status', 'request_bill_payment_handover', 'attach_bill_image'], true)) {
            return $this->bill($tool, $params, $context);
        }
        $tenancy = $this->tenancy($context, $params);
        if (isset($tenancy['error'])) {
            return $tenancy['error'];
        }
        $row = $tenancy['tenancy'];
        if ($tool === 'get_my_tenancy') {
            $unit = PropertyUnit::find($row->unit_id);
            $this->log->write('tenancy_lookup', 'tenancy', $row->id, [], isset($context['conversation_id']) ? $context['conversation_id'] : null);

            return $this->ok('Your tenancy is active for '.($unit ? $unit->name : 'your unit').'.', $row);
        }
        if ($tool === 'get_rent_balance') {
            $balance = $this->billing->balance($row);
            $this->log->write('rent_balance', 'tenancy', $row->id, ['amount' => $balance['amount']], isset($context['conversation_id']) ? $context['conversation_id'] : null);

            return $this->ok('Your rent balance is '.number_format($balance['amount'], 0, '.', ',').' '.$balance['currency'].'.', $row);
        }
        if ($tool === 'get_rent_due_date') {
            $balance = $this->billing->balance($row);
            if (! $balance['next']) {
                return $this->ok('There is no open rent due date on this tenancy.', $row);
            }
            $this->log->write('rent_due', 'tenancy', $row->id, ['due' => (string) $balance['next']->due_date], isset($context['conversation_id']) ? $context['conversation_id'] : null);

            return $this->ok('Your rent is due on '.$balance['next']->due_date.'.', $row);
        }
        if ($tool === 'get_rent_payment_history') {
            $payments = RentPayment::where('tenancy_id', $row->id)->orderByDesc('id')->limit(8)->get();
            if (count($payments) === 0) {
                return $this->ok('I could not find a rent payment on this tenancy.', $row);
            }
            $lines = [];
            foreach ($payments as $payment) {
                $lines[] = $payment->paid_on.' — '.number_format($payment->amount, 0, '.', ',').' '.$payment->currency;
            }
            $this->log->write('rent_history', 'tenancy', $row->id, ['count' => count($payments)], isset($context['conversation_id']) ? $context['conversation_id'] : null);

            return $this->ok("Payments on this tenancy:\n".implode("\n", $lines), $row);
        }
        if ($tool === 'record_payment_claim') {
            $this->log->write('rent_payment_claim', 'tenancy', $row->id, ['accepted' => false], isset($context['conversation_id']) ? $context['conversation_id'] : null);

            return $this->ok('A WhatsApp message is not proof of payment. Your rent balance is unchanged until the payment is recorded in the ERP.', $row);
        }
        if ($tool === 'get_maintenance_requests' || $tool === 'get_maintenance_status') {
            return $this->status($row, $params, $context);
        }
        if ($tool === 'create_maintenance_request') {
            return $this->createMaintenance($row, $params, $context);
        }
        if ($tool === 'add_maintenance_attachment') {
            return $this->attach($row, $params, $context);
        }
        if ($tool === 'request_tenant_document') {
            return ['success' => true, 'message' => 'I will check that document against this tenancy.', 'tenant_context' => 'tenant'];
        }

        return ['success' => false, 'error' => 'unknown_tool'];
    }

    public function clarify()
    {
        return [
            'success' => true,
            'needs_choice' => true,
            'tenant_choice_pending' => 1,
            'message' => 'Do you mean your tenant rent balance or another account?',
        ];
    }

    protected function createMaintenance(Tenancy $row, array $params, array $context)
    {
        $text = isset($params['text']) ? $params['text'] : '';
        $opened = $this->maintenance->open($row, $text, [
            'provider_message_id' => isset($params['provider_message_id']) ? $params['provider_message_id'] : null,
            'conversation_id' => isset($context['conversation_id']) ? $context['conversation_id'] : null,
            'suggested_priority' => isset($params['suggested_priority']) ? $params['suggested_priority'] : null,
            'source' => 'whatsapp',
        ]);
        $request = $opened['request'];
        $message = 'Maintenance request '.$request->id.' is '.$request->status.' ('.$request->category.', '.$request->priority.').';
        if (! empty($opened['emergency'])) {
            $message .= ' '.$this->maintenance->emergencyText();
        }

        return [
            'success' => true,
            'message' => $message,
            'maintenance_request_id' => $request->id,
            'maintenance_pending' => 1,
            'tenant_context' => 'tenant',
            'duplicate' => ! empty($opened['duplicate']),
        ];
    }

    protected function attach(Tenancy $row, array $params, array $context)
    {
        $ids = $this->ids($context, $row);
        $requestId = isset($params['maintenance_request_id']) ? (int) $params['maintenance_request_id'] : 0;
        if (isset($params['request_id'])) {
            $requestId = (int) $params['request_id'];
        }
        $request = null;
        if ($requestId > 0) {
            $request = $this->maintenance->owned($requestId, $ids);
            if (! $request) {
                return $this->denied();
            }
        } else {
            $request = MaintenanceRequest::whereIn('tenancy_id', $ids)->orderByDesc('id')->first();
        }
        if (! $request) {
            return ['success' => true, 'message' => 'I do not have an open repair for this account.'];
        }
        $bytes = isset($params['bytes']) ? $params['bytes'] : '';
        $name = isset($params['filename']) ? $params['filename'] : 'photo.jpg';
        $mime = isset($params['mime']) ? $params['mime'] : '';
        $voice = ! empty($params['voice']);
        $saved = $this->maintenance->attach($request, $bytes, $name, $mime, $voice);
        if (empty($saved['ok'])) {
            return ['success' => true, 'message' => 'I could not store that file. Send a photo or voice note in an accepted format.'];
        }

        return [
            'success' => true,
            'message' => 'The file is attached to maintenance request '.$request->id.'.',
            'maintenance_request_id' => $request->id,
            'maintenance_pending' => 1,
            'tenant_context' => 'tenant',
        ];
    }

    protected function status(Tenancy $row, array $params, array $context)
    {
        $ids = $this->ids($context, $row);
        $requestId = 0;
        $text = isset($params['text']) ? (string) $params['text'] : '';
        if (preg_match('/\b(\d{1,8})\b/', $text, $m)) {
            $requestId = (int) $m[1];
        }
        if (! empty($params['maintenance_request_id'])) {
            $requestId = (int) $params['maintenance_request_id'];
        }
        if ($requestId > 0) {
            $request = $this->maintenance->owned($requestId, $ids);
            if (! $request) {
                return $this->denied();
            }
        } else {
            $request = MaintenanceRequest::whereIn('tenancy_id', $ids)->orderByDesc('id')->first();
        }
        if (! $request) {
            return ['success' => true, 'message' => 'I do not have a repair request for this account.', 'tenant_context' => 'tenant'];
        }
        $this->log->write('maintenance_status', 'maintenance', $request->id, ['status' => $request->status], isset($context['conversation_id']) ? $context['conversation_id'] : null);

        return [
            'success' => true,
            'message' => 'Maintenance request '.$request->id.' is '.$request->status.'.',
            'tenant_context' => 'tenant',
        ];
    }

    protected function bill($tool, array $params, array $context)
    {
        if (empty($context['customer_id']) && empty($context['tenancy_id'])) {
            return ['success' => true, 'message' => 'I could not match this number to a customer account.'];
        }
        if ($tool === 'list_supported_bill_types') {
            return ['success' => true, 'message' => 'Supported bills: '.implode(', ', $this->bills->types()).'.', 'types' => $this->bills->types()];
        }
        if ($tool === 'request_bill_payment_handover') {
            return ['success' => true, 'message' => 'A team member will review this bill request.', 'handover' => true];
        }
        if ($tool === 'create_bill_payment_request') {
            $created = $this->bills->create([
                'customer_id' => isset($context['customer_id']) ? $context['customer_id'] : null,
                'tenancy_id' => isset($context['tenancy_id']) ? $context['tenancy_id'] : null,
                'conversation_id' => isset($context['conversation_id']) ? $context['conversation_id'] : null,
                'text' => isset($params['text']) ? $params['text'] : '',
                'provider_message_id' => isset($params['provider_message_id']) ? $params['provider_message_id'] : null,
            ]);
            $request = $created['request'];
            $this->prefillUtility($request);
            if (! empty($params['bytes'])) {
                $this->bills->attach(
                    $request,
                    $params['bytes'],
                    isset($params['filename']) ? $params['filename'] : 'bill.jpg',
                    isset($params['mime']) ? $params['mime'] : 'image/jpeg'
                );
            }

            return [
                'success' => true,
                'message' => 'Bill request '.$request->id.' is '.$request->status.'. It is not paid. Send the provider, account or reference, and amount so a person can review it.',
                'bill_request_id' => $request->id,
                'bill_pending' => 1,
                'duplicate' => ! empty($created['duplicate']),
            ];
        }
        $request = $this->billFor($params, $context);
        if (! $request) {
            return $this->denied();
        }
        if ($tool === 'get_bill_payment_request' || $tool === 'get_bill_payment_status') {
            return ['success' => true, 'message' => 'Bill request '.$request->id.' is '.$request->status.'.', 'bill_request_id' => $request->id, 'bill_pending' => $request->status !== 'PAID' ? 1 : 0];
        }
        if ($tool === 'update_bill_payment_details') {
            $updated = $this->bills->updateDetails($request, [
                'provider' => isset($params['provider']) ? $params['provider'] : $this->providerFrom(isset($params['text']) ? $params['text'] : ''),
                'account_reference' => isset($params['account_reference']) ? $params['account_reference'] : $this->accountFrom(isset($params['text']) ? $params['text'] : ''),
                'amount' => isset($params['amount']) ? $params['amount'] : null,
                'provisional' => ! empty($params['provisional']),
            ]);
            $request = $updated['request'];

            return ['success' => true, 'message' => $this->summary($request), 'bill_request_id' => $request->id, 'bill_pending' => 1];
        }
        if ($tool === 'attach_bill_image') {
            $bytes = isset($params['bytes']) ? $params['bytes'] : '';
            if ($bytes === '' && isset($params['media']['bytes'])) {
                $bytes = $params['media']['bytes'];
            }
            $saved = $this->bills->attach(
                $request,
                $bytes,
                isset($params['filename']) ? $params['filename'] : 'bill.jpg',
                isset($params['mime']) ? $params['mime'] : 'image/jpeg'
            );
            if (empty($saved['ok'])) {
                return ['success' => true, 'message' => 'I could not store that image. Send a JPEG, PNG, or WebP photo.', 'bill_request_id' => $request->id, 'bill_pending' => 1];
            }

            return ['success' => true, 'message' => 'The bill image is stored on request '.$request->id.'. Anything read from it is provisional until you confirm the provider, account, and amount. It is not paid.', 'bill_request_id' => $request->id, 'bill_pending' => 1, 'provisional' => true];
        }
        if ($tool === 'confirm_bill_payment_request') {
            $confirmed = $this->bills->confirm($request);
            $request = $confirmed['request'];
            if (empty($confirmed['ok'])) {
                return ['success' => true, 'message' => 'I still need the provider, account or reference, and amount. Nothing has been paid.', 'bill_request_id' => $request->id, 'bill_pending' => 1];
            }

            return [
                'success' => true,
                'message' => $this->summary($request).' Status: '.$request->status.'. No payment has been taken.',
                'bill_request_id' => $request->id,
                'bill_pending' => 0,
                'debited' => false,
            ];
        }

        return ['success' => false, 'error' => 'unknown_tool'];
    }

    protected function prefillUtility(BillPaymentRequest $request)
    {
        if (! $request->tenancy_id || ! \Illuminate\Support\Facades\Schema::hasTable('property_utility_accounts')) {
            return;
        }
        $tenancy = Tenancy::find($request->tenancy_id);
        if (! $tenancy) {
            return;
        }
        $account = DB::table('property_utility_accounts')
            ->where('unit_id', $tenancy->unit_id)
            ->where('utility_type', $request->bill_category)
            ->where('is_active', 1)
            ->first();
        if (! $account) {
            return;
        }
        $request->provider = $account->provider;
        $request->account_reference = $account->account_reference;
        $request->status = 'SUBMITTED';
        $request->save();
    }

    protected function summary(BillPaymentRequest $request)
    {
        $fee = round((float) $request->service_fee, 2);
        $amount = round((float) $request->amount, 2);
        $total = round($amount + $fee, 2);

        return 'Provider: '.($request->provider ?: 'not set')
            ."\nAccount: ".($request->account_reference ?: 'not set')
            ."\nAmount: ".number_format($amount, 0, '.', ',').' '.$request->currency
            ."\nService fee: ".number_format($fee, 0, '.', ',').' '.$request->currency
            ."\nTotal: ".number_format($total, 0, '.', ',').' '.$request->currency;
    }

    protected function billFor(array $params, array $context)
    {
        $id = ! empty($params['bill_request_id']) ? (int) $params['bill_request_id'] : 0;
        $query = BillPaymentRequest::query();
        if (! empty($context['customer_id'])) {
            $query->where('customer_id', (int) $context['customer_id']);
        } elseif (! empty($context['tenancy_id'])) {
            $query->where('tenancy_id', (int) $context['tenancy_id']);
        } else {
            return null;
        }
        if ($id > 0) {
            $row = (clone $query)->where('id', $id)->first();
            if (! $row) {
                $this->log->write('bill_denied', 'bill', $id, ['reason' => 'wrong_owner']);
            }

            return $row;
        }

        return $query->orderByDesc('id')->first();
    }

    protected function tenancy(array $context, array $params)
    {
        $ids = isset($context['tenancy_ids']) && is_array($context['tenancy_ids']) ? array_map('intval', $context['tenancy_ids']) : [];
        if (! empty($context['tenancy_id'])) {
            $ids[] = (int) $context['tenancy_id'];
        }
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return ['error' => ['success' => true, 'message' => 'I could not find a tenancy for this account.']];
        }
        $requested = ! empty($params['tenancy_id']) ? (int) $params['tenancy_id'] : 0;
        if ($requested > 0 && ! in_array($requested, $ids, true)) {
            $this->log->write('tenancy_denied', 'tenancy', $requested, ['reason' => 'wrong_owner']);

            return ['error' => $this->denied()];
        }
        if (count($ids) > 1 && $requested < 1 && empty($context['tenancy_id'])) {
            $units = [];
            foreach (Tenancy::whereIn('id', $ids)->get() as $tenancy) {
                $unit = PropertyUnit::find($tenancy->unit_id);
                $units[] = $unit ? $unit->code : ('tenancy '.$tenancy->id);
            }

            return ['error' => [
                'success' => true,
                'needs_choice' => true,
                'tenant_choice_pending' => 1,
                'message' => 'Which unit should I use? '.implode(', ', $units).'.',
            ]];
        }
        $id = $requested > 0 ? $requested : (int) $ids[0];
        $row = Tenancy::find($id);
        if (! $row || ! in_array((int) $row->id, $ids, true)) {
            return ['error' => $this->denied()];
        }

        return ['tenancy' => $row];
    }

    protected function ids(array $context, Tenancy $row)
    {
        $ids = isset($context['tenancy_ids']) && is_array($context['tenancy_ids']) ? array_map('intval', $context['tenancy_ids']) : [];
        $ids[] = (int) $row->id;

        return array_values(array_unique($ids));
    }

    protected function providerFrom($text)
    {
        if (preg_match('/provider\s+([A-Za-z0-9 ._-]{2,40})/i', (string) $text, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    protected function accountFrom($text)
    {
        if (preg_match('/(?:account|reference)\s+([A-Za-z0-9-]{3,40})/i', (string) $text, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    protected function ok($message, Tenancy $row)
    {
        return [
            'success' => true,
            'message' => $message,
            'tenancy_id' => $row->id,
            'tenant_context' => 'tenant',
            'tenant_choice_pending' => 0,
        ];
    }

    protected function denied()
    {
        return ['success' => true, 'message' => "I couldn't provide that for this account."];
    }
}

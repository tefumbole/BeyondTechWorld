<?php

namespace App\Services\Sales;

use App\PaymentAttempt;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PaymentAttemptService
{
    /**
     * Durable row before any provider call. Pending, unknown, and paid attempts are reused.
     *
     * @return array{attempt:?PaymentAttempt,reuse:bool}
     */
    public function claim($checkoutKey, $hash, $amount, array $payload, $saleId = null)
    {
        if (! Schema::hasTable('payment_attempts')) {
            return ['attempt' => null, 'reuse' => false];
        }
        $orders = app(OrderSubmissionService::class);
        if (! $orders->validKey($checkoutKey)) {
            $checkoutKey = 'pay-'.bin2hex(random_bytes(16));
        }
        $tenant = $orders->tenantId();

        return DB::transaction(function () use ($orders, $tenant, $checkoutKey, $hash, $amount, $payload, $saleId) {
            $row = PaymentAttempt::where('cloud_tenant_id', $tenant)
                ->where('checkout_key', $checkoutKey)
                ->lockForUpdate()
                ->first();
            if ($row) {
                if (! hash_equals((string) $row->payload_hash, (string) $hash)) {
                    throw new CheckoutConflictException('This checkout was already used for a different payment.');
                }
                if ($saleId && ! $row->sale_id) {
                    $row->sale_id = $saleId;
                    $row->save();
                }
                if (in_array($row->status, ['pending', 'unknown', 'paid'], true)) {
                    return ['attempt' => $row, 'reuse' => true];
                }
                $row->status = 'pending';
                $row->provider_reference = null;
                $row->last_error = null;
                $row->amount = $amount;
                $row->payload_hash = $hash;
                $row->request_payload = $payload;
                $row->sale_id = $saleId ?: $row->sale_id;
                $row->external_reference = bin2hex(random_bytes(10));
                $row->save();

                return ['attempt' => $row, 'reuse' => false];
            }

            try {
                $row = new PaymentAttempt();
                $row->cloud_tenant_id = $tenant;
                $row->checkout_key = $checkoutKey;
                $row->sale_id = $saleId;
                $row->provider = 'campay';
                $row->external_reference = bin2hex(random_bytes(10));
                $row->amount = $amount;
                $row->status = 'pending';
                $row->payload_hash = $hash;
                $row->request_payload = $payload;
                $row->save();
            } catch (QueryException $e) {
                if (! $orders->duplicateKey($e)) {
                    throw $e;
                }
                $row = PaymentAttempt::where('cloud_tenant_id', $tenant)
                    ->where('checkout_key', $checkoutKey)
                    ->first();
                if (! $row) {
                    throw $e;
                }
                if (! hash_equals((string) $row->payload_hash, (string) $hash)) {
                    throw new CheckoutConflictException('This checkout was already used for a different payment.');
                }

                return ['attempt' => $row, 'reuse' => true];
            }

            return ['attempt' => $row, 'reuse' => false];
        });
    }

    public function mark(PaymentAttempt $attempt, $status, $providerReference = null, $error = null)
    {
        $attempt->status = $status;
        if ($providerReference) {
            $attempt->provider_reference = $providerReference;
        }
        $attempt->last_error = $error ? substr((string) $error, 0, 500) : null;
        $attempt->save();

        return $attempt;
    }
}

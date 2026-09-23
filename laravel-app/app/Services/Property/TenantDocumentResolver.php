<?php

namespace App\Services\Property;

use App\Property\BillPaymentRequest;
use App\Property\RentObligation;
use App\Property\RentPayment;
use App\Property\Tenancy;

class TenantDocumentResolver
{
    protected $log;

    public function __construct(PropertyActivityLogger $log)
    {
        $this->log = $log;
    }

    public function resolve($type, array $owner, $text)
    {
        if ($type === 'BILL_PAYMENT_RECEIPT') {
            return $this->bill($owner, $text);
        }
        if (($owner['type'] ?? '') !== 'tenant') {
            return $this->deny();
        }
        $tenancy = Tenancy::find((int) $owner['id']);
        if (! $tenancy) {
            return $this->deny();
        }
        if ($type === 'TENANCY_AGREEMENT') {
            if (! $tenancy->agreement_path) {
                return ['ok' => false, 'code' => 'not_available', 'message' => 'That agreement is not on file.'];
            }
            $real = realpath($tenancy->agreement_path);
            $root = realpath(storage_path('app'));
            if (! $real || ! $root || strpos($real, $root) !== 0) {
                return $this->deny();
            }

            return ['ok' => true, 'id' => $tenancy->id];
        }
        if ($type === 'RENT_STATEMENT') {
            return ['ok' => true, 'id' => $tenancy->id];
        }
        if ($type === 'RENT_RECEIPT') {
            $query = RentPayment::where('tenancy_id', $tenancy->id);
            $reference = $this->reference($text);
            if ($reference !== null) {
                $owned = (clone $query)->where('id', (int) $reference)->first();
                if (! $owned) {
                    $this->log->write('rent_receipt_denied', 'tenancy', $tenancy->id, ['requested_id' => (int) $reference]);

                    return $this->deny();
                }

                return ['ok' => true, 'id' => $owned->id];
            }
            $row = $query->orderByDesc('id')->first();
            if (! $row) {
                return ['ok' => false, 'code' => 'not_found', 'message' => 'I could not find a rent receipt for this account.'];
            }

            return ['ok' => true, 'id' => $row->id];
        }

        return ['ok' => false, 'code' => 'not_available', 'message' => 'That document is not available on WhatsApp.'];
    }

    protected function bill(array $owner, $text)
    {
        if (($owner['type'] ?? '') !== 'customer') {
            return $this->deny();
        }
        $query = BillPaymentRequest::where('customer_id', (int) $owner['id'])->where('status', 'PAID');
        $reference = $this->reference($text);
        if ($reference !== null) {
            $owned = (clone $query)->where('id', (int) $reference)->first();
            if (! $owned) {
                $this->log->write('bill_receipt_denied', 'customer', (int) $owner['id'], ['requested_id' => (int) $reference]);

                return $this->deny();
            }

            return ['ok' => true, 'id' => $owned->id];
        }
        $row = $query->orderByDesc('id')->first();
        if (! $row) {
            return ['ok' => false, 'code' => 'not_found', 'message' => 'I could not find a paid bill receipt for this account.'];
        }

        return ['ok' => true, 'id' => $row->id];
    }

    protected function reference($text)
    {
        if (preg_match('/\b(\d{1,8})\b/', (string) $text, $m)) {
            return $m[1];
        }

        return null;
    }

    protected function deny()
    {
        return ['ok' => false, 'code' => 'wrong_owner', 'message' => "I couldn't provide that document for this account."];
    }
}

<?php

namespace App\Services\Contracts;

use App\ContractAuditLog;
use Illuminate\Support\Facades\Auth;

class ContractAuditService
{
    public function log($contractId, $action, $before = null, $after = null, $request = null)
    {
        try {
            ContractAuditLog::create([
                'contract_id' => $contractId,
                'actor_type' => Auth::check() ? 'user' : 'system',
                'actor_id' => Auth::check() ? (string) Auth::id() : null,
                'action' => substr((string) $action, 0, 80),
                'before_json' => $before === null ? null : json_encode($before),
                'after_json' => $after === null ? null : json_encode($after),
                'ip_address' => $request ? $request->ip() : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('[contract-audit] '.$e->getMessage());
        }
    }
}

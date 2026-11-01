<?php

namespace App\Services\Sales;

use App\OrderSubmission;
use App\PaymentAttempt;
use App\Sale;
use App\Services\Cloud\CloudTenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class OrderSubmissionService
{
    public function tenantId()
    {
        $id = app(CloudTenantContext::class)->id();

        return $id ? (int) $id : 1;
    }

    public function validKey($key)
    {
        return is_string($key) && preg_match('/^[A-Za-z0-9_.:-]{8,80}$/', $key) === 1;
    }

    public function keyFrom(Request $request)
    {
        $open = $this->blockingKey((int) Auth::id());
        if ($open) {
            return $open;
        }
        $key = trim((string) $request->input('checkout_key'));
        if ($this->validKey($key)) {
            return $key;
        }
        $slot = 'pos.checkout.'.(int) Auth::id();
        $saved = session($slot);
        if (is_string($saved) && $this->validKey($saved)) {
            return $saved;
        }
        $fresh = 'pos-'.bin2hex(random_bytes(16));
        session([$slot => $fresh]);

        return $fresh;
    }

    public function hashPos(array $data)
    {
        $ids = isset($data['product_id']) ? (array) $data['product_id'] : [];
        $qtys = isset($data['qty']) ? (array) $data['qty'] : [];
        $prices = isset($data['net_unit_price']) ? (array) $data['net_unit_price'] : [];
        $lines = [];
        foreach ($ids as $i => $id) {
            $lines[] = [
                (string) $id,
                (string) (isset($qtys[$i]) ? $qtys[$i] : ''),
                (string) (isset($prices[$i]) ? $prices[$i] : ''),
            ];
        }

        return hash('sha256', json_encode([
            'customer_id' => (string) (isset($data['customer_id']) ? $data['customer_id'] : ''),
            'warehouse_id' => (string) (isset($data['warehouse_id']) ? $data['warehouse_id'] : ''),
            'paid_by_id' => (string) (isset($data['paid_by_id']) ? $data['paid_by_id'] : ''),
            'grand_total' => (string) (isset($data['grand_total']) ? $data['grand_total'] : ''),
            'paid_amount' => (string) (isset($data['paid_amount']) ? $data['paid_amount'] : ''),
            'lines' => $lines,
        ]));
    }

    /**
     * Claim the checkout key before a sale row is written.
     * A response means the caller must return it and write nothing.
     *
     * @return array{response:mixed,submission:?OrderSubmission,key:?string}
     */
    public function claimBeforeCreate(Request $request, array $data)
    {
        if (empty($data['pos']) || ! Schema::hasTable('order_submissions')) {
            return ['response' => null, 'submission' => null, 'key' => null];
        }
        $key = $this->keyFrom($request);
        $hash = $this->hashPos($data);
        $existing = $this->find($key);
        if ($existing) {
            return ['response' => $this->replay($request, $existing, $hash), 'submission' => null, 'key' => $key];
        }
        try {
            $row = new OrderSubmission();
            $row->cloud_tenant_id = $this->tenantId();
            $row->idempotency_key = $key;
            $row->payload_hash = $hash;
            $row->user_id = Auth::id();
            $row->status = 'pending';
            $row->save();
        } catch (QueryException $e) {
            if (! $this->duplicateKey($e)) {
                throw $e;
            }
            $existing = $this->find($key);
            if ($existing) {
                return ['response' => $this->replay($request, $existing, $hash), 'submission' => null, 'key' => $key];
            }
            throw $e;
        }

        return ['response' => null, 'submission' => $row, 'key' => $key];
    }

    public function link($submission, Sale $sale)
    {
        if (! $submission instanceof OrderSubmission) {
            return;
        }
        $submission->sale_id = $sale->id;
        $submission->status = 'linked';
        $submission->save();
    }

    public function finish(Sale $sale)
    {
        if (! Schema::hasTable('order_submissions')) {
            return;
        }
        OrderSubmission::where('cloud_tenant_id', $this->tenantId())
            ->where('sale_id', $sale->id)
            ->update(['status' => 'committed']);
        session()->forget('pos.checkout.'.(int) Auth::id());
    }

    public function releaseSale($saleId)
    {
        if (! $saleId || ! Schema::hasTable('order_submissions')) {
            return;
        }
        OrderSubmission::where('cloud_tenant_id', $this->tenantId())
            ->where('sale_id', $saleId)
            ->where('status', '!=', 'committed')
            ->delete();
        if (Schema::hasTable('payment_attempts')) {
            PaymentAttempt::where('cloud_tenant_id', $this->tenantId())
                ->where('sale_id', $saleId)
                ->whereIn('status', ['pending', 'unknown'])
                ->update(['status' => 'failed', 'last_error' => 'sale_removed']);
        }
    }

    public function find($key)
    {
        if (! $this->validKey($key) || ! Schema::hasTable('order_submissions')) {
            return null;
        }

        return OrderSubmission::where('cloud_tenant_id', $this->tenantId())
            ->where('idempotency_key', $key)
            ->first();
    }

    public function replay(Request $request, OrderSubmission $existing, $hash)
    {
        if (! hash_equals((string) $existing->payload_hash, (string) $hash)) {
            return back()->with('not_permitted', 'This checkout was already used for a different order. Start a new checkout to change it.');
        }
        if (! $existing->sale_id) {
            return back()->with('not_permitted', 'This checkout is still being saved. Keep the cart and submit it again.');
        }
        $sale = Sale::find($existing->sale_id);
        if (! $sale) {
            $existing->delete();

            return back()->with('not_permitted', 'The earlier checkout was not saved. Submit the cart again.');
        }
        if ((int) $sale->payment_status === 4) {
            $this->finish($sale);

            return redirect('sales/gen_invoice/'.$sale->id)->with('message', 'This order was already placed.');
        }
        if (Cache::get('pos_momo:'.$sale->id)) {
            return redirect()->route('pos.momo.wait', ['id' => $sale->id]);
        }

        return back()->with('not_permitted', 'This checkout is still open. Wait until Mobile Money is reconciled before starting another charge.');
    }

    public function duplicateKey(QueryException $e)
    {
        $message = $e->getMessage();

        return (string) $e->getCode() === '23000'
            || strpos($message, 'UNIQUE') !== false
            || strpos($message, 'Duplicate') !== false;
    }

    protected function blockingKey($userId)
    {
        if ($userId < 1 || ! Schema::hasTable('order_submissions')) {
            return null;
        }
        $row = OrderSubmission::where('cloud_tenant_id', $this->tenantId())
            ->where('user_id', $userId)
            ->whereIn('status', ['pending', 'linked'])
            ->orderByDesc('id')
            ->first();
        if (! $row) {
            return null;
        }
        if ($row->status === 'pending' && ! $row->sale_id && $row->created_at && $row->created_at->lt(now()->subMinutes(2))) {
            $row->delete();

            return null;
        }

        return $row->idempotency_key;
    }
}

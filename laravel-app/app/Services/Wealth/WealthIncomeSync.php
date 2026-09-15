<?php

namespace App\Services\Wealth;

use App\GeneralSetting;
use App\Payment;
use App\Sale;
use App\Support\WealthMoney;
use App\WealthIncome;
use App\WealthIncomeCategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class WealthIncomeSync
{
    public function syncSale(Sale $sale)
    {
        if (! Schema::hasTable('wealth_income')) {
            return null;
        }
        $sale->refresh();
        $row = $this->findForSale($sale->id);
        $qualifies = (int) $sale->sale_status === 1
            && (int) $sale->payment_status === 4
            && WealthMoney::cmp($sale->paid_amount, '0') === 1;

        if (! $qualifies) {
            if ($row) {
                $row->status = 'reversed';
                $row->amount = WealthMoney::of($sale->paid_amount);
                $row->updated_by = Auth::id();
                $row->save();
            }

            return $row;
        }

        $isPos = ! empty($sale->cash_register_id);
        $payload = [
            'source_type' => $isPos ? 'pos' : 'sale',
            'source_id' => $sale->id,
            'occurred_at' => $sale->created_at ?: now(),
            'amount' => WealthMoney::of($sale->paid_amount),
            'currency' => $this->currency(),
            'title' => 'Sale '.$sale->reference_no,
            'description' => $sale->sale_note,
            'biller_id' => $sale->biller_id,
            'user_id' => $sale->user_id,
            'customer_id' => $sale->customer_id,
            'reference' => $sale->reference_no,
            'payment_method' => $this->latestMethod($sale->id),
            'status' => 'posted',
            'income_category_id' => $this->categoryId($isPos ? 'POS' : 'SALES'),
            'updated_by' => Auth::id(),
        ];
        if ($row) {
            $row->fill($payload);
            $row->save();

            return $row;
        }
        $payload['created_by'] = Auth::id() ?: $sale->user_id;

        return WealthIncome::create($payload);
    }

    public function reverseSale($saleId)
    {
        $row = $this->findForSale($saleId);
        if ($row) {
            $row->status = 'reversed';
            $row->updated_by = Auth::id();
            $row->save();
        }

        return $row;
    }

    public function syncAllPaidSales()
    {
        $count = 0;
        Sale::where('sale_status', 1)->where('payment_status', 4)->where('paid_amount', '>', 0)
            ->orderBy('id')->chunk(100, function ($sales) use (&$count) {
                foreach ($sales as $sale) {
                    $this->syncSale($sale);
                    $count++;
                }
            });

        return $count;
    }

    protected function findForSale($saleId)
    {
        return WealthIncome::where('source_id', $saleId)
            ->whereIn('source_type', ['sale', 'pos'])
            ->first();
    }

    protected function latestMethod($saleId)
    {
        $p = Payment::where('sale_id', $saleId)->orderByDesc('id')->first();

        return $p ? $p->paying_method : null;
    }

    protected function categoryId($code)
    {
        return WealthIncomeCategory::where('code', $code)->value('id');
    }

    protected function currency()
    {
        $gs = GeneralSetting::first();
        if ($gs && $gs->currency) {
            $cur = \App\Currency::find($gs->currency);

            return $cur ? $cur->code : 'XAF';
        }

        return 'XAF';
    }
}

<?php

namespace App\Services;

use App\Account;
use App\Deposit;
use Illuminate\Support\Facades\DB;

class AccountDepositService
{
    /**
     * Record money paid into an account and return the deposit row.
     */
    public function record(Account $account, $amount, $method, $note, $userId)
    {
        $amount = round((float) $amount, 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Enter a deposit amount greater than zero.');
        }
        if (! $account->is_active) {
            throw new \RuntimeException('That account is not active.');
        }

        return DB::transaction(function () use ($account, $amount, $method, $note, $userId) {
            $current = (float) $account->total_balance;
            if ($current == 0.0 && (float) $account->initial_balance > 0) {
                $current = (float) $account->initial_balance;
            }
            $account->total_balance = $current + $amount;
            $account->save();

            return Deposit::create([
                'amount' => $amount,
                'customer_id' => null,
                'user_id' => $userId,
                'depositor_id' => $userId,
                'account_id' => $account->id,
                'note' => $note !== null && $note !== '' ? $note : null,
                'payment_method' => (int) $method,
                'payment_reference' => 'dep-'.date('Ymd').'-'.date('His'),
                'status' => 1,
            ]);
        });
    }

    /**
     * Add a waiting deposit to its account once Campay has collected it.
     */
    public function creditPending(Deposit $deposit)
    {
        return DB::transaction(function () use ($deposit) {
            $locked = Deposit::where('id', $deposit->id)->lockForUpdate()->first();
            if (! $locked || (int) $locked->status === 1) {
                return $locked;
            }
            $account = Account::where('id', $locked->account_id)->lockForUpdate()->first();
            if (! $account) {
                return $locked;
            }
            $current = (float) $account->total_balance;
            if ($current == 0.0 && (float) $account->initial_balance > 0) {
                $current = (float) $account->initial_balance;
            }
            $account->total_balance = $current + (float) $locked->amount;
            $account->save();
            $locked->status = 1;
            $locked->save();

            return $locked;
        });
    }
}

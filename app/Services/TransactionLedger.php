<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * The ledger entry behind one finance transaction (source_type 'transaction').
 *
 * Income (and balance-sheet money IN):  Dr cash/bank  / Cr the transaction's account
 * Expense (and balance-sheet money OUT): Dr the transaction's account / Cr cash/bank
 *
 * repost() rewrites it after an edit, so the ledger always matches the transaction:
 * the entry is removed and posted again from the transaction's current amount, date,
 * category, account and status, keeping the cash/bank account it was received into
 * (or paid from) unless a new one is given. Only Confirmed transactions with both an
 * account and a cash/bank account have an entry — the same rule as when recording.
 */
class TransactionLedger
{
    public function entry(Transaction $tx): ?JournalEntry
    {
        return JournalEntry::with('lines')->where('source_type', 'transaction')->where('source_id', $tx->id)->latest('id')->first();
    }

    /** The cash/bank side of the transaction's entry: the line that isn't the transaction's own account. */
    public function cashAccountId(Transaction $tx): ?int
    {
        $entry = $this->entry($tx);
        if (! $entry) {
            return null;
        }

        $line = $entry->lines->first(fn ($l) => (int) $l->account_id !== (int) $tx->account_id);

        return $line ? (int) $line->account_id : null;
    }

    public function repost(Transaction $tx, ?int $cashAccountId = null): ?JournalEntry
    {
        return DB::transaction(function () use ($tx, $cashAccountId) {
            $cash = $cashAccountId ?: $this->cashAccountId($tx);
            $ledger = new LedgerService();
            $ledger->removeForSource('transaction', $tx->id);

            if ($tx->trashed() || $tx->status !== 'Confirmed' || ! $tx->account_id || ! $cash) {
                return null;
            }

            $amount = (float) $tx->amount;
            $lines  = $tx->category === 'Expense'
                ? [['account_id' => $tx->account_id, 'debit' => $amount, 'credit' => 0], ['account_id' => $cash, 'debit' => 0, 'credit' => $amount]]
                : [['account_id' => $cash, 'debit' => $amount, 'credit' => 0], ['account_id' => $tx->account_id, 'debit' => 0, 'credit' => $amount]];

            return $ledger->postEntry($lines, $tx->transaction_date->toDateString(), "{$tx->type} — {$tx->reference}", 'transaction', $tx->id);
        });
    }
}

<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Support\Facades\DB;

/**
 * The double-entry posting engine.
 *
 * Staff record transactions the simple way (amount + income/expense account
 * + which cash/bank account). This service writes the correct BALANCED
 * journal entry behind the scenes, so the books stay double-entry-correct
 * without anyone thinking about debits and credits.
 *
 * Accounting rules encoded here:
 *   - Income received  → DEBIT cash/bank, CREDIT income account
 *   - Expense paid     → DEBIT expense account, CREDIT cash/bank
 *   - Generic transfer → caller supplies debit + credit accounts
 */
class LedgerService
{
    /**
     * Post an INCOME event (money coming in).
     *
     * @param float   $amount
     * @param int     $incomeAccountId   the income account earned (e.g. Tithe Income)
     * @param int     $cashAccountId     the cash/bank account it went into
     * @param string  $date
     * @param string|null $description
     * @param string|null $sourceType    e.g. 'transaction'
     * @param int|null    $sourceId
     */
    public function postIncome(float $amount, int $incomeAccountId, int $cashAccountId, string $date, ?string $description = null, ?string $sourceType = null, ?int $sourceId = null): JournalEntry
    {
        return $this->postEntry([
            ['account_id' => $cashAccountId,   'debit' => $amount, 'credit' => 0],       // asset up
            ['account_id' => $incomeAccountId, 'debit' => 0,       'credit' => $amount],  // income up
        ], $date, $description, $sourceType, $sourceId);
    }

    /**
     * Post an EXPENSE event (money going out).
     */
    public function postExpense(float $amount, int $expenseAccountId, int $cashAccountId, string $date, ?string $description = null, ?string $sourceType = null, ?int $sourceId = null): JournalEntry
    {
        return $this->postEntry([
            ['account_id' => $expenseAccountId, 'debit' => $amount, 'credit' => 0],       // expense up
            ['account_id' => $cashAccountId,    'debit' => 0,       'credit' => $amount],  // asset down
        ], $date, $description, $sourceType, $sourceId);
    }

    /**
     * Generic balanced entry. $lines is an array of
     * ['account_id' => x, 'debit' => y, 'credit' => z].
     * Throws if the entry does not balance.
     */
    public function postEntry(array $lines, string $date, ?string $description = null, ?string $sourceType = null, ?int $sourceId = null): JournalEntry
    {
        $totalDebit  = round(array_sum(array_column($lines, 'debit')), 2);
        $totalCredit = round(array_sum(array_column($lines, 'credit')), 2);

        if ($totalDebit !== $totalCredit) {
            throw new \RuntimeException("Journal entry does not balance: debit {$totalDebit} vs credit {$totalCredit}.");
        }

        if ($totalDebit == 0.0) {
            throw new \RuntimeException("Journal entry has zero value.");
        }

        return DB::transaction(function () use ($lines, $date, $description, $sourceType, $sourceId) {
            $entry = JournalEntry::create([
                'entry_date'  => $date,
                'description' => $description,
                'reference'   => $this->nextReference(),
                'source_type' => $sourceType,
                'source_id'   => $sourceId,
                'created_by'  => auth()->id(),
            ]);

            foreach ($lines as $line) {
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $line['account_id'],
                    'debit'            => $line['debit'],
                    'credit'           => $line['credit'],
                ]);
            }

            return $entry;
        });
    }

    /**
     * Reverse/remove the journal entry tied to a given source
     * (used when a transaction is edited or deleted, so we can re-post).
     */
    public function removeForSource(string $sourceType, int $sourceId): void
    {
        JournalEntry::where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->each(function ($entry) {
                $entry->lines()->delete();
                $entry->delete();
            });
    }

    private function nextReference(): string
    {
        $n = JournalEntry::count() + 1;
        return 'JE-' . str_pad($n, 5, '0', STR_PAD_LEFT);
    }
}
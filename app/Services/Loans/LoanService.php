<?php

namespace App\Services\Loans;

use App\Models\Account;
use App\Models\Loan;
use App\Models\LoanAdjustment;
use App\Models\LoanEvent;
use App\Models\LoanRepayment;
use App\Models\Transaction;
use App\Services\LedgerService;
use App\Services\TransactionLedger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Keeps loans, their repayments and the ledger in step.
 *
 * Opening:    Dr opening counter account (default 3200 Opening Balance Equity) / Cr loan liability
 * Repayment:  Dr loan liability / Cr the account the money is paid from   (one per counted transaction)
 * Correction: Dr counter / Cr liability when more is owed; the reverse when less
 * Manual repayment: Dr liability / Cr the cash or bank account it was paid from
 *
 * A counted transaction: Confirmed, of one of the loan's types, dated on/after the repayment
 * start date. Each is applied in date order until the balance reaches zero; the last one may
 * be applied in part (the rest is reported as surplus and stays where it was received). Its
 * repayment entry is dated the transaction's date. Income is never touched: repayments are
 * journal entries only.
 *
 * rebuild() recomputes a loan from scratch, so it can run any number of times with the same
 * result; changes to one transaction (edit, void) move the later repayments correctly.
 */
class LoanService
{
    public const OPENING_EQUITY_CODE = '3200';

    public function __construct(private LedgerService $ledger, private TransactionLedger $transactions) {}

    /** 3200 Opening Balance Equity, created under the Equity group the first time it is needed. */
    public function openingEquityAccount(): Account
    {
        $group = Account::where('code', '3000')->first();

        return Account::firstOrCreate(['code' => self::OPENING_EQUITY_CODE], [
            'name' => 'Opening Balance Equity', 'type' => 'Equity', 'parent_id' => $group?->id,
            'is_group' => false, 'is_active' => true, 'sort_order' => 3020,
            'description' => 'Balancing entry for amounts owed before they were recorded (e.g. a loan\'s opening balance).',
        ]);
    }

    /** Types already counted by another Active loan. @return list<string> */
    public function claimedTypes(?Loan $except = null): array
    {
        return Loan::where('status', 'Active')->when($except, fn ($q) => $q->whereKeyNot($except->id))->get()
            ->flatMap(fn (Loan $l) => $l->counted_types)->unique()->values()->all();
    }

    /** Counted transactions that already exist for these settings (shown before saving). */
    public function existingCounted(array $types, string $startDate, ?Loan $except = null): Collection
    {
        return Transaction::whereIn('type', $types)->where('status', 'Confirmed')
            ->whereDate('transaction_date', '>=', $startDate)
            ->whereNotIn('id', LoanRepayment::when($except, fn ($q) => $q->where('loan_id', '!=', $except->id))->select('transaction_id'))
            ->orderBy('transaction_date')->orderBy('id')->get();
    }

    public function create(array $data, ?int $userId): Loan
    {
        return DB::transaction(function () use ($data, $userId) {
            $this->guardTypes($data['counted_types'], null);

            $loan = Loan::create($data + ['status' => 'Active', 'created_by' => $userId]);
            $this->postOpening($loan);
            $this->event($loan, 'opened', null, (float) $loan->opening_balance, null, $userId);
            $this->rebuild($loan, $userId);

            return $loan->fresh();
        });
    }

    public function update(Loan $loan, array $data, ?int $userId): Loan
    {
        return DB::transaction(function () use ($loan, $data, $userId) {
            $this->guardTypes($data['counted_types'] ?? $loan->counted_types, $loan);

            $loan->fill($data);
            $openingChanged = $loan->isDirty(['opening_balance', 'opening_date', 'opening_counter_account_id', 'liability_account_id', 'lender']);
            $postingChanged = $loan->isDirty(['liability_account_id', 'repayment_source', 'repayment_account_id', 'lender']);
            $changed = array_keys($loan->getDirty());
            $loan->save();

            if ($openingChanged) {
                $this->postOpening($loan);
            }
            $this->event($loan, 'edited', null, null, $changed ? 'changed: ' . implode(', ', $changed) : null, $userId);
            $this->rebuild($loan, $userId, force: $postingChanged);

            return $loan->fresh();
        });
    }

    /**
     * A manual change with a reason. correction: $amount > 0 owed more, < 0 owed less (counter
     * usually Opening Balance Equity). repayment: paid some other way, $amount > 0 (counter = the
     * cash or bank account it was paid from).
     */
    public function adjust(Loan $loan, string $kind, float $amount, string $date, string $reason, int $counterAccountId, ?int $userId): LoanAdjustment
    {
        return DB::transaction(function () use ($loan, $kind, $amount, $date, $reason, $counterAccountId, $userId) {
            $signed = $kind === 'repayment' ? -abs($amount) : $amount;
            $adjustment = LoanAdjustment::create([
                'loan_id' => $loan->id, 'kind' => $kind, 'amount' => $signed, 'adjustment_date' => $date,
                'reason' => $reason, 'counter_account_id' => $counterAccountId, 'created_by' => $userId,
            ]);

            $value = abs($signed);
            $lines = $signed > 0
                ? [['account_id' => $counterAccountId, 'debit' => $value, 'credit' => 0], ['account_id' => $loan->liability_account_id, 'debit' => 0, 'credit' => $value]]
                : [['account_id' => $loan->liability_account_id, 'debit' => $value, 'credit' => 0], ['account_id' => $counterAccountId, 'debit' => 0, 'credit' => $value]];
            $entry = $this->ledger->postEntry($lines, $date,
                ($kind === 'repayment' ? 'Loan repayment (manual)' : 'Loan correction') . " — {$loan->lender}: {$reason}", 'loan_adjustment', $adjustment->id);
            $adjustment->update(['journal_entry_id' => $entry->id]);

            $this->event($loan, $kind === 'repayment' ? 'manual_repayment' : 'corrected', null, $signed, $reason, $userId);
            $this->rebuild($loan, $userId);

            return $adjustment;
        });
    }

    /** After a transaction is recorded, edited or voided: rebuild every loan it could affect. */
    public function syncTransaction(Transaction $tx, ?int $userId = null): void
    {
        $holding = LoanRepayment::where('transaction_id', $tx->id)->pluck('loan_id');
        Loan::whereIn('id', $holding)->get()
            ->merge(Loan::get()->filter(fn (Loan $l) => $l->counts((string) $tx->type)))
            ->unique('id')
            ->each(fn (Loan $loan) => $this->rebuild($loan, $userId));
    }

    /**
     * Recompute the loan's repayments from its counted transactions, re-posting only what
     * changed (everything when $force). Returns what changed.
     *
     * @return array{added: int, changed: int, removed: int}
     */
    public function rebuild(Loan $loan, ?int $userId = null, bool $force = false): array
    {
        return DB::transaction(function () use ($loan, $userId, $force) {
            $loan = Loan::whereKey($loan->id)->lockForUpdate()->first();
            $remaining = round($loan->owedTotal() + (float) $loan->adjustments()->where('kind', 'repayment')->sum('amount'), 2);
            $existing = $loan->repayments()->get()->keyBy('transaction_id');
            $kept = [];
            $stats = ['added' => 0, 'changed' => 0, 'removed' => 0];
            $paidOffOn = null;

            foreach ($this->candidates($loan) as $tx) {
                if ($remaining <= 0.004) {
                    break;   // paid off: later payments are not counted
                }
                $applied = round(min((float) $tx->amount, $remaining), 2);
                $remaining = round($remaining - $applied, 2);
                if ($remaining <= 0.004) {
                    $paidOffOn = $tx->transaction_date->toDateString();
                }

                $hasEntry = $this->transactions->entry($tx) !== null;
                $cash = $loan->repayment_source === Loan::SOURCE_FIXED ? $loan->repayment_account_id : $this->transactions->cashAccountId($tx);
                $row = [
                    'transaction_amount' => (float) $tx->amount,
                    'amount_applied'     => $applied,
                    'surplus'            => round((float) $tx->amount - $applied, 2),
                    'cash_account_id'    => $cash,
                    'flag'               => $hasEntry ? null : LoanRepayment::FLAG_NO_LEDGER,
                ];
                $kept[] = (int) $tx->id;

                $repayment = $existing->get($tx->id);
                if ($repayment && ! $force && $this->same($repayment, $row, $tx)) {
                    continue;
                }

                if ($repayment) {
                    $repayment->update($row);
                    $stats['changed']++;
                    $this->event($loan, 'repayment_changed', $tx->id, $applied, null, $userId);
                } else {
                    $repayment = LoanRepayment::create($row + ['loan_id' => $loan->id, 'transaction_id' => $tx->id]);
                    $stats['added']++;
                    $this->event($loan, 'repayment_added', $tx->id, $applied, null, $userId);
                }
                $this->postRepayment($loan, $repayment, $tx);
            }

            // (filter by value: Collection::except() mis-handles these integer keys)
            foreach ($existing->filter(fn (LoanRepayment $r) => ! in_array((int) $r->transaction_id, $kept, true)) as $repayment) {
                $this->ledger->removeForSource('loan_repayment', $repayment->id);
                $this->event($loan, 'repayment_removed', $repayment->transaction_id, (float) $repayment->amount_applied, null, $userId);
                $repayment->delete();
                $stats['removed']++;
            }

            $this->updateStatus($loan, $remaining, $paidOffOn, $userId);

            return $stats;
        });
    }

    /** What the ledger says is owed on the loan's liability account (for the check on the loan page). */
    public function ledgerBalance(Loan $loan): float
    {
        $sums = DB::table('journal_lines')->where('account_id', $loan->liability_account_id)
            ->selectRaw('COALESCE(SUM(credit),0) c, COALESCE(SUM(debit),0) d')->first();

        return round((float) $sums->c - (float) $sums->d, 2);
    }

    // ── internals ──────────────────────────────────────────────────────

    /** Counted transactions, oldest first, that no other loan has already counted. */
    private function candidates(Loan $loan): Collection
    {
        return Transaction::whereIn('type', $loan->counted_types)
            ->where('status', 'Confirmed')
            ->whereDate('transaction_date', '>=', $loan->repayment_start_date->toDateString())
            ->whereNotIn('id', LoanRepayment::where('loan_id', '!=', $loan->id)->select('transaction_id'))
            ->orderBy('transaction_date')->orderBy('id')
            ->get();
    }

    private function same(LoanRepayment $r, array $row, Transaction $tx): bool
    {
        $entryOk = $row['cash_account_id'] ? $r->journal_entry_id !== null : $r->journal_entry_id === null;
        $dateOk  = ! $r->journal_entry_id || optional(\App\Models\JournalEntry::find($r->journal_entry_id))->entry_date?->toDateString() === $tx->transaction_date->toDateString();

        return abs((float) $r->amount_applied - $row['amount_applied']) < 0.005
            && abs((float) $r->transaction_amount - $row['transaction_amount']) < 0.005
            && (int) $r->cash_account_id === (int) $row['cash_account_id']
            && $r->flag === $row['flag']
            && $entryOk && $dateOk;
    }

    private function postRepayment(Loan $loan, LoanRepayment $repayment, Transaction $tx): void
    {
        $this->ledger->removeForSource('loan_repayment', $repayment->id);
        $entryId = null;

        if ($repayment->cash_account_id && (float) $repayment->amount_applied > 0) {
            $amount = (float) $repayment->amount_applied;
            $entryId = $this->ledger->postEntry([
                ['account_id' => $loan->liability_account_id, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $repayment->cash_account_id, 'debit' => 0, 'credit' => $amount],
            ], $tx->transaction_date->toDateString(), "Loan repayment — {$loan->lender} — {$tx->reference}", 'loan_repayment', $repayment->id)->id;
        }

        $repayment->update(['journal_entry_id' => $entryId]);
    }

    private function postOpening(Loan $loan): void
    {
        $this->ledger->removeForSource('loan_opening', $loan->id);
        $amount = (float) $loan->opening_balance;
        $entry = $this->ledger->postEntry([
            ['account_id' => $loan->opening_counter_account_id, 'debit' => $amount, 'credit' => 0],
            ['account_id' => $loan->liability_account_id, 'debit' => 0, 'credit' => $amount],
        ], $loan->opening_date->toDateString(), "Loan opening balance — {$loan->lender}", 'loan_opening', $loan->id);

        $loan->forceFill(['opening_journal_entry_id' => $entry->id])->saveQuietly();
    }

    private function updateStatus(Loan $loan, float $remaining, ?string $paidOffOn, ?int $userId): void
    {
        $paidOff = $remaining <= 0.004;
        if ($paidOff && $loan->status !== 'Paid off') {
            $loan->update(['status' => 'Paid off', 'paid_off_at' => $paidOffOn ?? now()->toDateString()]);
            $this->event($loan, 'paid_off', null, null, null, $userId);
        } elseif ($paidOff && $paidOffOn && $loan->paid_off_at?->toDateString() !== $paidOffOn) {
            $loan->update(['paid_off_at' => $paidOffOn]);
        } elseif (! $paidOff && $loan->status === 'Paid off') {
            $loan->update(['status' => 'Active', 'paid_off_at' => null]);
            $this->event($loan, 'reopened', null, $remaining, null, $userId);
        }
    }

    private function guardTypes(array $types, ?Loan $loan): void
    {
        $clash = array_values(array_intersect($types, $this->claimedTypes($loan)));
        if ($clash) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'counted_types' => 'Already counted by another active loan: ' . implode(', ', $clash) . '.',
            ]);
        }
    }

    private function event(Loan $loan, string $action, ?int $transactionId, ?float $amount, ?string $note, ?int $userId): void
    {
        LoanEvent::create([
            'loan_id' => $loan->id, 'action' => $action, 'transaction_id' => $transactionId,
            'amount' => $amount, 'note' => $note ? mb_substr($note, 0, 250) : null, 'user_id' => $userId,
        ]);
    }
}

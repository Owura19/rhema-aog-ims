<?php

namespace App\Services\DayBorn;

use App\Models\DayBornOffering;
use App\Models\DayBornOfferingChange;
use App\Models\DayBornWeek;
use App\Models\Transaction;
use App\Models\User;
use App\Services\LedgerService;
use App\Support\Api\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Every write to a day-born week goes through here, so each one is audited
 * (day_born_offering_changes) and the optional finance link stays in step:
 *
 *  - "Post to finance" on publish creates ONE Offering transaction (subcategory
 *    "Day-born Offering") for the week's total and posts it to the ledger;
 *  - editing a posted week updates that transaction and re-posts its journal;
 *  - unpublishing voids it (soft delete; its journal entry is removed).
 *
 * Without "Post to finance" a week is a separate tally and never touches the finance totals.
 */
final class DayBornRecorder
{
    public const FINANCE_SUBCATEGORY = 'Day-born Offering';

    public function __construct(private readonly LedgerService $ledger)
    {
    }

    /**
     * Create (no $week) or update a week. Amounts are weekday => "12.50".
     *
     * @param  array<int, string>  $amounts
     */
    public function save(?DayBornWeek $week, array $amounts, string $serviceDate, bool $specialService, ?string $notes, User $user, ?string $reason = null): DayBornWeek
    {
        return DB::transaction(function () use ($week, $amounts, $serviceDate, $specialService, $notes, $user, $reason) {
            $creating = $week === null;
            $week ??= new DayBornWeek(['recorded_by' => $user->id, 'status' => DayBornWeek::STATUS_DRAFT]);

            $oldDetails = $creating ? null : [$week->service_date->toDateString(), $week->special_service, (string) $week->notes];
            $week->fill([
                'service_date'    => $serviceDate,
                'special_service' => $specialService,
                'notes'           => $notes,
                'updated_by'      => $user->id,
            ])->save();

            if (! $creating && $oldDetails !== [$serviceDate, $specialService, (string) $notes]) {
                $this->log($week, 'details_updated', null, null, null, $reason, $user);
            }

            $existing = $week->offerings()->get()->keyBy('weekday');
            foreach ($amounts as $weekday => $amount) {
                $new = Money::format($amount);
                $row = $existing->get($weekday);

                if (! $row) {
                    DayBornOffering::create([
                        'week_id'     => $week->id,
                        'weekday'     => $weekday,
                        'amount'      => $new,
                        'recorded_by' => $user->id,
                        'updated_by'  => $user->id,
                    ]);
                    $this->log($week, $creating ? 'created' : 'updated', $weekday, $creating ? null : '0.00', $new, $reason, $user);
                    continue;
                }

                $old = Money::format($row->amount);
                if ($old !== $new) {
                    $row->update(['amount' => $new, 'updated_by' => $user->id]);
                    $this->log($week, 'updated', $weekday, $old, $new, $reason, $user);
                }
            }

            $week->unsetRelation('offerings');
            if ($week->transaction_id) {
                $this->syncFinance($week, $user);
            }

            return $week;
        });
    }

    /** Publish. With $postToFinance, also record the week's total as ONE Offering transaction. */
    public function publish(DayBornWeek $week, User $user, bool $postToFinance = false, ?int $financeAccountId = null, ?int $cashAccountId = null): void
    {
        DB::transaction(function () use ($week, $user, $postToFinance, $financeAccountId, $cashAccountId) {
            $week->update([
                'status'       => DayBornWeek::STATUS_PUBLISHED,
                'published_at' => now(),
                'published_by' => $user->id,
                'updated_by'   => $user->id,
            ]);
            $this->log($week, 'published', null, null, $week->load('offerings')->total(), null, $user);

            if ($postToFinance && ! $week->transaction_id) {
                if (Money::toCents($week->total()) <= 0) {
                    throw new RuntimeException('A week with a total of 0.00 cannot be posted to finance.');
                }
                $week->update(['finance_account_id' => $financeAccountId, 'cash_account_id' => $cashAccountId]);
                $this->createTransaction($week, $user);
            }
        });
    }

    /** Back to draft: hidden from members; a posted finance transaction is voided. */
    public function unpublish(DayBornWeek $week, User $user, ?string $reason = null): void
    {
        DB::transaction(function () use ($week, $user, $reason) {
            if ($week->transaction_id) {
                $transaction = Transaction::find($week->transaction_id);
                $transaction?->delete();   // soft delete; the model's deleted hook removes the journal entry
                $this->log($week, 'finance_voided', null, $transaction ? Money::format($transaction->amount) : null, null, $reason, $user);
            }

            $week->update([
                'status'         => DayBornWeek::STATUS_DRAFT,
                'published_at'   => null,
                'published_by'   => null,
                'transaction_id' => null,
                'updated_by'     => $user->id,
            ]);
            $this->log($week, 'unpublished', null, null, null, $reason, $user);
        });
    }

    /** Drafts only (never posted). The audit rows stay, with the date. */
    public function deleteDraft(DayBornWeek $week, User $user): void
    {
        if ($week->isPublished() || $week->transaction_id) {
            throw new RuntimeException('Only a draft that was never posted to finance can be deleted.');
        }

        DB::transaction(function () use ($week, $user) {
            $this->log($week, 'deleted', null, $week->load('offerings')->total(), null, null, $user);
            $week->delete();
        });
    }

    private function createTransaction(DayBornWeek $week, User $user): void
    {
        $week->load('offerings');
        $transaction = Transaction::create([
            'type'             => 'Offering',
            'subcategory'      => self::FINANCE_SUBCATEGORY,
            'account_id'       => $week->finance_account_id,
            'category'         => 'Income',
            'amount'           => $week->total(),
            'currency'         => 'GHS',
            'transaction_date' => $week->service_date->toDateString(),
            'payment_method'   => 'Cash',
            'status'           => 'Confirmed',
            'description'      => $this->description($week),
            'recorded_by'      => $user->id,
        ]);
        $week->update(['transaction_id' => $transaction->id]);
        $this->post($week, $transaction);
        $this->log($week, 'posted_to_finance', null, null, $week->total(), null, $user);
    }

    /** The linked transaction follows the week's total and date; its journal is re-posted. */
    private function syncFinance(DayBornWeek $week, User $user): void
    {
        $week->load('offerings');
        $transaction = Transaction::find($week->transaction_id);
        if (! $transaction) {
            return;
        }

        $old = Money::format($transaction->amount);
        $transaction->update([
            'amount'           => $week->total(),
            'transaction_date' => $week->service_date->toDateString(),
            'description'      => $this->description($week),
        ]);
        $this->ledger->removeForSource('transaction', $transaction->id);
        if (Money::toCents($week->total()) > 0) {
            $this->post($week, $transaction);
        }
        if ($old !== $week->total()) {
            $this->log($week, 'finance_updated', null, $old, $week->total(), null, $user);
        }
    }

    private function post(DayBornWeek $week, Transaction $transaction): void
    {
        if ($week->finance_account_id && $week->cash_account_id) {
            $this->ledger->postIncome(
                (float) $transaction->amount,
                $week->finance_account_id,
                $week->cash_account_id,
                $week->service_date->toDateString(),
                "Offering — {$transaction->reference} (Day-born)",
                'transaction',
                $transaction->id,
            );
        }
    }

    private function description(DayBornWeek $week): string
    {
        return 'Day-born offering, ' . Carbon::parse($week->service_date)->format('l j F Y') . ($week->special_service ? ' (special service)' : '');
    }

    private function log(DayBornWeek $week, string $action, ?int $weekday, ?string $old, ?string $new, ?string $reason, User $user): void
    {
        DayBornOfferingChange::create([
            'week_id'      => $week->id,
            'service_date' => $week->service_date->toDateString(),
            'weekday'      => $weekday,
            'action'       => $action,
            'old_amount'   => $old,
            'new_amount'   => $new,
            'reason'       => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
            'changed_by'   => $user->id,
        ]);
    }
}

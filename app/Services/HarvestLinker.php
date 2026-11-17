<?php

namespace App\Services;

use App\Models\Harvest;
use App\Models\Pledge;
use App\Models\PledgePayment;
use App\Models\PledgePurpose;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ties harvest money recorded in Finance to a pledge on the harvest, so it shows on the
 * Harvest page. The finance transaction stays the single record of the money: the pledge
 * payment points at it (transaction_id), so no second transaction or ledger entry is made.
 *
 * Who the money is from: the transaction's member, or its payer name when there is no member.
 *  - exactly one real pledge on the harvest   -> pay into it
 *  - more than one                            -> staff must choose (pledge_id)
 *  - none                                     -> a "giver pledge" (pledged = what was given)
 */
class HarvestLinker
{
    /** Non-cancelled pledges on the harvest belonging to this member / payer name. */
    public function pledgesFor(Harvest $harvest, ?int $memberId, ?string $payerName): Collection
    {
        $query = Pledge::where('harvest_id', $harvest->id)->where('status', '!=', 'Cancelled');

        if ($memberId) {
            $query->where('member_id', $memberId);
        } elseif (self::normaliseName($payerName) !== '') {
            $query->whereNull('member_id')->whereRaw('LOWER(TRIM(pledger_name)) = ?', [self::normaliseName($payerName)]);
        } else {
            return collect();
        }

        return $query->orderBy('id')->get();
    }

    /**
     * Which pledge this money belongs to.
     *
     * @return array{pledge: ?Pledge, giver: bool, error: ?string}
     *         pledge null + giver true = a new giver pledge is needed.
     */
    public function resolve(Harvest $harvest, ?int $memberId, ?string $payerName, ?int $chosenPledgeId = null): array
    {
        if (! $memberId && self::normaliseName($payerName) === '') {
            return ['pledge' => null, 'giver' => false, 'error' => 'Select the member, or enter the payer name, for harvest money.'];
        }

        $pledges = $this->pledgesFor($harvest, $memberId, $payerName);
        $real = $pledges->reject->isGiver()->values();

        if ($chosenPledgeId) {
            $chosen = $pledges->firstWhere('id', $chosenPledgeId);

            return $chosen
                ? ['pledge' => $chosen, 'giver' => $chosen->isGiver(), 'error' => null]
                : ['pledge' => null, 'giver' => false, 'error' => 'That pledge is not on this harvest for this payer.'];
        }

        if ($real->count() === 1) {
            return ['pledge' => $real->first(), 'giver' => false, 'error' => null];
        }

        if ($real->count() > 1) {
            return ['pledge' => null, 'giver' => false, 'error' => 'This payer has more than one pledge on this harvest: choose which one the money is for.'];
        }

        return ['pledge' => $pledges->first(fn (Pledge $p) => $p->isGiver()), 'giver' => true, 'error' => null];
    }

    /**
     * Record the transaction as a payment on the right pledge. Throws if it can't be resolved
     * (callers validate with resolve() first).
     */
    public function link(Transaction $transaction, Harvest $harvest, ?int $chosenPledgeId = null, ?string $note = null): PledgePayment
    {
        return DB::transaction(function () use ($transaction, $harvest, $chosenPledgeId, $note) {
            $resolved = $this->resolve($harvest, $transaction->member_id, $transaction->payer_name, $chosenPledgeId);
            if ($resolved['error']) {
                throw new \DomainException($resolved['error']);
            }

            $pledge = $resolved['pledge'] ?? $this->newGiverPledge($harvest, $transaction);

            // transaction_id is set up front, so PledgePayment's created hook does NOT
            // make another transaction or ledger entry. It tops up a giver pledge and
            // refreshes the pledge status.
            return PledgePayment::create([
                'pledge_id'      => $pledge->id,
                'amount'         => $transaction->amount,
                'payment_date'   => $transaction->transaction_date,
                'payment_method' => $transaction->payment_method,
                'transaction_id' => $transaction->id,
                'notes'          => $note ?? "Recorded in Finance as {$transaction->reference}",
                'recorded_by'    => $transaction->recorded_by,
            ]);
        });
    }

    /** Remove the pledge payment(s) made through this transaction; the transaction itself stays. */
    public function unlink(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            PledgePayment::where('transaction_id', $transaction->id)->get()->each(function (PledgePayment $payment) {
                $payment->keepTransaction = true;
                $payment->delete();
            });
        });
    }

    /**
     * After a transaction is saved: make its pledge payment match it, or remove it when the
     * transaction is no longer Confirmed harvest money.
     */
    public function sync(Transaction $transaction, ?Harvest $harvest, ?int $chosenPledgeId = null): ?PledgePayment
    {
        return DB::transaction(function () use ($transaction, $harvest, $chosenPledgeId) {
            $qualifies = $harvest
                && $transaction->type === 'Harvest'
                && $transaction->status === 'Confirmed'
                && ! $transaction->trashed();

            if (! $qualifies) {
                $this->unlink($transaction);

                return null;
            }

            $existing = PledgePayment::with('pledge')->where('transaction_id', $transaction->id)->first();
            if ($existing && $this->stillFits($existing, $transaction, $harvest, $chosenPledgeId)) {
                $existing->update([
                    'amount'         => $transaction->amount,
                    'payment_date'   => $transaction->transaction_date,
                    'payment_method' => $transaction->payment_method,
                ]);

                return $existing->fresh();
            }

            $this->unlink($transaction);

            return $this->link($transaction, $harvest, $chosenPledgeId);
        });
    }

    /** The harvest a transaction's money is currently recorded against, if any. */
    public function linkedPayment(Transaction $transaction): ?PledgePayment
    {
        return PledgePayment::with('pledge.harvest')->where('transaction_id', $transaction->id)->first();
    }

    public function harvestPurposeId(): int
    {
        $purpose = PledgePurpose::whereRaw('LOWER(name) = ?', ['harvest'])->first()
            ?? PledgePurpose::where('name', 'like', '%harvest%')->orderBy('id')->first()
            ?? PledgePurpose::create(['name' => 'Harvest', 'description' => 'Annual harvest contributions', 'is_active' => true]);

        return $purpose->id;
    }

    public static function normaliseName(?string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $name)));
    }

    private function stillFits(PledgePayment $payment, Transaction $transaction, Harvest $harvest, ?int $chosenPledgeId): bool
    {
        $pledge = $payment->pledge;
        if (! $pledge || $pledge->status === 'Cancelled' || (int) $pledge->harvest_id !== (int) $harvest->id) {
            return false;
        }
        if ($chosenPledgeId && (int) $chosenPledgeId !== (int) $pledge->id) {
            return false;
        }

        return $transaction->member_id
            ? (int) $pledge->member_id === (int) $transaction->member_id
            : $pledge->member_id === null && self::normaliseName($pledge->pledger_name) === self::normaliseName($transaction->payer_name);
    }

    private function newGiverPledge(Harvest $harvest, Transaction $transaction): Pledge
    {
        // Created at 0: the payment's created hook raises it to the amount given.
        return Pledge::create([
            'member_id'         => $transaction->member_id,
            'pledger_name'      => $transaction->member_id ? null : trim((string) $transaction->payer_name),
            'pledge_purpose_id' => $this->harvestPurposeId(),
            'harvest_id'        => $harvest->id,
            'amount_pledged'    => 0,
            'currency'          => 'GHS',
            'date_pledged'      => $transaction->transaction_date,
            'status'            => 'Fulfilled',
            'notes'             => Pledge::GIVER_NOTE,
            'recorded_by'       => $transaction->recorded_by,
        ]);
    }
}

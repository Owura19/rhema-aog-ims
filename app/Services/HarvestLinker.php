<?php

namespace App\Services;

use App\Models\Harvest;
use App\Models\HarvestLinkEvent;
use App\Models\Member;
use App\Models\Pledge;
use App\Models\PledgePayment;
use App\Models\PledgePurpose;
use App\Models\Transaction;
use App\Services\Harvest\HarvestLinkException;
use App\Services\Harvest\NameMatcher;
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
        $query = Pledge::where('harvest_id', $harvest->id)->where('status', '!=', 'Cancelled')->orderBy('id');

        if ($memberId) {
            return $query->where('member_id', $memberId)->get();
        }
        if (NameMatcher::words($payerName) === []) {
            return collect();
        }

        return $query->whereNull('member_id')->get()->filter(fn (Pledge $p) => NameMatcher::same($p->pledger_name, $payerName))->values();
    }

    /**
     * The payer's own pledges on the harvest, and name-only pledges (imported with just a
     * pledger name) that match the member by name and so may well be theirs.
     *
     * @return array{own: Collection<int, Pledge>, name: Collection<int, Pledge>}
     */
    public function candidates(Harvest $harvest, ?int $memberId, ?string $payerName): array
    {
        $name = collect();
        if ($memberId && ($member = Member::find($memberId))) {
            $name = Pledge::where('harvest_id', $harvest->id)->where('status', '!=', 'Cancelled')->whereNull('member_id')->orderBy('id')->get()
                ->filter(fn (Pledge $p) => ! $p->isGiver() && NameMatcher::matchesMember($member, $p->pledger_name))->values();
        }

        return ['own' => $this->pledgesFor($harvest, $memberId, $payerName), 'name' => $name];
    }

    /**
     * Which pledge this money belongs to. $chosen: a pledge id, 'giver', or null (decide).
     * The payer's own single pledge is used automatically; a pledge that only matches by
     * name must be chosen by a person.
     *
     * @return array{pledge: ?Pledge, giver: bool, error: ?string}
     *         pledge null + giver true = a new giver pledge is needed.
     */
    public function resolve(Harvest $harvest, ?int $memberId, ?string $payerName, int|string|null $chosen = null): array
    {
        if (! $memberId && NameMatcher::words($payerName) === []) {
            return ['pledge' => null, 'giver' => false, 'error' => 'Select the member, or enter the payer name, for harvest money.'];
        }

        ['own' => $own, 'name' => $byName] = $this->candidates($harvest, $memberId, $payerName);
        $real  = $own->reject->isGiver()->values();
        $giver = $own->first(fn (Pledge $p) => $p->isGiver());

        if ($chosen === 'giver') {
            return ['pledge' => $giver, 'giver' => true, 'error' => null];
        }
        if ($chosen) {
            $pick = $own->merge($byName)->firstWhere('id', (int) $chosen);

            return $pick
                ? ['pledge' => $pick, 'giver' => $pick->isGiver(), 'error' => null]
                : ['pledge' => null, 'giver' => false, 'error' => 'That pledge is not on this harvest for this payer.'];
        }

        if ($real->count() === 1) {
            return ['pledge' => $real->first(), 'giver' => false, 'error' => null];
        }
        if ($real->count() > 1) {
            return ['pledge' => null, 'giver' => false, 'error' => 'This payer has more than one pledge on this harvest: choose which one the money is for.'];
        }
        if ($byName->isNotEmpty()) {
            return ['pledge' => null, 'giver' => false, 'error' => 'No pledge is recorded under this member, but '
                . $byName->pluck('reference')->implode(', ') . ' matches their name. Choose it to confirm (it will be put under the member), or choose "record as a harvest giver".'];
        }

        return ['pledge' => $giver, 'giver' => true, 'error' => null];
    }

    /**
     * Record the transaction as a payment on the right pledge (Record / Edit Transaction form).
     * Throws if it can't be resolved (callers validate with resolve() first).
     */
    public function link(Transaction $transaction, Harvest $harvest, int|string|null $chosenPledgeId = null, ?string $note = null): PledgePayment
    {
        return DB::transaction(function () use ($transaction, $harvest, $chosenPledgeId, $note) {
            $resolved = $this->resolve($harvest, $transaction->member_id, $transaction->payer_name, $chosenPledgeId);
            if ($resolved['error']) {
                throw new HarvestLinkException($resolved['error']);
            }

            $target = $resolved['pledge'] && ! $resolved['pledge']->isGiver() ? $resolved['pledge'] : 'giver';

            return $this->linkTo($transaction, $harvest, $target, auth()->id() ?? $transaction->recorded_by, HarvestLinkEvent::SOURCE_FINANCE_FORM, $note);
        });
    }

    /**
     * Link a transaction to the pledge Finance chose ('giver' = the payer's giver pledge, made if
     * needed). The payment points at the existing transaction, so no transaction or ledger entry
     * is created. A name-only pledge that matches the member by name is given the member.
     */
    public function linkTo(Transaction $transaction, Harvest $harvest, Pledge|string $target, ?int $userId, string $source, ?string $note = null): PledgePayment
    {
        return DB::transaction(function () use ($transaction, $harvest, $target, $userId, $source, $note) {
            // Lock the transaction so two people can't link it at once
            $tx = Transaction::whereKey($transaction->id)->lockForUpdate()->first();
            if (! $tx) {
                throw new HarvestLinkException("{$transaction->reference} has been voided.");
            }
            if ($tx->type !== 'Harvest' || $tx->status !== 'Confirmed') {
                throw new HarvestLinkException("{$tx->reference} is no longer a Confirmed Harvest transaction.");
            }
            if (PledgePayment::where('transaction_id', $tx->id)->exists()) {
                throw new HarvestLinkException("{$tx->reference} is already linked to a pledge.");
            }
            if ($harvest->status === 'Cancelled') {
                throw new HarvestLinkException("{$harvest->name} is cancelled.");
            }

            $giverCreated = $memberClaimed = false;
            if ($target === 'giver') {
                $pledge = $this->pledgesFor($harvest, $tx->member_id, $tx->payer_name)->first(fn (Pledge $p) => $p->isGiver());
                if (! $pledge) {
                    $pledge = $this->newGiverPledge($harvest, $tx);
                    $giverCreated = true;
                }
            } else {
                $pledge = Pledge::whereKey($target->id)->lockForUpdate()->first();
                if (! $pledge || $pledge->status === 'Cancelled' || (int) $pledge->harvest_id !== (int) $harvest->id) {
                    throw new HarvestLinkException("{$target->reference} is not an open pledge on {$harvest->name}.");
                }
                $memberClaimed = $this->claimIfNameMatch($pledge, $tx);
            }

            // transaction_id is set up front, so PledgePayment's created hook does NOT
            // make another transaction or ledger entry. It tops up a giver pledge and
            // refreshes the pledge status.
            $payment = PledgePayment::create([
                'pledge_id'      => $pledge->id,
                'amount'         => $tx->amount,
                'payment_date'   => $tx->transaction_date,
                'payment_method' => $tx->payment_method,
                'transaction_id' => $tx->id,
                'notes'          => $note ?? "Recorded in Finance as {$tx->reference}",
                'recorded_by'    => $userId ?? $tx->recorded_by,
            ]);

            HarvestLinkEvent::create([
                'action' => 'link', 'source' => $source, 'transaction_id' => $tx->id, 'pledge_payment_id' => $payment->id,
                'pledge_id' => $pledge->id, 'harvest_id' => $harvest->id, 'giver_created' => $giverCreated,
                'member_claimed' => $memberClaimed, 'user_id' => $userId,
            ]);

            return $payment;
        });
    }

    /** Remove the pledge payment(s) made through this transaction (form edits); the transaction stays. */
    public function unlink(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            PledgePayment::with('pledge')->where('transaction_id', $transaction->id)->get()->each(function (PledgePayment $payment) use ($transaction) {
                $this->removePayment($payment, $transaction->id, auth()->id(), HarvestLinkEvent::SOURCE_FINANCE_FORM, false);
            });
        });
    }

    /**
     * Undo a link made on the review page: the pledge payment goes (and an emptied giver pledge),
     * the transaction and its ledger entry stay. A member given to a name-only pledge by that
     * link is taken off again.
     */
    public function unlinkPayment(PledgePayment $payment, ?int $userId): void
    {
        DB::transaction(function () use ($payment, $userId) {
            $payment = PledgePayment::with('pledge')->whereKey($payment->id)->lockForUpdate()->first();
            $link = $payment ? HarvestLinkEvent::activeReviewLink($payment->id) : null;
            if (! $link) {
                throw new HarvestLinkException('Only links made on the Link payments page can be undone here.');
            }

            $unclaim = false;
            if ($link->member_claimed && $payment->pledge) {
                $tx = Transaction::withTrashed()->find($payment->transaction_id);
                $unclaim = $tx && (int) $payment->pledge->member_id === (int) $tx->member_id;
            }

            $this->removePayment($payment, $payment->transaction_id, $userId, HarvestLinkEvent::SOURCE_REVIEW, $unclaim);
        });
    }

    private function removePayment(PledgePayment $payment, ?int $transactionId, ?int $userId, string $source, bool $unclaim): void
    {
        $pledge = $payment->pledge;
        if ($unclaim && $pledge) {
            $pledge->member_id = null;
            $pledge->save();
        }

        $payment->keepTransaction = true;
        $payment->delete();   // hooks: giver pledge reduced (or removed when emptied), status refreshed

        HarvestLinkEvent::create([
            'action' => 'unlink', 'source' => $source, 'transaction_id' => $transactionId, 'pledge_payment_id' => $payment->id,
            'pledge_id' => $pledge?->id, 'harvest_id' => $pledge?->harvest_id, 'member_claimed' => $unclaim, 'user_id' => $userId,
        ]);
    }

    /** A name-only pledge that matches the paying member by name becomes that member's. */
    private function claimIfNameMatch(Pledge $pledge, Transaction $tx): bool
    {
        if ($tx->member_id) {
            if ((int) $pledge->member_id === (int) $tx->member_id) {
                return false;
            }
            if ($pledge->member_id === null && ($member = Member::find($tx->member_id)) && NameMatcher::matchesMember($member, $pledge->pledger_name)) {
                $pledge->member_id = $member->id;
                $pledge->save();

                return true;
            }
        } elseif ($pledge->member_id === null && NameMatcher::same($pledge->pledger_name, $tx->payer_name)) {
            return false;
        }

        throw new HarvestLinkException("{$pledge->reference} is not this payer's pledge.");
    }

    /**
     * After a transaction is saved: make its pledge payment match it, or remove it when the
     * transaction is no longer Confirmed harvest money.
     */
    public function sync(Transaction $transaction, ?Harvest $harvest, int|string|null $chosenPledgeId = null): ?PledgePayment
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

    private function stillFits(PledgePayment $payment, Transaction $transaction, Harvest $harvest, int|string|null $chosenPledgeId): bool
    {
        $pledge = $payment->pledge;
        if (! $pledge || $pledge->status === 'Cancelled' || (int) $pledge->harvest_id !== (int) $harvest->id) {
            return false;
        }
        if ($chosenPledgeId === 'giver' ? ! $pledge->isGiver() : ($chosenPledgeId && (int) $chosenPledgeId !== (int) $pledge->id)) {
            return false;
        }

        return $transaction->member_id
            ? (int) $pledge->member_id === (int) $transaction->member_id
            : $pledge->member_id === null && NameMatcher::same($pledge->pledger_name, $transaction->payer_name);
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

<?php

namespace App\Services\Harvest;

use App\Models\Harvest;
use App\Models\Pledge;
use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * Suggests the harvest campaign and pledge for Finance "Harvest" transactions. It only
 * suggests: Finance confirms every link on the review page.
 *
 * Campaign: the year written in the description ("HARVEST 2025", "harvest 25") picks the
 * harvest of that year with "Harvest" in its name. With no year it falls back to the date
 * (flagged "by date"). Disagreements are flagged, never silently resolved.
 *
 * Pledge (on that campaign): the payer's own pledge; else a name-only pledge whose name
 * matches the member (flagged "name match"); else a new (or the existing) giver pledge.
 */
class HarvestSuggester
{
    public const FLAGS = [
        'by_date'            => 'Campaign from the date: check',
        'year_mismatch'      => 'Year in the description differs from the date',
        'two_years'          => 'Description mentions more than one year',
        'no_campaign'        => 'No harvest campaign found',
        'multiple_campaigns' => 'More than one campaign for that year',
        'overlap'            => 'Date is inside more than one campaign',
        'name_match'         => 'Pledge matched by name: check',
        'multiple_pledges'   => 'More than one possible pledge',
        'overpay'            => 'Would take the pledge over its pledged amount',
        'no_payer'           => 'No member or payer name',
    ];

    /** @var Collection<int, Harvest>|null */
    private ?Collection $harvests = null;

    /** @var array<int, Collection<int, Pledge>> non-cancelled pledges per harvest id, with payments_sum_amount */
    private array $pledges = [];

    /**
     * Years written next to "harvest" in a description: "HARVEST 2025", "harvest 25",
     * "harvest pledge for 2026", "2026 HARVEST". Dates such as 24/07/26 are ignored.
     *
     * @return list<int>
     */
    public static function descriptionYears(?string $description): array
    {
        $text  = (string) $description;
        $years = [];
        $year  = '(?<![\d\/.\-])(20\d{2}|\d{2})(?![\d\/.\-])';

        if (preg_match_all('/harvest[^a-z0-9]*(?:[a-z]+[^a-z0-9]+){0,3}?' . $year . '/i', $text, $m)) {
            $years = [...$years, ...$m[1]];
        }
        if (preg_match_all('/(?<![\d\/.\-])(20\d{2})[^a-z0-9]*harvest/i', $text, $m)) {
            $years = [...$years, ...$m[1]];
        }

        $years = array_map(fn ($y) => strlen($y) === 2 ? 2000 + (int) $y : (int) $y, $years);
        $years = array_values(array_unique(array_filter($years, fn ($y) => $y >= 2015 && $y <= 2099)));
        sort($years);

        return $years;
    }

    /**
     * @param  Collection<int, Transaction>  $transactions  (member relation loaded or loadable)
     * @return Collection<int, HarvestSuggestion> keyed by transaction id
     */
    public function suggest(Collection $transactions): Collection
    {
        $transactions = (new \Illuminate\Database\Eloquent\Collection($transactions->all()))->loadMissing('member');
        $this->load();

        return $transactions->mapWithKeys(fn (Transaction $tx) => [$tx->id => $this->suggestOne($tx)]);
    }

    public function suggestOne(Transaction $tx): HarvestSuggestion
    {
        $this->load();
        [$harvest, $basis, $year, $campaignFlags] = $this->campaign($tx);

        $options = $choices = $pledgeFlags = [];
        foreach ($this->harvests as $h) {
            [$options[$h->id], $choices[$h->id], $pledgeFlags[$h->id]] = $this->pledgeOptions($tx, $h);
        }

        return new HarvestSuggestion($tx, $harvest, $basis, $year, $options, $choices, $pledgeFlags, $campaignFlags);
    }

    /** Campaigns staff may pick on the review page. @return Collection<int, Harvest> */
    public function harvests(): Collection
    {
        $this->load();

        return $this->harvests;
    }

    /** Forget cached pledges (after links change them). */
    public function refresh(): void
    {
        $this->harvests = null;
        $this->pledges  = [];
    }

    /** @return array{0: ?Harvest, 1: string, 2: ?int, 3: list<string>} */
    private function campaign(Transaction $tx): array
    {
        $years = self::descriptionYears($tx->description);

        if (count($years) > 1) {
            return [null, 'none', null, ['two_years']];
        }

        if (count($years) === 1) {
            $year  = $years[0];
            $named = $this->harvests->filter(fn (Harvest $h) => (int) $h->year === $year && stripos($h->name, 'harvest') !== false)->values();
            $flags = $year !== (int) $tx->transaction_date->year ? ['year_mismatch'] : [];

            if ($named->count() === 1) {
                return [$named->first(), 'description', $year, $flags];
            }

            return [null, 'none', $year, [...$flags, $named->isEmpty() ? 'no_campaign' : 'multiple_campaigns']];
        }

        $covering = $this->harvests->filter(fn (Harvest $h) => $h->coversDate($tx->transaction_date))->values();
        $flags    = $covering->count() > 1 ? ['overlap'] : [];
        $pick     = $covering->count() === 1
            ? $covering->first()
            : (($named = $covering->filter(fn (Harvest $h) => stripos($h->name, 'harvest') !== false))->count() === 1 ? $named->first() : null);

        return $pick
            ? [$pick, 'date', null, ['by_date', ...$flags]]
            : [null, 'none', null, [...$flags, $covering->isEmpty() ? 'no_campaign' : 'multiple_campaigns']];
    }

    /**
     * The payer's pledge options on one harvest, the suggested one, and pledge flags.
     *
     * @return array{0: list<array<string, mixed>>, 1: ?string, 2: list<string>}
     */
    private function pledgeOptions(Transaction $tx, Harvest $harvest): array
    {
        $member = $tx->member;
        $payer  = trim((string) $tx->payer_name);
        if (! $member && $payer === '') {
            return [[], null, ['no_payer']];
        }

        $pledges = $this->pledges[$harvest->id] ?? collect();

        // The payer's own pledges: by member, or by payer name when there is no member
        $own = $member
            ? $pledges->filter(fn (Pledge $p) => (int) $p->member_id === (int) $member->id)
            : $pledges->filter(fn (Pledge $p) => $p->member_id === null && NameMatcher::same($p->pledger_name, $payer));
        // Name-only pledges that look like this member's (imported with pledger_name only)
        $byName = $member
            ? $pledges->filter(fn (Pledge $p) => $p->member_id === null && ! $p->isGiver() && NameMatcher::matchesMember($member, $p->pledger_name))
            : collect();

        $direct = $own->reject->isGiver()->values();
        $giver  = $own->first(fn (Pledge $p) => $p->isGiver());

        $options = [];
        foreach ($direct as $p) {
            $options[] = $this->option($p, $member ? 'member' : 'payer');
        }
        foreach ($byName as $p) {
            $options[] = $this->option($p, 'name');
        }
        $options[] = [
            'value' => 'giver', 'match' => 'giver', 'reference' => $giver?->reference,
            'label' => $giver ? "Add to giver pledge {$giver->reference}" : 'New giver pledge',
        ];

        $flags = [];
        if ($direct->count() === 1) {
            $choice = (string) $direct->first()->id;
        } elseif ($direct->count() > 1) {
            $choice = null;
            $flags[] = 'multiple_pledges';
        } elseif ($byName->count() === 1) {
            $choice = (string) $byName->first()->id;
            $flags[] = 'name_match';
        } elseif ($byName->count() > 1) {
            $choice = null;
            $flags = ['name_match', 'multiple_pledges'];
        } else {
            $choice = 'giver';
        }

        if ($choice !== null && $choice !== 'giver') {
            $p = $pledges->firstWhere('id', (int) $choice);
            if ((float) $p->payments_sum_amount + (float) $tx->amount > (float) $p->amount_pledged + 0.005) {
                $flags[] = 'overpay';
            }
        }

        return [$options, $choice, $flags];
    }

    private function option(Pledge $p, string $match): array
    {
        $paid = (float) $p->payments_sum_amount;

        return [
            'value'     => (string) $p->id,
            'match'     => $match,
            'reference' => $p->reference,
            'pledged'   => (float) $p->amount_pledged,
            'paid'      => $paid,
            'balance'   => max(0, (float) $p->amount_pledged - $paid),
            'label'     => $p->reference . ($match === 'name' ? ' (name match: check)' : ''),
        ];
    }

    private function load(): void
    {
        if ($this->harvests !== null) {
            return;
        }

        $this->harvests = Harvest::where('status', '!=', 'Cancelled')->orderByDesc('year')->orderBy('name')->get();
        $this->pledges  = Pledge::whereIn('harvest_id', $this->harvests->pluck('id'))
            ->where('status', '!=', 'Cancelled')
            ->withSum('payments', 'amount')
            ->orderBy('id')
            ->get()
            ->groupBy('harvest_id')
            ->all();
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Harvest;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Pledge;
use App\Models\PledgePayment;
use App\Models\Transaction;
use App\Services\HarvestLinker;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * One-time repair: harvest money recorded in Finance as type "Harvest" before transactions
 * were linked to pledges. Each such transaction becomes a payment on the payer's pledge for
 * the harvest (or on a "giver pledge" when they made none). The payment points at the
 * existing transaction, so no transaction or ledger entry is created and Finance totals do
 * not move: the command checks this and rolls everything back if they do.
 *
 * Without --apply it is a dry run: nothing changes, the CSV shows what would happen.
 * The console shows counts and references only; names and amounts are in the CSV.
 */
class HarvestLinkTransactions extends Command
{
    protected $signature = 'harvest:link-transactions
                            {--harvest= : The harvest id (required)}
                            {--apply : Make the changes (without it: dry run)}';

    protected $description = 'Link Finance "Harvest" transactions to the payers\' pledges on a harvest (dry run unless --apply)';

    private const SKIP_OVERLAP  = 'date is also inside another harvest';
    private const SKIP_NO_PAYER = 'no member and no payer name';
    private const SKIP_MULTIPLE = 'more than one pledge on this harvest';
    private const SKIP_OVERPAY  = 'would take the pledge over its pledged amount';

    public function handle(HarvestLinker $linker): int
    {
        $harvest = Harvest::find($this->option('harvest'));
        if (! $harvest) {
            $this->error('Give an existing harvest: --harvest=ID');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $this->line(sprintf('Harvest #%d (%d), period %s..%s — %s',
            $harvest->id, $harvest->year, $harvest->periodStart()->toDateString(), $harvest->periodEnd()->toDateString(),
            $apply ? 'APPLYING' : 'DRY RUN (nothing will change)'));

        try {
            $rows = $apply
                ? DB::transaction(fn () => $this->apply($harvest, $linker))
                : $this->plan($harvest, $linker);
        } catch (\Throwable $e) {
            $this->error('Stopped, nothing was changed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $path = $this->writeCsv($harvest, $rows, $apply);
        $this->report($rows, $path, $apply);

        return self::SUCCESS;
    }

    /**
     * What to do with each unlinked Confirmed "Harvest" transaction in the harvest's period.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function plan(Harvest $harvest, HarvestLinker $linker): Collection
    {
        $linked = PledgePayment::whereNotNull('transaction_id')->pluck('transaction_id');
        $others = Harvest::where('id', '!=', $harvest->id)->where('status', '!=', 'Cancelled')->get();

        $transactions = Transaction::with('member')
            ->where('type', 'Harvest')
            ->where('status', 'Confirmed')
            ->whereNotIn('id', $linked)
            ->whereBetween('transaction_date', [$harvest->periodStart()->toDateString(), $harvest->periodEnd()->toDateString()])
            ->orderBy('transaction_date')->orderBy('id')
            ->get();

        $rows = $transactions->map(function (Transaction $tx) use ($harvest, $linker, $others) {
            $row = ['tx' => $tx, 'action' => 'skipped', 'reason' => '', 'pledge' => null];

            if ($other = $others->first(fn (Harvest $h) => $h->coversDate($tx->transaction_date))) {
                return ['reason' => self::SKIP_OVERLAP . " (#{$other->id})"] + $row;
            }
            if (! $tx->member_id && HarvestLinker::normaliseName($tx->payer_name) === '') {
                return ['reason' => self::SKIP_NO_PAYER] + $row;
            }

            $pledges = $linker->pledgesFor($harvest, $tx->member_id, $tx->payer_name);
            $real = $pledges->reject->isGiver()->values();

            if ($real->count() > 1) {
                return ['reason' => self::SKIP_MULTIPLE] + $row;
            }
            if ($real->count() === 1) {
                return ['action' => 'linked', 'pledge' => $real->first()] + $row;
            }

            return ['action' => 'giver', 'pledge' => $pledges->first(fn (Pledge $p) => $p->isGiver())] + $row;
        });

        // A pledge that the planned links would overpay: leave all its transactions for review.
        $over = $rows->where('action', 'linked')->groupBy(fn ($r) => $r['pledge']->id)
            ->filter(function (Collection $group) {
                $pledge = $group->first()['pledge'];

                return $pledge->total_paid + $group->sum(fn ($r) => (float) $r['tx']->amount) > (float) $pledge->amount_pledged + 0.005;
            })->keys();

        return $rows->map(fn ($r) => $r['action'] === 'linked' && $over->contains($r['pledge']->id)
            ? ['action' => 'skipped', 'reason' => self::SKIP_OVERPAY] + $r
            : $r)->values();
    }

    /** Carry out the plan; runs inside one DB transaction. */
    private function apply(Harvest $harvest, HarvestLinker $linker): Collection
    {
        $before = $this->financeSnapshot();

        $rows = $this->plan($harvest, $linker)->map(function (array $row) use ($harvest, $linker) {
            if ($row['action'] === 'skipped') {
                return $row;
            }
            $tx = $row['tx'];
            // Linked: the planned pledge. Giver: the linker finds (or makes) the payer's giver pledge.
            $payment = $linker->link($tx, $harvest, $row['action'] === 'linked' ? $row['pledge']->id : null,
                "Linked from Finance transaction {$tx->reference}");

            return ['pledge' => $payment->pledge] + $row;
        });

        $after = $this->financeSnapshot();
        if ($before !== $after) {
            throw new \RuntimeException('Finance totals or journal entries changed (' . json_encode(['before' => $before, 'after' => $after]) . '); rolled back.');
        }

        return $rows;
    }

    /** What must not move: income, transaction count and the journal. */
    private function financeSnapshot(): array
    {
        return [
            'income'        => number_format((float) Transaction::where('category', 'Income')->sum('amount'), 2, '.', ''),
            'expense'       => number_format((float) Transaction::where('category', 'Expense')->sum('amount'), 2, '.', ''),
            'transactions'  => Transaction::withTrashed()->count(),
            'journals'      => JournalEntry::count(),
            'journal_lines' => JournalLine::count(),
        ];
    }

    private function writeCsv(Harvest $harvest, Collection $rows, bool $applied): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['transaction', 'date', 'payer', 'amount', 'action', 'pledge', 'reason']);
        foreach ($rows as $r) {
            $tx = $r['tx'];
            fputcsv($out, [
                $tx->reference,
                $tx->transaction_date->toDateString(),
                $tx->member?->full_name ?? $tx->payer_name,
                number_format((float) $tx->amount, 2, '.', ''),
                $r['action'],
                $r['pledge']?->reference ?? ($r['action'] === 'giver' ? '(new giver pledge)' : ''),
                $r['reason'],
            ]);
        }
        rewind($out);

        $path = sprintf('harvest-link/%d-%s-%s.csv', $harvest->id, now()->format('Ymd-His'), $applied ? 'applied' : 'dry-run');
        Storage::disk('local')->put($path, stream_get_contents($out));
        fclose($out);

        return $path;
    }

    private function report(Collection $rows, string $path, bool $applied): void
    {
        $linked = $rows->where('action', 'linked');
        $giver  = $rows->where('action', 'giver');
        $verb   = $applied ? '' : 'would be ';

        $this->newLine();
        $this->line("Transactions considered: {$rows->count()}");
        $this->line("  {$verb}linked to the payer's pledge: {$linked->count()} (pledges: " . $linked->map(fn ($r) => $r['pledge']->id)->unique()->count() . ')');
        $this->line("  {$verb}recorded as harvest givers:   {$giver->count()} (givers: " . $giver->map(fn ($r) => $r['tx']->member_id ?: 'n:' . HarvestLinker::normaliseName($r['tx']->payer_name))->unique()->count() . ')');
        $this->line("  skipped for review:              {$rows->where('action', 'skipped')->count()}");
        foreach ([self::SKIP_OVERLAP, self::SKIP_NO_PAYER, self::SKIP_MULTIPLE, self::SKIP_OVERPAY] as $reason) {
            $n = $rows->filter(fn ($r) => str_starts_with($r['reason'], $reason))->count();
            $this->line(sprintf('    %-46s %d', $reason . ':', $n));
        }
        $this->newLine();
        $this->line('CSV (names and amounts, for Finance): storage/app/private/' . $path);

        $orphans = Pledge::whereNull('harvest_id')->whereHas('purpose', fn ($q) => $q->where('name', 'like', '%harvest%'))->orderBy('id')->pluck('reference');
        $this->newLine();
        $this->line("Harvest-purpose pledges with no harvest set (fix by hand): {$orphans->count()}");
        if ($orphans->isNotEmpty()) {
            $this->line('  ' . $orphans->implode(', '));
        }
    }
}

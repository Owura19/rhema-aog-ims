<?php

namespace App\Console\Commands;

use App\Models\Harvest;
use App\Models\HarvestLinkEvent;
use App\Models\Pledge;
use App\Models\PledgePayment;
use App\Models\Transaction;
use App\Services\Harvest\HarvestSuggester;
use App\Services\Harvest\HarvestSuggestion;
use App\Services\Harvest\PledgeStatusChecks;
use Illuminate\Console\Command;

/**
 * READ-ONLY harvest report: what is waiting on Harvests → Link payments, what has been linked,
 * and pledges whose status doesn't match their payments. Counts and references only: no
 * names, no amounts. Changes nothing (linking is done by Finance on the review page).
 */
class HarvestReport extends Command
{
    protected $signature = 'harvest:report {--harvest= : Limit the pledge checks to one harvest id}';

    protected $description = 'Read-only: harvest payments waiting to be linked, links made, and pledge status checks (counts and references only)';

    public function handle(HarvestSuggester $suggester): int
    {
        $harvestId = $this->option('harvest');
        if ($harvestId !== null && ! Harvest::whereKey($harvestId)->exists()) {
            $this->error('No harvest with that id.');

            return self::FAILURE;
        }

        $this->line('Harvest report (read-only, nothing is changed) — ' . now()->toDateTimeString());

        // Campaigns
        $this->newLine();
        $this->line('Campaigns:');
        foreach (Harvest::orderBy('id')->get() as $h) {
            $this->line(sprintf('  #%d %d %-9s %s..%s  pledges %d (name only %d)', $h->id, $h->year, $h->status,
                $h->periodStart()->toDateString(), $h->periodEnd()->toDateString(),
                Pledge::where('harvest_id', $h->id)->count(), Pledge::where('harvest_id', $h->id)->whereNull('member_id')->count()));
        }

        // Waiting to be linked
        $unlinked = Transaction::where('type', 'Harvest')->where('status', 'Confirmed')
            ->whereNotIn('id', PledgePayment::whereNotNull('transaction_id')->select('transaction_id'))->get();
        $suggestions = $suggester->suggest($unlinked);

        $this->newLine();
        $this->line("Harvest transactions waiting to be linked: {$suggestions->count()} (without flags: {$suggestions->filter->isClean()->count()})");
        foreach ($suggestions->groupBy(fn (HarvestSuggestion $s) => $s->harvest?->id ?? 0)->sortKeys() as $id => $group) {
            $label = $id ? "#{$id}" : 'no campaign suggested';
            $this->line(sprintf('  suggested %-22s %d (from description %d, by date %d)', $label . ':', $group->count(),
                $group->where('basis', 'description')->count(), $group->where('basis', 'date')->count()));
        }
        $flagCounts = $suggestions->flatMap->flags()->countBy();
        foreach (HarvestSuggester::FLAGS as $key => $label) {
            $this->line(sprintf('  flag %-48s %d', $label . ':', $flagCounts[$key] ?? 0));
        }

        // Links made
        $live = PledgePayment::whereNotNull('transaction_id')->pluck('id');
        $this->newLine();
        $this->line('Links in place: ' . $live->count() . ' pledge payments point at a Finance transaction');
        if (! \Illuminate\Support\Facades\Schema::hasTable('harvest_link_events')) {
            $this->warn('  harvest_link_events table missing: run php artisan migrate first (link history not shown).');
        } else {
            foreach ([HarvestLinkEvent::SOURCE_REVIEW => 'from the review page', HarvestLinkEvent::SOURCE_FINANCE_FORM => 'from the Finance form'] as $source => $label) {
                $made = HarvestLinkEvent::where('action', 'link')->where('source', $source)->whereIn('pledge_payment_id', $live)->count();
                $this->line(sprintf('  %-24s %d', $label . ':', $made));
            }
            $this->line('  link / unlink events recorded: ' . HarvestLinkEvent::where('action', 'link')->count() . ' / ' . HarvestLinkEvent::where('action', 'unlink')->count());
        }

        // Status checks (report only)
        $this->newLine();
        $this->line('Pledge status checks' . ($harvestId ? " (harvest #{$harvestId})" : '') . ' — report only:');
        foreach (PledgeStatusChecks::run($harvestId ? (int) $harvestId : null) as $key => $pledges) {
            $this->line(sprintf('  %-32s %d', PledgeStatusChecks::CHECKS[$key] . ':', $pledges->count()));
            if ($pledges->isNotEmpty()) {
                $this->line('    ' . $pledges->pluck('reference')->implode(', '));
            }
        }

        $loose = Pledge::whereNull('harvest_id')->whereHas('purpose', fn ($q) => $q->where('name', 'like', '%harvest%'))->orderBy('reference')->pluck('reference');
        $this->newLine();
        $this->line("Harvest-purpose pledges with no harvest set (fix by hand): {$loose->count()}");
        if ($loose->isNotEmpty()) {
            $this->line('  ' . $loose->implode(', '));
        }

        return self::SUCCESS;
    }
}

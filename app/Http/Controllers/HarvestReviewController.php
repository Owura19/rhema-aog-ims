<?php

namespace App\Http\Controllers;

use App\Models\HarvestLinkEvent;
use App\Models\PledgePayment;
use App\Models\Transaction;
use App\Services\Harvest\FinanceSnapshot;
use App\Services\Harvest\HarvestLinkException;
use App\Services\Harvest\HarvestSuggester;
use App\Services\Harvest\HarvestSuggestion;
use App\Services\Harvest\NameMatcher;
use App\Services\Harvest\PledgeStatusChecks;
use App\Services\HarvestLinker;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Harvests → Link payments: Finance confirms which campaign and pledge each unlinked
 * "Harvest" transaction belongs to. The system only suggests.
 */
class HarvestReviewController extends Controller
{
    private const PER_PAGE = 50;

    public function index(Request $request, HarvestSuggester $suggester)
    {
        $filters = $request->validate([
            'year'    => 'nullable|integer|min:2000|max:2100',
            'harvest' => 'nullable|string|max:10',     // a harvest id, or "none"
            'flag'    => 'nullable|string|max:30',     // a flag key, or "clean"
        ]);

        $unlinked = $this->unlinkedQuery()
            ->when($filters['year'] ?? null, fn ($q, $y) => $q->whereYear('transaction_date', $y))
            ->get();
        $suggestions = $suggester->suggest($unlinked)
            ->filter(fn (HarvestSuggestion $s) => $this->matchesFilters($s, $filters))
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $rows = new LengthAwarePaginator($suggestions->forPage($page, self::PER_PAGE)->values(), $suggestions->count(), self::PER_PAGE, $page,
            ['path' => $request->url(), 'query' => $request->query()]);

        $years    = $this->unlinkedQuery()->selectRaw('DISTINCT YEAR(transaction_date) AS y')->reorder()->orderByDesc('y')->pluck('y');
        $linked   = $this->reviewLinks();
        $checks   = PledgeStatusChecks::run();
        $harvests = $suggester->harvests();
        $flags    = HarvestSuggester::FLAGS;
        $total    = $suggestions->count();
        $clean    = $suggestions->filter->isClean()->count();

        return view('harvests.link-payments', compact('rows', 'years', 'linked', 'checks', 'harvests', 'flags', 'filters', 'total', 'clean'));
    }

    /** Step 1 of "Link selected": show what will be added, per campaign, for Finance to confirm. */
    public function preview(Request $request, HarvestSuggester $suggester)
    {
        [$plan, $skipped] = $this->plan($request, $suggester);

        return view('harvests.link-payments-confirm', [
            'plan'    => $plan,
            'skipped' => $skipped,
            'summary' => $this->summary($plan),
            'input'   => [
                'selected' => $selected = array_map('intval', $request->input('selected', [])),
                'rows'     => array_intersect_key($request->input('rows', []), array_flip($selected)),
            ],
        ]);
    }

    /** Step 2: link everything confirmed, in one DB transaction; Finance totals must not move. */
    public function store(Request $request, HarvestSuggester $suggester, HarvestLinker $linker)
    {
        $request->validate(['confirmed' => 'accepted']);
        [$plan] = $this->plan($request, $suggester);

        try {
            DB::transaction(function () use ($plan, $linker) {
                $before = FinanceSnapshot::take();
                foreach ($plan as $row) {
                    $linker->linkTo($row['tx'], $row['harvest'], $row['pledge'] ?? 'giver', auth()->id(), HarvestLinkEvent::SOURCE_REVIEW);
                }
                if (FinanceSnapshot::take() !== $before) {
                    throw new HarvestLinkException('Finance totals would have changed, so nothing was linked. Please report this.');
                }
            });
        } catch (HarvestLinkException $e) {
            return redirect()->route('harvests.link-payments')->with('error', 'Nothing was linked: ' . $e->getMessage());
        }

        $n = count($plan);

        return redirect()->route('harvests.link-payments')
            ->with('success', $n . ' ' . ($n === 1 ? 'payment' : 'payments') . ' linked.');
    }

    public function unlink(PledgePayment $payment, HarvestLinker $linker)
    {
        $reference = optional(Transaction::withTrashed()->find($payment->transaction_id))->reference;

        try {
            $linker->unlinkPayment($payment, auth()->id());
        } catch (HarvestLinkException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Unlinked {$reference}. The transaction is unchanged.");
    }

    // ── helpers ────────────────────────────────────────────────────────

    private function unlinkedQuery()
    {
        return Transaction::with('member')
            ->where('type', 'Harvest')
            ->where('status', 'Confirmed')
            ->whereNotIn('id', PledgePayment::whereNotNull('transaction_id')->select('transaction_id'))
            ->orderByDesc('transaction_date')->orderByDesc('id');
    }

    private function matchesFilters(HarvestSuggestion $s, array $filters): bool
    {
        $harvest = $filters['harvest'] ?? null;
        if ($harvest === 'none' && $s->harvest !== null) {
            return false;
        }
        if ($harvest && $harvest !== 'none' && (string) $s->harvest?->id !== $harvest) {
            return false;
        }

        $flag = $filters['flag'] ?? null;
        if ($flag === 'clean') {
            return $s->isClean();
        }

        return ! $flag || in_array($flag, $s->flags(), true);
    }

    /** Links made on this page that are still in place, newest first. */
    private function reviewLinks(): Collection
    {
        return PledgePayment::with(['pledge.harvest', 'transaction.member'])
            ->whereIn('id', HarvestLinkEvent::where('action', 'link')->where('source', HarvestLinkEvent::SOURCE_REVIEW)->select('pledge_payment_id'))
            ->latest('id')
            ->limit(200)
            ->get()
            ->filter(fn (PledgePayment $p) => HarvestLinkEvent::activeReviewLink($p->id) !== null)
            ->values();
    }

    /**
     * The selected rows as links to make, checked against the current suggestions. Any problem
     * sends Finance back to the list with the reasons; nothing is linked.
     *
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function plan(Request $request, HarvestSuggester $suggester): array
    {
        $request->validate([
            'selected'   => 'required|array|min:1',
            'selected.*' => 'integer',
            'rows'       => 'array',
        ], ['selected.required' => 'Tick at least one payment to link.']);

        $ids  = array_values(array_unique(array_map('intval', $request->input('selected'))));
        $txs  = $this->unlinkedQuery()->whereIn('id', $ids)->get()->keyBy('id');
        $sugg = $suggester->suggest($txs);
        $rows = $request->input('rows', []);

        $plan = $errors = [];
        $skipped = 0;
        $running = [];   // pledge id => amount added in this batch (for overpay)

        foreach ($ids as $id) {
            $tx = $txs->get($id);
            if (! $tx) {
                $errors[] = 'A selected payment is no longer waiting to be linked (already linked, voided or changed). Reload the page.';
                continue;
            }
            $choice = (string) ($rows[$id]['pledge'] ?? '');
            if ($choice === 'none') {
                $skipped++;
                continue;
            }

            $harvest = $suggester->harvests()->firstWhere('id', (int) ($rows[$id]['harvest_id'] ?? 0));
            if (! $harvest) {
                $errors[] = "{$tx->reference}: choose the harvest campaign.";
                continue;
            }
            $option = collect($sugg[$id]->optionsByHarvest[$harvest->id] ?? [])->firstWhere('value', $choice);
            if ($choice === '' || ! $option) {
                $errors[] = "{$tx->reference}: choose the pledge for {$harvest->name}" . ($choice === '' ? '.' : ' (that one is not among this payer\'s options).');
                continue;
            }

            $pledge  = $choice === 'giver' ? null : \App\Models\Pledge::find((int) $choice);
            $overpay = false;
            if ($pledge) {
                $running[$pledge->id] = ($running[$pledge->id] ?? 0) + (float) $tx->amount;
                $overpay = $option['paid'] + $running[$pledge->id] > $option['pledged'] + 0.005;
            }

            $plan[] = [
                'tx'        => $tx,
                'harvest'   => $harvest,
                'pledge'    => $pledge,
                'option'    => $option,
                'newGiver'  => $choice === 'giver' && empty($option['reference']),
                'giverKey'  => $tx->member_id ? 'm' . $tx->member_id : 'n' . NameMatcher::key($tx->payer_name),
                'nameMatch' => $option['match'] === 'name',
                'overpay'   => $overpay,
            ];
        }

        if ($errors) {
            throw \Illuminate\Validation\ValidationException::withMessages(['selected' => array_values(array_unique($errors))])
                ->redirectTo(route('harvests.link-payments'));
        }
        if (! $plan) {
            throw \Illuminate\Validation\ValidationException::withMessages(['selected' => "All the ticked payments are set to \"Don't link\"."])
                ->redirectTo(route('harvests.link-payments'));
        }

        return [$plan, $skipped];
    }

    /** Per campaign: payments, total amount, new giver pledges, name matches, overpays. */
    private function summary(array $plan): Collection
    {
        return collect($plan)->groupBy(fn ($r) => $r['harvest']->id)->map(fn (Collection $rows) => [
            'harvest'    => $rows->first()['harvest'],
            'payments'   => $rows->count(),
            'amount'     => $rows->sum(fn ($r) => (float) $r['tx']->amount),
            'newGivers'  => $rows->where('newGiver', true)->pluck('giverKey')->unique()->count(),
            'nameMatch'  => $rows->where('nameMatch', true)->count(),
            'overpay'    => $rows->where('overpay', true)->count(),
        ])->values();
    }
}

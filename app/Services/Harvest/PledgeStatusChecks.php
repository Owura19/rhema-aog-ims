<?php

namespace App\Services\Harvest;

use App\Models\Pledge;
use Illuminate\Support\Collection;

/**
 * Pledges whose status doesn't match their payments (report only — nothing is changed).
 * Imported payments were written without refreshing the pledge status, so some fully
 * paid pledges still say Active.
 */
final class PledgeStatusChecks
{
    public const CHECKS = [
        'active_but_paid'         => 'Active but fully paid',
        'fulfilled_but_short'     => 'Fulfilled but not fully paid',
        'cancelled_with_payments' => 'Cancelled but has payments',
        'overpaid'                => 'Paid more than pledged',
    ];

    /** @return array<string, Collection<int, Pledge>> per check, pledges ordered by reference */
    public static function run(?int $harvestId = null): array
    {
        $pledges = Pledge::query()
            ->when($harvestId, fn ($q) => $q->where('harvest_id', $harvestId))
            ->withSum('payments', 'amount')
            ->orderBy('reference')
            ->get(['id', 'reference', 'status', 'amount_pledged', 'harvest_id', 'notes']);

        $paid    = fn (Pledge $p) => (float) $p->payments_sum_amount;
        $pledged = fn (Pledge $p) => (float) $p->amount_pledged;

        return [
            'active_but_paid'         => $pledges->filter(fn ($p) => $p->status === 'Active' && $pledged($p) > 0 && $paid($p) >= $pledged($p) - 0.005)->values(),
            'fulfilled_but_short'     => $pledges->filter(fn ($p) => $p->status === 'Fulfilled' && $paid($p) < $pledged($p) - 0.005)->values(),
            'cancelled_with_payments' => $pledges->filter(fn ($p) => $p->status === 'Cancelled' && $paid($p) > 0)->values(),
            'overpaid'                => $pledges->filter(fn ($p) => $paid($p) > $pledged($p) + 0.005)->values(),
        ];
    }
}

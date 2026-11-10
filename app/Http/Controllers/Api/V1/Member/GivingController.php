<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\GivingResource;
use App\Models\Transaction;
use App\Support\Api\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * GET /me/giving?year=&type=&page=&per_page=
 * The member's own confirmed income (soft-deleted rows excluded), newest first,
 * plus a summary for the year (the filter, or the current year) that ignores
 * the type filter so the breakdown stays complete.
 */
class GivingController extends Controller
{
    /**
     * Income types a member can filter by: transactions.type minus 'Expense'.
     * Keep in step with the transactions.type enum (also used by the finance forms).
     */
    public const TYPES = ['Tithe', 'Offering', 'Project Offering', 'Harvest', 'First Fruit', 'Seed', 'Pledge', 'Donation', 'Other'];

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'year'     => ['nullable', 'integer', 'min:2000', 'max:' . (now()->year + 1)],
            'type'     => ['nullable', 'string', Rule::in(self::TYPES)],
            'page'     => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $memberId = $request->user()->member->id;

        $gifts = $this->mine($memberId)
            ->when(isset($data['year']), fn ($q) => $q->whereYear('transaction_date', $data['year']))
            ->when(isset($data['type']), fn ($q) => $q->where('type', $data['type']))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate((int) ($data['per_page'] ?? 20))
            ->withQueryString();

        return GivingResource::collection($gifts)
            ->additional(['summary' => $this->summary($memberId, (int) ($data['year'] ?? now()->year))])
            ->response();
    }

    /** The member's confirmed income; SoftDeletes already hides voided rows. */
    private function mine(int $memberId): Builder
    {
        return Transaction::query()
            ->where('member_id', $memberId)
            ->where('category', 'Income')
            ->where('status', 'Confirmed');
    }

    private function summary(int $memberId, int $year): array
    {
        $byType = $this->mine($memberId)
            ->whereYear('transaction_date', $year)
            ->selectRaw('type, SUM(amount) AS total, COUNT(*) AS gifts')
            ->groupBy('type')
            ->orderByDesc('total')
            ->orderBy('type')
            ->get();

        return [
            'year'           => $year,
            'year_total'     => Money::fromCents($byType->sum(fn ($row) => Money::toCents($row->total))),
            'by_type'        => $byType->map(fn ($row) => [
                'type'  => $row->type,
                'total' => Money::format($row->total),
                'count' => (int) $row->gifts,
            ])->values()->all(),
            'all_time_total' => Money::format($this->mine($memberId)->sum('amount')),
            'years'          => $this->mine($memberId)
                ->selectRaw('DISTINCT YEAR(transaction_date) AS y')
                ->orderByDesc('y')
                ->pluck('y')
                ->map(fn ($y) => (int) $y)
                ->all(),
        ];
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Http\Controllers\Controller;
use App\Support\Api\Money;
use App\Support\Giving\MemberGiving;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * GET /me/giving?year=&type=&page=&per_page=
 *
 * Everything the signed-in member has given (see MemberGiving: confirmed income plus
 * pledge payments that never became a transaction), newest first, and a summary for the
 * year (the filter, or the current year) that ignores the type filter so the breakdown
 * stays complete. month_total is the current calendar month, and only for the current
 * year (null when an earlier year is asked for).
 *
 * The member is always $request->user()->member: no parameter can point at anyone else.
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

        $giving = new MemberGiving($request->user()->member->id);

        $page = $giving->query()
            ->when(isset($data['year']), fn ($q) => $q->whereYear('date', $data['year']))
            ->when(isset($data['type']), fn ($q) => $q->where('type', $data['type']))
            ->orderByDesc('date')
            ->orderByDesc('source')   // 'transaction' before 'pledge_payment' on the same day
            ->orderByDesc('id')
            ->paginate((int) ($data['per_page'] ?? 20))
            ->withQueryString();

        return response()->json([
            'data'    => collect($page->items())->map(fn (object $row) => [
                'id'             => (int) $row->id,
                'source'         => $row->source,
                'reference'      => $row->reference,
                'receipt_number' => $row->receipt_number !== null && $row->receipt_number !== '' ? $row->receipt_number : null,
                'date'           => substr((string) $row->date, 0, 10),
                'type'           => $row->type,
                'amount'         => Money::format($row->amount),
                'currency'       => $row->currency ?: 'GHS',
                'payment_method' => $row->payment_method,
            ])->values(),
            'links'   => [
                'first' => $page->url(1),
                'last'  => $page->url($page->lastPage()),
                'prev'  => $page->previousPageUrl(),
                'next'  => $page->nextPageUrl(),
            ],
            'meta'    => [
                'current_page' => $page->currentPage(),
                'from'         => $page->firstItem(),
                'last_page'    => $page->lastPage(),
                'path'         => $page->path(),
                'per_page'     => $page->perPage(),
                'to'           => $page->lastItem(),
                'total'        => $page->total(),
            ],
            'summary' => $this->summary($giving, (int) ($data['year'] ?? now()->year)),
        ]);
    }

    private function summary(MemberGiving $giving, int $year): array
    {
        $byType = $giving->query()
            ->whereYear('date', $year)
            ->selectRaw('type, SUM(amount) AS total, COUNT(*) AS gifts')
            ->groupBy('type')
            ->orderByDesc('total')
            ->orderBy('type')
            ->get();

        $isCurrentYear = $year === now()->year;
        $monthTotal = $isCurrentYear
            ? Money::format($giving->query()->whereYear('date', $year)->whereMonth('date', now()->month)->sum('amount'))
            : null;

        return [
            'year'           => $year,
            'month'          => $isCurrentYear ? now()->format('Y-m') : null,
            'month_total'    => $monthTotal,
            'year_total'     => Money::fromCents($byType->sum(fn ($row) => Money::toCents($row->total))),
            'by_type'        => $byType->map(fn ($row) => [
                'type'  => $row->type,
                'total' => Money::format($row->total),
                'count' => (int) $row->gifts,
            ])->values()->all(),
            'all_time_total' => Money::format($giving->query()->sum('amount')),
            'years'          => $giving->query()
                ->selectRaw('DISTINCT YEAR(date) AS y')
                ->orderByDesc('y')
                ->pluck('y')
                ->map(fn ($y) => (int) $y)
                ->all(),
        ];
    }
}

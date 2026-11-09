<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PledgeResource;
use App\Models\Pledge;
use App\Support\Api\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * GET /me/pledges?status=
 * The member's own pledges (soft-deleted excluded) with live payments, newest
 * first. Not paginated: a member has only a few pledges. The summary covers all
 * non-cancelled pledges and ignores the status filter, like the web pledge reports.
 */
class PledgeController extends Controller
{
    public const STATUSES = ['Active', 'Fulfilled', 'Cancelled'];

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', 'string', Rule::in(self::STATUSES)],
        ]);

        $pledges = Pledge::query()
            ->where('member_id', $request->user()->member->id)
            ->with([
                'purpose:id,name',
                'harvest:id,name',
                'payments' => fn ($q) => $q->orderByDesc('payment_date')->orderByDesc('id'),
                'payments.transaction:id,reference',
            ])
            ->orderByDesc('date_pledged')
            ->orderByDesc('id')
            ->get();

        $counted = $pledges->where('status', '!=', 'Cancelled');
        $pledged = $counted->sum(fn (Pledge $p) => Money::toCents($p->amount_pledged));
        $paid    = $counted->sum(fn (Pledge $p) => PledgeResource::paidCents($p));
        $owing   = $counted->sum(fn (Pledge $p) => max(0, Money::toCents($p->amount_pledged) - PledgeResource::paidCents($p)));

        $listed = isset($data['status']) ? $pledges->where('status', $data['status'])->values() : $pledges;

        return PledgeResource::collection($listed)
            ->additional(['summary' => [
                'pledged'     => Money::fromCents($pledged),
                'paid'        => Money::fromCents($paid),
                'outstanding' => Money::fromCents($owing),
            ]])
            ->response();
    }
}

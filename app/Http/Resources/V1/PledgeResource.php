<?php

namespace App\Http\Resources\V1;

use App\Models\Pledge;
use App\Support\Api\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One of the member's own pledges with its live payments (soft-deleted payments
 * are excluded by the relation). Expects purpose, harvest and payments.transaction
 * to be eager-loaded. Never pledge notes (staff text), pledger_name or recorded_by.
 *
 * @mixin \App\Models\Pledge
 */
class PledgeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pledged = Money::toCents($this->amount_pledged);
        $paid    = self::paidCents($this->resource);

        return [
            'id'               => $this->id,
            'reference'        => $this->reference,
            'purpose'          => $this->purpose?->name,
            'harvest'          => $this->harvest?->name,
            'date_pledged'     => $this->date_pledged->toDateString(),
            'target_date'      => $this->target_date?->toDateString(),
            'status'           => $this->status,
            'currency'         => $this->currency,
            'amount_pledged'   => Money::fromCents($pledged),
            'paid'             => Money::fromCents($paid),
            'balance'          => Money::fromCents(max(0, $pledged - $paid)),   // as on the web pages
            'overpaid'         => Money::fromCents(max(0, $paid - $pledged)),
            'progress_percent' => $pledged > 0 ? min(100, round($paid / $pledged * 100, 1)) : 0,   // a JSON number: 30, 33.3, 100
            'payments'         => $this->payments->map(fn ($payment) => [
                'id'             => $payment->id,
                'date'           => $payment->payment_date->toDateString(),
                'amount'         => Money::format($payment->amount),
                'payment_method' => $payment->payment_method,
                'receipt'        => $payment->transaction?->reference,
            ])->values()->all(),
        ];
    }

    /** Sum of the loaded live payments, in pesewas. */
    public static function paidCents(Pledge $pledge): int
    {
        return $pledge->payments->sum(fn ($payment) => Money::toCents($payment->amount));
    }
}

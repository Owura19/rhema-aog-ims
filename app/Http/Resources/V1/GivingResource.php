<?php

namespace App\Http\Resources\V1;

use App\Support\Api\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One of the member's own gifts. Never the staff-written description, payer
 * name, mobile money / cheque / bank details, accounts or who recorded it.
 *
 * @mixin \App\Models\Transaction
 */
class GivingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'reference'      => $this->reference,
            'date'           => $this->transaction_date->toDateString(),
            'type'           => $this->type,
            'amount'         => Money::format($this->amount),
            'currency'       => $this->currency,
            'payment_method' => $this->payment_method,
        ];
    }
}

<?php

namespace App\Rules;

use App\Models\Member;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A fingerprint (device user) ID may belong to one member only, or attendance from the device
 * could not tell them apart. Checked here rather than with a database index because
 * existing data may already contain duplicates (see: php artisan biometric:duplicate-ids).
 * The message names the member who already has the ID.
 */
class UniqueFingerprintId implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreMemberId = null)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $other = Member::where('fingerprint_id', $value)
            ->when($this->ignoreMemberId, fn ($q) => $q->whereKeyNot($this->ignoreMemberId))
            ->first();

        if ($other) {
            $fail("Fingerprint ID {$value} is already assigned to {$other->full_name} ({$other->member_id}).");
        }
    }
}

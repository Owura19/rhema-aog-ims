<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A member's acceptance of one version of the community rules (and their "18 or older" tick). */
class CommunityRuleAcceptance extends Model
{
    public $timestamps = false;

    protected $fillable = ['member_id', 'rules_version', 'adult_confirmed', 'accepted_at'];

    protected $casts = [
        'adult_confirmed' => 'boolean',
        'accepted_at'     => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }
}

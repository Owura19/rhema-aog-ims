<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Audit row: harvest money linked to / unlinked from a pledge (see the migration). */
class HarvestLinkEvent extends Model
{
    public const SOURCE_REVIEW = 'review';
    public const SOURCE_FINANCE_FORM = 'finance_form';

    protected $fillable = [
        'action', 'source', 'transaction_id', 'pledge_payment_id', 'pledge_id', 'harvest_id',
        'giver_created', 'member_claimed', 'user_id',
    ];

    protected $casts = [
        'giver_created'  => 'boolean',
        'member_claimed' => 'boolean',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class)->withTrashed();
    }

    public function pledge()
    {
        return $this->belongsTo(Pledge::class)->withTrashed();
    }

    public function harvest()
    {
        return $this->belongsTo(Harvest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** The review-page link event behind a payment that is still linked, if any. */
    public static function activeReviewLink(int $paymentId): ?self
    {
        $latest = static::where('pledge_payment_id', $paymentId)->latest('id')->first();

        return $latest && $latest->action === 'link' && $latest->source === self::SOURCE_REVIEW ? $latest : null;
    }
}

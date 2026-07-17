<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberRelationship extends Model
{
    protected $fillable = [
        'member_id',
        'related_member_id',
        'type',
        'label',
    ];

    // ── RELATIONSHIPS ───────────────────────────────────────────

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function relatedMember()
    {
        return $this->belongsTo(Member::class, 'related_member_id');
    }

    // ── REVERSE-RELATIONSHIP LOGIC ──────────────────────────────

    /**
     * Given a relationship TYPE, return the inverse type.
     * If member A -> B is "parent" (B is A's parent),
     * then B -> A is "child".
     */
    public static function inverseType(string $type): string
    {
        return match ($type) {
            'parent'   => 'child',
            'child'    => 'parent',
            'spouse'   => 'spouse',
            'sibling'  => 'sibling',
            'guardian' => 'other',   // ward — no clean single term
            default    => 'other',
        };
    }

    /**
     * Create a relationship AND its reverse in one call,
     * so links are always bidirectional and consistent.
     */
    public static function link(int $memberId, int $relatedId, string $type, ?string $label = null): void
    {
        if ($memberId === $relatedId) {
            return; // can't relate a member to themselves
        }

        static::firstOrCreate(
            ['member_id' => $memberId, 'related_member_id' => $relatedId, 'type' => $type],
            ['label' => $label]
        );

        static::firstOrCreate(
            ['member_id' => $relatedId, 'related_member_id' => $memberId, 'type' => static::inverseType($type)],
            ['label' => $label]
        );
    }

    /**
     * Remove a relationship AND its reverse.
     */
    public static function unlink(int $memberId, int $relatedId): void
    {
        static::where(function ($q) use ($memberId, $relatedId) {
            $q->where('member_id', $memberId)->where('related_member_id', $relatedId);
        })->orWhere(function ($q) use ($memberId, $relatedId) {
            $q->where('member_id', $relatedId)->where('related_member_id', $memberId);
        })->delete();
    }

    /**
     * Human-friendly label for a relationship type.
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'parent'   => 'Parent',
            'child'    => 'Child',
            'spouse'   => 'Spouse',
            'sibling'  => 'Sibling',
            'guardian' => 'Guardian',
            default    => $this->label ?: 'Relative',
        };
    }
}
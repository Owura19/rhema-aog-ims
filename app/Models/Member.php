<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'member_id',
        'first_name',
        'last_name',
        'other_name',
        'email',
        'phone',
        'alt_phone',
        'gender',
        'date_of_birth',
        'occupation',
        'employer',
        'marital_status',
        'residential_address',
        'digital_address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'date_joined',
        'date_baptized',
        'membership_status',
        'member_type',
        'photo',
        'family_id',
        'family_role',
        'fingerprint_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'date_of_birth'  => 'date',
        'date_joined'    => 'date',
        'date_baptized'  => 'date',
    ];

    // ── RELATIONSHIPS ──────────────────────────────────────

    public function family()
    {
        return $this->belongsTo(Family::class);
    }

    public function relationships()
    {
        return $this->hasMany(MemberRelationship::class, 'member_id')->with('relatedMember');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages()
    {
        return $this->hasMany(MemberMessage::class);
    }

    // ── ACCESSORS ──────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->other_name} {$this->last_name}");
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }

    public function getPhotoUrlAttribute(): string
    {
        return $this->photo
            ? asset('storage/' . $this->photo)
            : asset('images/default-avatar.png');
    }

    // ── AUTO GENERATE MEMBER ID ────────────────────────────

    protected static function booted(): void
    {
        static::creating(function ($member) {
            if (empty($member->member_id)) {
                $latest = static::withTrashed()->latest('id')->first();
                $nextId = $latest ? $latest->id + 1 : 1;
                $member->member_id = 'GW-' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
            }
        });
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }
}
<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A member's own profile, read-only. Only these keys — never notes (staff text),
 * fingerprint_id, created_by, membership_status / member_type (internal
 * classifications), relationships (other members) or the family's own details.
 *
 * @mixin \App\Models\Member
 */
class MemberProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'member_code'         => $this->member_id,
            'first_name'          => $this->first_name,
            'other_name'          => $this->other_name,
            'last_name'           => $this->last_name,
            'full_name'           => $this->full_name,
            'gender'              => $this->gender,
            'date_of_birth'       => $this->date_of_birth?->toDateString(),
            'marital_status'      => $this->marital_status,
            'email'               => $this->email,
            'phone'               => $this->phone,
            'alt_phone'           => $this->alt_phone,
            'occupation'          => $this->occupation,
            'employer'            => $this->employer,
            'residential_address' => $this->residential_address,
            'digital_address'     => $this->digital_address,
            'emergency_contact'   => [
                'name'  => $this->emergency_contact_name,
                'phone' => $this->emergency_contact_phone,
            ],
            'date_joined'         => $this->date_joined?->toDateString(),
            'date_baptized'       => $this->date_baptized?->toDateString(),
            // The model's photo_url falls back to a default avatar; the app wants null instead.
            'photo_url'           => $this->photo ? asset('storage/' . $this->photo) : null,
            'family'              => $this->family ? [
                'name' => $this->family->family_name,
                'role' => $this->family_role,
            ] : null,
        ];
    }
}

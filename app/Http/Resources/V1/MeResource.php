<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The signed-in user as the app sees it. Only these keys — never password,
 * remember_token or email. Permissions are listed explicitly (Super Admin has
 * no Gate::before bypass in this app).
 *
 * @mixin \App\Models\User
 */
class MeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $token = $this->currentAccessToken();

        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'login_phone'      => $this->login_phone,
            'member'           => $this->member ? [
                'id'          => $this->member->id,
                'member_code' => $this->member->member_id,
                'full_name'   => $this->member->full_name,
            ] : null,
            'roles'            => $this->getRoleNames()->values(),
            'permissions'      => $this->getAllPermissions()->pluck('name')->sort()->values(),
            'token_expires_at' => $token instanceof PersonalAccessToken ? $token->expires_at?->toIso8601String() : null,
        ];
    }
}

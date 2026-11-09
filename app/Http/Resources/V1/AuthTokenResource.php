<?php

namespace App\Http\Resources\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\NewAccessToken;

/**
 * A freshly issued app token:
 *   { "data": { "token": "12|rhema_…", "token_type": "Bearer", "expires_at": "…", "user": { …/me… } } }
 * The plain token is shown exactly once — only its hash is stored.
 */
class AuthTokenResource extends JsonResource
{
    public function __construct(private User $user, private NewAccessToken $token)
    {
        parent::__construct($token);
    }

    public function toArray(Request $request): array
    {
        $this->user->withAccessToken($this->token->accessToken)->load('member');

        return [
            'token'      => $this->token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $this->token->accessToken->expires_at?->toIso8601String(),
            'user'       => (new MeResource($this->user))->toArray($request),
        ];
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\MemberProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** GET /me/profile — the signed-in member's own record (read-only). */
class ProfileController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $member = $request->user()->member->load('family');

        return (new MemberProfileResource($member))->response();
    }
}

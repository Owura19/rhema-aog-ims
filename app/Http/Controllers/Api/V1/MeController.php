<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\MeResource;
use Illuminate\Http\Request;

/** GET /api/v1/me — the signed-in user (auth:sanctum + active). Extended in Step 3. */
class MeController extends Controller
{
    public function __invoke(Request $request): MeResource
    {
        return new MeResource($request->user()->load('member'));
    }
}

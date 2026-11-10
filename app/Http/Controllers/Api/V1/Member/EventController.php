<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\EventResource;
use App\Support\Events\MemberEvents;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /events?scope=upcoming|recent&page=&per_page=
 * GET /events/{id}
 * Draft and deleted events are invisible (404). See App\Support\Events\MemberEvents.
 */
class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'scope'    => ['nullable', 'string', 'in:upcoming,recent'],
            'page'     => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $memberId = $request->user()->member->id;
        $query = ($data['scope'] ?? 'upcoming') === 'recent'
            ? MemberEvents::recent($memberId)
            : MemberEvents::upcoming($memberId);

        $events = $query->paginate((int) ($data['per_page'] ?? 20))->withQueryString();

        return EventResource::collection($events)->response();
    }

    public function show(Request $request, int $event): JsonResponse
    {
        $model = MemberEvents::query($request->user()->member->id)->findOrFail($event);

        return (new EventResource($model))->response();
    }
}

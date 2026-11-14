<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Announcements for the app's Home ('manage announcements': Super Admin, Pastor, Office Staff).
 *  - New announcements are drafts; publishing makes them show in the app between
 *    starts_at and ends_at (times are in the app timezone, UTC = Ghana time).
 *  - Deleting is a soft delete.
 */
class AnnouncementController extends Controller
{
    public const STATES = ['draft', 'scheduled', 'active', 'expired'];

    public function index(Request $request)
    {
        $filters = $request->validate(['state' => ['nullable', Rule::in(self::STATES)]]);

        $announcements = Announcement::query()
            ->when($filters['state'] ?? null, fn (Builder $q, string $state) => $this->whereState($q, $state))
            ->orderByDesc('is_pinned')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('announcements.index', compact('announcements'));
    }

    public function create()
    {
        return view('announcements.create', ['announcement' => new Announcement(['starts_at' => now()])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $userId = $request->user()->id;

        Announcement::create($this->validated($request) + [
            'status'     => Announcement::STATUS_DRAFT,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        return redirect()->route('announcements.index')->with('success', 'Announcement saved as a draft. Publish it to show it in the app.');
    }

    public function edit(Announcement $announcement)
    {
        return view('announcements.edit', compact('announcement'));
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $announcement->update($this->validated($request) + ['updated_by' => $request->user()->id]);

        return redirect()->route('announcements.index')->with('success', 'Announcement updated.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();   // soft delete

        return redirect()->route('announcements.index')->with('success', "\"{$announcement->title}\" was deleted.");
    }

    public function publish(Request $request, Announcement $announcement): RedirectResponse
    {
        if ($announcement->isPublished()) {
            return back()->with('error', 'This announcement is already published.');
        }

        $announcement->update(['status' => Announcement::STATUS_PUBLISHED, 'updated_by' => $request->user()->id]);

        $when = match ($announcement->fresh()->state()) {
            'scheduled' => ' It will show in the app from ' . $announcement->starts_at->format('j M Y, g:i A') . '.',
            'expired'   => ' Its end time has already passed, so it will not show in the app.',
            default     => ' It now shows in the app.',
        };

        return back()->with('success', 'Announcement published.' . $when);
    }

    public function unpublish(Request $request, Announcement $announcement): RedirectResponse
    {
        if (! $announcement->isPublished()) {
            return back()->with('error', 'This announcement is not published.');
        }

        $announcement->update(['status' => Announcement::STATUS_DRAFT, 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Announcement unpublished: it is a draft again and hidden from the app.');
    }

    // ── helpers ────────────────────────────────────────────────────────

    private function whereState(Builder $query, string $state): Builder
    {
        $now = now();

        return match ($state) {
            'draft'     => $query->where('status', Announcement::STATUS_DRAFT),
            'scheduled' => $query->where('status', Announcement::STATUS_PUBLISHED)->where('starts_at', '>', $now),
            'expired'   => $query->where('status', Announcement::STATUS_PUBLISHED)->whereNotNull('ends_at')->where('ends_at', '<=', $now),
            default     => $query->live(),
        };
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title'     => ['required', 'string', 'max:120'],
            'body'      => ['required', 'string', 'max:1000'],
            'link_url'  => ['nullable', 'string', 'max:500', 'url:https'],
            'is_pinned' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at'   => ['nullable', 'date'],
        ], [
            'link_url.url' => 'The link must be a full https:// address.',
        ]);

        $startsAt = isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : now();
        $endsAt = isset($data['ends_at']) ? Carbon::parse($data['ends_at']) : null;

        if ($endsAt && $endsAt->lte($startsAt)) {
            throw ValidationException::withMessages(['ends_at' => 'The end must be after the start.']);
        }

        return [
            'title'     => trim($data['title']),
            'body'      => trim($data['body']),
            'link_url'  => $data['link_url'] ?? null,
            'is_pinned' => $request->boolean('is_pinned'),
            'starts_at' => $startsAt,
            'ends_at'   => $endsAt,
        ];
    }
}

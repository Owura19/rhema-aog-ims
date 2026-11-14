<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Sermon;
use App\Models\SermonSeries;
use App\Support\Bible\InvalidReference;
use App\Support\Bible\ReferenceParser;
use App\Support\Bible\ScriptureReference;
use App\Support\Sermons\YouTube;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Staff pages for the sermon library.
 *  - view: 'view sermons'; write, publish, unpublish, delete: 'manage sermons'.
 *  - New sermons are drafts. Publishing makes them visible in the app (published_at = now)
 *    and needs a YouTube or audio link, so the app always has something to play.
 *  - The scripture reference is optional; when given it is checked by ReferenceParser
 *    and stored in display form with its book, chapter and verses (no verse text).
 *  - Deleting is a soft delete.
 */
class SermonController extends Controller
{
    /** Sermons may cite long passages (a whole Psalm 119 is 176 verses); devotions keep their own cap. */
    public const MAX_SCRIPTURE_VERSES = 200;

    private const THUMBNAIL_DIR = 'sermons/thumbnails';

    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in([Sermon::STATUS_DRAFT, Sermon::STATUS_PUBLISHED])],
            'series' => ['nullable', 'integer'],
        ]);

        $sermons = Sermon::query()
            ->with('series')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['series'] ?? null, fn ($q, $series) => $q->where('series_id', $series))
            ->orderByDesc('preached_on')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('sermons.index', [
            'sermons' => $sermons,
            'series'  => SermonSeries::orderBy('title')->get(),
        ]);
    }

    public function create()
    {
        return view('sermons.create', $this->formData(new Sermon(['preached_on' => today()])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $userId = $request->user()->id;

        $sermon = Sermon::create($data + [
            'status'     => Sermon::STATUS_DRAFT,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        return redirect()->route('sermons.show', $sermon)->with('success', 'Sermon saved as a draft.');
    }

    public function show(Sermon $sermon)
    {
        $sermon->load(['series', 'preacherMember', 'createdBy', 'updatedBy']);

        return view('sermons.show', compact('sermon'));
    }

    public function edit(Sermon $sermon)
    {
        return view('sermons.edit', $this->formData($sermon));
    }

    public function update(Request $request, Sermon $sermon): RedirectResponse
    {
        $data = $this->validated($request, $sermon);

        $sermon->update($data + ['updated_by' => $request->user()->id]);

        return redirect()->route('sermons.show', $sermon)->with('success', 'Sermon updated.');
    }

    public function destroy(Sermon $sermon): RedirectResponse
    {
        $title = $sermon->title;
        $sermon->delete();   // soft delete; the thumbnail file is kept with it

        return redirect()->route('sermons.index')->with('success', "\"{$title}\" was deleted.");
    }

    public function publish(Request $request, Sermon $sermon): RedirectResponse
    {
        if ($sermon->isPublished()) {
            return back()->with('error', 'This sermon is already published.');
        }

        if (! $sermon->youtube_video_id && ! $sermon->audio_url) {
            return back()->with('error', 'Add a YouTube link or an audio link before publishing.');
        }

        $sermon->update([
            'status'       => Sermon::STATUS_PUBLISHED,
            'published_at' => now(),
            'updated_by'   => $request->user()->id,
        ]);

        return back()->with('success', 'Sermon published. It is now in the app.');
    }

    public function unpublish(Request $request, Sermon $sermon): RedirectResponse
    {
        if (! $sermon->isPublished()) {
            return back()->with('error', 'This sermon is not published.');
        }

        $sermon->update([
            'status'       => Sermon::STATUS_DRAFT,
            'published_at' => null,
            'updated_by'   => $request->user()->id,
        ]);

        return back()->with('success', 'Sermon unpublished: it is a draft again and hidden from the app.');
    }

    // ── helpers ────────────────────────────────────────────────────────

    private function formData(Sermon $sermon): array
    {
        return [
            'sermon'  => $sermon,
            'series'  => SermonSeries::orderBy('title')->get(),
            'members' => Member::orderBy('first_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name']),
        ];
    }

    /** The sermon's columns from the form: reference checked, YouTube id extracted, thumbnail stored. */
    private function validated(Request $request, ?Sermon $sermon = null): array
    {
        $reference = null;
        $videoId = null;

        $data = $request->validate([
            'title'               => ['required', 'string', 'max:255'],
            'preacher_name'       => ['required', 'string', 'max:150'],
            'preacher_member_id'  => ['nullable', 'integer', Rule::exists('members', 'id')],
            'preached_on'         => ['required', 'date_format:Y-m-d'],
            'scripture_reference' => ['nullable', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail) use (&$reference) {
                if (! is_string($value) || trim($value) === '') {
                    return;
                }
                try {
                    $reference = (new ReferenceParser(self::MAX_SCRIPTURE_VERSES))->parse($value);
                } catch (InvalidReference $e) {
                    $fail($e->getMessage());
                }
            }],
            'series_id'           => ['nullable', 'integer', Rule::exists('sermon_series', 'id')],
            'summary'             => ['nullable', 'string', 'max:10000'],
            'youtube_url'         => ['nullable', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail) use (&$videoId) {
                if (! is_string($value) || trim($value) === '') {
                    return;
                }
                $videoId = YouTube::videoId($value);
                if ($videoId === null) {
                    $fail('Enter a YouTube video link (youtube.com or youtu.be).');
                }
            }],
            'audio_url'           => ['nullable', 'string', 'max:500', 'url:https'],
            'thumbnail'           => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_thumbnail'    => ['nullable', 'boolean'],
        ], [
            'audio_url.url' => 'The audio link must be a full https:// address.',
        ]);

        $columns = [
            'title'              => $data['title'],
            'preacher_name'      => $data['preacher_name'],
            'preacher_member_id' => $data['preacher_member_id'] ?? null,
            'preached_on'        => $data['preached_on'],
            'series_id'          => $data['series_id'] ?? null,
            'summary'            => $data['summary'] ?? null,
            'youtube_video_id'   => $videoId,
            'audio_url'          => $data['audio_url'] ?? null,
        ] + $this->scripture($reference);

        if ($request->hasFile('thumbnail')) {
            $this->deleteThumbnail($sermon);
            $columns['thumbnail_path'] = $request->file('thumbnail')->store(self::THUMBNAIL_DIR, 'public');
        } elseif ($request->boolean('remove_thumbnail')) {
            $this->deleteThumbnail($sermon);
            $columns['thumbnail_path'] = null;
        }

        return $columns;
    }

    /** @return array<string, mixed> the reference in display form and its verse columns, or all null */
    private function scripture(?ScriptureReference $reference): array
    {
        return [
            'scripture_reference' => $reference?->display,
            'bible_book_id'       => $reference?->bookId,
            'chapter'             => $reference?->chapter,
            'verse_start'         => $reference?->verseStart,
            'verse_end'           => $reference?->verseEnd,
        ];
    }

    private function deleteThumbnail(?Sermon $sermon): void
    {
        if ($sermon?->thumbnail_path) {
            Storage::disk('public')->delete($sermon->thumbnail_path);
        }
    }
}

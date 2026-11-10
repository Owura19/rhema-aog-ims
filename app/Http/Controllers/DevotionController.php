<?php

namespace App\Http\Controllers;

use App\Models\Devotion;
use App\Models\DevotionTheme;
use App\Services\Bible\BibleText;
use App\Support\Bible\InvalidReference;
use App\Support\Bible\ReferenceParser;
use App\Support\Bible\ScriptureReference;
use App\Support\Devotions\Words;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Staff pages for daily devotions.
 *  - New devotions are always drafts. Approving needs 'approve devotions'.
 *  - Editing an approved devotion without 'approve devotions' returns it to draft.
 *  - Deleting is a soft delete; an approved devotion also needs 'approve devotions'.
 *  - One non-deleted devotion per date (validated, and enforced by the database).
 *  - The scripture reference is checked (ReferenceParser) and stored in its display form
 *    with its book, chapter and verses; verse_text is never taken from the form: it is
 *    filled from the imported KJV when the passage changes (or is still empty).
 *  - Approving needs a checked reference and its verse text.
 */
class DevotionController extends Controller
{
    public const STRIP_DAYS = 14;

    /** The fields that make up the devotion's text (AI drafts become "edited" when any of these change). */
    private const TEXT_FIELDS = ['title', 'scripture_reference', 'body', 'prayer', 'thought_for_the_day'];

    private const DUPLICATE_DATE = 'There is already a devotion for this date.';

    private const NEEDS_REFERENCE = 'Add a scripture reference before approving.';

    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['draft', 'approved', 'live'])],
            'month'  => ['nullable', 'date_format:Y-m'],
        ]);

        $query = Devotion::query()->with('theme')->orderByDesc('publish_date');

        match ($filters['status'] ?? null) {
            'draft'    => $query->where('status', Devotion::STATUS_DRAFT),
            'approved' => $query->where('status', Devotion::STATUS_APPROVED)->where('publish_date', '>', today()->toDateString()),
            'live'     => $query->live(),
            default    => null,
        };

        if (isset($filters['month'])) {
            $month = Carbon::createFromFormat('!Y-m', $filters['month']);
            $query->whereBetween('publish_date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()]);
        }

        $devotions = $query->paginate(31)->withQueryString();

        return view('devotions.index', ['devotions' => $devotions, 'strip' => $this->nextDays()]);
    }

    public function create(Request $request)
    {
        $date = $this->validDate($request->query('date')) ?? $this->firstMissingDate();

        return view('devotions.create', [
            'devotion'       => new Devotion(['publish_date' => $date]),
            'themes'         => $this->themes(),
            'selectedTheme'  => $date ? DevotionTheme::covering($date)?->id : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $reference] = $this->validated($request);
        $themeId = $this->themeFor($data);
        $userId = $request->user()->id;

        try {
            $devotion = Devotion::create(array_merge($data, $this->scripture($reference), [
                'theme_id'   => $themeId,
                'no_theme'   => $themeId === null,
                'status'     => Devotion::STATUS_DRAFT,
                'source'     => Devotion::SOURCE_MANUAL,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['publish_date' => self::DUPLICATE_DATE]);
        }

        return $this->withLengthWarning(
            redirect()->route('devotions.show', $devotion)->with('success', 'Devotion saved as a draft.'),
            $devotion->body,
        );
    }

    public function show(Devotion $devotion)
    {
        $devotion->load(['theme', 'approvedBy', 'createdBy', 'updatedBy']);

        return view('devotions.show', compact('devotion'));
    }

    public function edit(Devotion $devotion)
    {
        return view('devotions.edit', [
            'devotion'      => $devotion,
            'themes'        => $this->themes(),
            'selectedTheme' => $devotion->theme_id,
        ]);
    }

    public function update(Request $request, Devotion $devotion): RedirectResponse
    {
        [$data, $reference] = $this->validated($request, $devotion);
        $themeId = $this->themeFor($data);

        $devotion->fill(array_merge($data, $this->scripture($reference, $devotion), ['theme_id' => $themeId, 'no_theme' => $themeId === null]));
        // Only what staff can edit (and the verse it brings) counts as a change; not the derived no_theme
        // flag or reference columns.
        $changed = $devotion->isDirty(array_merge(array_keys($data), ['theme_id', 'verse_text']));

        if ($devotion->source === Devotion::SOURCE_AI && $devotion->isDirty(self::TEXT_FIELDS)) {
            $devotion->ai_edited = true;
        }

        $backToDraft = $changed && $devotion->isApproved() && ! $request->user()->can('approve devotions');
        if ($backToDraft) {
            $devotion->status = Devotion::STATUS_DRAFT;
            $devotion->approved_by = null;
            $devotion->approved_at = null;
        }

        $devotion->updated_by = $request->user()->id;

        try {
            $devotion->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['publish_date' => self::DUPLICATE_DATE]);
        }

        $message = $backToDraft
            ? 'Devotion updated and returned to draft: it needs to be approved again.'
            : 'Devotion updated.';

        return $this->withLengthWarning(redirect()->route('devotions.show', $devotion)->with('success', $message), $devotion->body);
    }

    public function destroy(Request $request, Devotion $devotion): RedirectResponse
    {
        abort_if($devotion->isApproved() && ! $request->user()->can('approve devotions'), 403);

        $date = $devotion->publish_date->format('j M Y');
        $devotion->delete();   // soft delete: the date is free again

        return redirect()->route('devotions.index')
            ->with('success', "The devotion for {$date} was deleted. That date can be used again.");
    }

    public function approve(Request $request, Devotion $devotion): RedirectResponse
    {
        if ($devotion->isApproved()) {
            return back()->with('error', 'This devotion is already approved.');
        }

        $missing = array_values(array_filter(self::TEXT_FIELDS, fn (string $field) => trim((string) $devotion->{$field}) === ''));
        if ($missing) {
            $labels = array_map(fn (string $field) => str_replace('_', ' ', $field), $missing);

            return back()->with('error', "This devotion can't be approved yet. Missing: " . implode(', ', $labels) . '.');
        }

        if ($devotion->bible_book_id === null || trim((string) $devotion->verse_text) === '') {
            return back()->with('error', BibleText::isImported() ? self::NEEDS_REFERENCE : BibleText::NOT_IMPORTED);
        }

        $devotion->update([
            'status'      => Devotion::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        $when = $devotion->publish_date->lte(today())
            ? 'It is live now.'
            : 'It goes live on ' . $devotion->publish_date->format('j M Y') . '.';

        return back()->with('success', "Devotion approved. {$when}");
    }

    public function withdraw(Devotion $devotion): RedirectResponse
    {
        if (! $devotion->isApproved()) {
            return back()->with('error', 'This devotion is not approved.');
        }

        $devotion->update([
            'status'      => Devotion::STATUS_DRAFT,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        return back()->with('success', 'Approval withdrawn: the devotion is a draft again and hidden from the app.');
    }

    // ── helpers ────────────────────────────────────────────────────────

    /** @return array{0: array<string, mixed>, 1: ScriptureReference} the validated fields and the checked reference */
    private function validated(Request $request, ?Devotion $devotion = null): array
    {
        $reference = null;

        $data = $request->validate([
            // live_publish_date is NULL for deleted devotions, so a deleted devotion never blocks its date.
            'publish_date'        => ['required', 'date_format:Y-m-d', Rule::unique('devotions', 'live_publish_date')->ignore($devotion?->id)],
            'title'               => ['required', 'string', 'max:255'],
            'scripture_reference' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail) use (&$reference) {
                if (! is_string($value)) {
                    return;   // the 'string' rule reports it
                }
                if (! BibleText::isImported()) {
                    $fail(BibleText::NOT_IMPORTED);   // never "book not recognised" when there is no text at all

                    return;
                }
                try {
                    $reference = ReferenceParser::fromConfig()->parse($value);
                } catch (InvalidReference $e) {
                    $fail($e->getMessage());
                }
            }],
            'theme_id'            => ['nullable', 'integer', Rule::exists('devotion_themes', 'id')],
            'author_name'         => ['nullable', 'string', 'max:255'],
            'body'                => ['required', 'string', 'max:20000'],
            'prayer'              => ['required', 'string', 'max:5000'],
            'thought_for_the_day' => ['required', 'string', 'max:140'],
        ], [
            'publish_date.unique' => self::DUPLICATE_DATE,
        ]);

        return [$data, $reference];
    }

    /**
     * The reference in its display form with its book, chapter and verses, plus the KJV
     * verse text when the passage changed or the devotion has no verse text yet.
     */
    private function scripture(ScriptureReference $reference, ?Devotion $devotion = null): array
    {
        $columns = [
            'scripture_reference' => $reference->display,
            'bible_book_id'       => $reference->bookId,
            'chapter'             => $reference->chapter,
            'verse_start'         => $reference->verseStart,
            'verse_end'           => $reference->verseEnd,
        ];

        $samePassage = $devotion !== null
            && trim((string) $devotion->verse_text) !== ''
            && $devotion->bible_book_id === $reference->bookId
            && $devotion->chapter === $reference->chapter
            && $devotion->verse_start === $reference->verseStart
            && $devotion->verse_end === $reference->verseEnd;

        if ($samePassage) {
            return $columns;
        }

        $text = BibleText::passage($reference);
        if ($text === null) {
            throw ValidationException::withMessages(['scripture_reference' => BibleText::NOT_IMPORTED]);   // incomplete import
        }

        return $columns + ['verse_text' => $text];
    }

    /** The chosen theme, or (when "Automatic" is chosen) the theme covering the date. */
    private function themeFor(array $data): ?int
    {
        return isset($data['theme_id'])
            ? (int) $data['theme_id']
            : DevotionTheme::covering($data['publish_date'])?->id;
    }

    private function withLengthWarning(RedirectResponse $response, ?string $body): RedirectResponse
    {
        $warning = Words::staffLengthWarning($body);

        return $warning ? $response->with('warning', $warning) : $response;
    }

    private function themes()
    {
        return DevotionTheme::orderByDesc('starts_on')->get();
    }

    /**
     * Today and the next 13 days, each with its devotion (if any) and state:
     * live / approved / draft / missing.
     *
     * @return list<array{date: Carbon, state: string, devotion: ?Devotion}>
     */
    private function nextDays(): array
    {
        $first = today();
        $last = today()->addDays(self::STRIP_DAYS - 1);

        $byDate = Devotion::whereBetween('publish_date', [$first->toDateString(), $last->toDateString()])
            ->get()
            ->keyBy(fn (Devotion $devotion) => $devotion->publish_date->toDateString());

        $days = [];
        for ($date = $first->copy(); $date->lte($last); $date->addDay()) {
            $devotion = $byDate->get($date->toDateString());
            $days[] = ['date' => $date->copy(), 'state' => $devotion?->state() ?? 'missing', 'devotion' => $devotion];
        }

        return $days;
    }

    private function firstMissingDate(): ?string
    {
        foreach ($this->nextDays() as $day) {
            if ($day['state'] === 'missing') {
                return $day['date']->toDateString();
            }
        }

        return null;
    }

    private function validDate(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = Carbon::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }
}

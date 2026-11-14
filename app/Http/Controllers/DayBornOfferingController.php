<?php

namespace App\Http\Controllers;

use App\Exports\DayBornExport;
use App\Models\Account;
use App\Models\DayBornWeek;
use App\Services\DayBorn\DayBornRecorder;
use App\Support\DayBorn\Report;
use App\Support\DayBorn\Weekdays;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Day-born offering: ushers record ONE total per day-born group per service.
 *  - view:    'view day-born offering'    (weeks, results)
 *  - record:  'record day-born offering'  (enter / edit drafts, delete drafts)
 *  - publish: 'publish day-born offering' (publish, unpublish, edit a published week, post to finance)
 * Service dates are Sundays unless "special service" is ticked. Every change is audited.
 */
class DayBornOfferingController extends Controller
{
    public function index()
    {
        $weeks = DayBornWeek::with('offerings')->orderByDesc('service_date')->paginate(20);

        return view('day-born.index', compact('weeks'));
    }

    public function create(Request $request)
    {
        $date = $request->query('date');
        $default = is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : $this->lastSunday();

        $existing = DayBornWeek::whereDate('service_date', $default)->first();
        if ($existing) {
            return redirect()->route('day-born.edit', $existing);
        }

        return view('day-born.entry', [
            'week'    => new DayBornWeek(['service_date' => $default]),
            'amounts' => array_fill_keys(range(0, 6), ''),
        ]);
    }

    public function store(Request $request, DayBornRecorder $recorder): RedirectResponse
    {
        $data = $this->validated($request, null);

        $week = $recorder->save(null, $data['amounts'], $data['service_date'], $data['special_service'], $data['notes'], $request->user());

        return redirect()->route('day-born.show', $week)->with('success', 'Saved as a draft. Publish it to show it to members.');
    }

    public function show(DayBornWeek $week)
    {
        $week->load(['offerings', 'changes.changedBy', 'recordedBy', 'publishedBy', 'transaction']);

        return view('day-born.show', [
            'week'         => $week,
            'ranking'      => $week->ranking(),
            'incomeAccounts' => Account::where('type', 'Income')->where('is_group', false)->orderBy('sort_order')->orderBy('code')->get(),
            'cashAccounts' => $this->cashAccounts(),
            'defaultIncomeAccountId' => Account::where('code', '4100')->value('id'),
        ]);
    }

    public function edit(Request $request, DayBornWeek $week)
    {
        $this->authorizeEdit($request, $week);
        $week->load('offerings');

        return view('day-born.entry', ['week' => $week, 'amounts' => $week->amounts()]);
    }

    public function update(Request $request, DayBornWeek $week, DayBornRecorder $recorder): RedirectResponse
    {
        $this->authorizeEdit($request, $week);
        $data = $this->validated($request, $week);

        $recorder->save($week, $data['amounts'], $data['service_date'], $data['special_service'], $data['notes'], $request->user(), $data['reason']);

        return redirect()->route('day-born.show', $week->fresh())->with('success', $week->isPublished()
            ? 'The published week was updated. The change is recorded in its history.'
            : 'Draft updated.');
    }

    public function destroy(Request $request, DayBornWeek $week, DayBornRecorder $recorder): RedirectResponse
    {
        if ($week->isPublished()) {
            return back()->with('error', 'Unpublish the week before deleting it.');
        }

        try {
            $recorder->deleteDraft($week, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('day-born.index')->with('success', 'Draft deleted.');
    }

    public function publish(Request $request, DayBornWeek $week, DayBornRecorder $recorder): RedirectResponse
    {
        if ($week->isPublished()) {
            return back()->with('error', 'This week is already published.');
        }

        $data = $request->validate([
            'post_to_finance'    => ['nullable', 'boolean'],
            'finance_account_id' => ['nullable', 'required_if_accepted:post_to_finance', Rule::exists('accounts', 'id')->where('type', 'Income')->where('is_group', false)],
            'cash_account_id'    => ['nullable', 'required_if_accepted:post_to_finance', Rule::exists('accounts', 'id')->where('type', 'Asset')->where('is_group', false)],
        ], [
            'finance_account_id.required_if_accepted' => 'Choose the income account to post to.',
            'cash_account_id.required_if_accepted'    => 'Choose the cash or bank account the money went into.',
        ]);

        $post = $request->boolean('post_to_finance');
        try {
            $recorder->publish($week, $request->user(), $post, $data['finance_account_id'] ?? null, $data['cash_account_id'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $post
            ? 'Published, and the week\'s total was recorded in Finance as one Offering transaction.'
            : 'Published. Members can now see this week\'s results.');
    }

    public function unpublish(Request $request, DayBornWeek $week, DayBornRecorder $recorder): RedirectResponse
    {
        if (! $week->isPublished()) {
            return back()->with('error', 'This week is not published.');
        }

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $hadFinance = (bool) $week->transaction_id;
        $recorder->unpublish($week, $request->user(), $data['reason'] ?? null);

        return back()->with('success', $hadFinance
            ? 'Unpublished. Its finance transaction was voided.'
            : 'Unpublished: it is a draft again and hidden from members.');
    }

    /** Monthly / yearly view from published weeks: total per group and weeks won. */
    public function report(Request $request)
    {
        $data = $request->validate([
            'year'  => ['nullable', 'integer', 'min:2000', 'max:' . (now()->year + 1)],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);
        $year = (int) ($data['year'] ?? now()->year);

        return view('day-born.report', [
            'report' => new Report($year, isset($data['month']) ? (int) $data['month'] : null),
            'years'  => Report::years(now()->year),
        ]);
    }

    /** The year's published weeks as .xlsx. */
    public function export(Request $request): StreamedResponse
    {
        $data = $request->validate(['year' => ['nullable', 'integer', 'min:2000', 'max:' . (now()->year + 1)]]);
        $export = new DayBornExport(new Report((int) ($data['year'] ?? now()->year)));

        return response()->streamDownload(fn () => $export->write(), $export->filename(), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ── helpers ────────────────────────────────────────────────────────

    /** Drafts: anyone who records. A published week: only those who may publish. */
    private function authorizeEdit(Request $request, DayBornWeek $week): void
    {
        if ($week->isPublished() && ! $request->user()->can('publish day-born offering')) {
            abort(403, 'Only Finance can change a published week.');
        }
    }

    /** @return array{service_date: string, special_service: bool, notes: ?string, amounts: array<int, string>, reason: ?string} */
    private function validated(Request $request, ?DayBornWeek $week): array
    {
        $rules = [
            'service_date'    => ['required', 'date_format:Y-m-d', Rule::unique('day_born_weeks', 'service_date')->ignore($week?->id)],
            'special_service' => ['nullable', 'boolean'],
            'notes'           => ['nullable', 'string', 'max:1000'],
            'amounts'         => ['required', 'array'],
            'reason'          => [$week?->isPublished() ? 'required' : 'nullable', 'string', 'max:500'],
        ];
        foreach (Weekdays::numbers() as $weekday) {
            $rules["amounts.{$weekday}"] = ['required', 'regex:/^\d{1,10}(\.\d{1,2})?$/'];
        }

        $messages = ['service_date.unique' => 'A week for this date already exists.', 'reason.required' => 'Say why a published week is being changed.'];
        foreach (Weekdays::numbers() as $weekday) {
            $messages["amounts.{$weekday}.required"] = 'Enter an amount for ' . Weekdays::day($weekday) . ' (0 if none).';
            $messages["amounts.{$weekday}.regex"] = 'Enter ' . Weekdays::day($weekday) . "'s amount as a number like 125.50.";
        }

        $data = $request->validate($rules, $messages);
        $special = $request->boolean('special_service');

        if (! $special && ! Carbon::parse($data['service_date'])->isSunday()) {
            throw ValidationException::withMessages([
                'service_date' => 'Pick a Sunday, or tick "Special service" for another day.',
            ]);
        }

        return [
            'service_date'    => $data['service_date'],
            'special_service' => $special,
            'notes'           => isset($data['notes']) && trim($data['notes']) !== '' ? trim($data['notes']) : null,
            'amounts'         => collect(Weekdays::numbers())->mapWithKeys(fn ($d) => [$d => $data['amounts'][$d]])->all(),
            'reason'          => $data['reason'] ?? null,
        ];
    }

    private function lastSunday(): string
    {
        $today = today();

        return ($today->isSunday() ? $today : $today->previous(Carbon::SUNDAY))->toDateString();
    }

    private function cashAccounts()
    {
        $currentAssets = Account::where('code', '1000')->first();

        return Account::where('is_group', false)
            ->where('type', 'Asset')
            ->when($currentAssets, fn ($q) => $q->where('parent_id', $currentAssets->id))
            ->orderBy('sort_order')
            ->get();
    }
}

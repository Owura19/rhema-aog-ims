<?php

namespace App\Http\Controllers;

use App\Exports\LoanExport;
use App\Models\Account;
use App\Models\Loan;
use App\Services\Loans\LoanService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Finance → Loans. View: 'view finance'. Create / edit / adjust / recalculate: 'edit finance'. */
class LoanController extends Controller
{
    /** Transaction types a loan may count (income types). */
    public const TYPES = ['Tithe', 'Offering', 'Project Offering', 'Harvest', 'First Fruit', 'Seed', 'Pledge', 'Donation', 'Other'];

    /** Form value for "3200 Opening Balance Equity" before that account exists. */
    private const OPENING_EQUITY = 'opening_equity';

    public function __construct(private LoanService $loans) {}

    public function index()
    {
        $loans = Loan::with('liabilityAccount')->orderByRaw("status = 'Active' DESC")->latest('opening_date')->get();

        return view('loans.index', compact('loans'));
    }

    public function show(Loan $loan)
    {
        $loan->load(['liabilityAccount', 'counterAccount', 'repaymentAccount']);
        $repayments = $loan->repayments()->with(['transaction', 'cashAccount'])->get()
            ->sortBy(fn ($r) => [$r->transaction?->transaction_date?->toDateString(), $r->transaction_id])->values();
        $monthly = $repayments->groupBy(fn ($r) => $r->transaction?->transaction_date?->format('Y-m'))
            ->map(fn ($g, $month) => ['month' => $month, 'count' => $g->count(), 'applied' => $g->sum(fn ($r) => (float) $r->amount_applied)])
            ->sortKeys()->values();
        $adjustments = $loan->adjustments()->with(['counterAccount', 'createdBy'])->orderBy('adjustment_date')->orderBy('id')->get();
        $events = $loan->events()->with('user', 'transaction')->latest('id')->limit(30)->get();
        $ledgerBalance = $this->loans->ledgerBalance($loan);
        $cashAccounts = FinanceController::cashAccounts();
        $equityAccounts = $this->counterOptions(equityOnly: true);

        return view('loans.show', compact('loan', 'repayments', 'monthly', 'adjustments', 'events', 'ledgerBalance', 'cashAccounts', 'equityAccounts'));
    }

    public function create()
    {
        return view('loans.form', $this->formData(null));
    }

    /** Before saving a new loan: the opening entry and any counted transactions that already exist. */
    public function preview(Request $request)
    {
        $data = $this->validated($request, null);
        $existing = $this->loans->existingCounted($data['counted_types'], $data['repayment_start_date']);

        return view('loans.confirm', [
            'data'     => $data,
            'input'    => $request->except(['_token']),
            'existing' => $existing,
            'accounts' => Account::whereIn('id', array_filter([$data['liability_account_id'], $data['opening_counter_account_id'] ?? null, $data['repayment_account_id'] ?? null]))->get()->keyBy('id'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['confirmed' => 'accepted']);
        $data = $this->resolveCounter($this->validated($request, null));
        $loan = $this->loans->create($data, auth()->id());

        return redirect()->route('loans.show', $loan)->with('success', "Loan from {$loan->lender} recorded. "
            . $loan->repayments()->count() . ' counted ' . ($loan->repayments()->count() === 1 ? 'payment' : 'payments') . ' so far.');
    }

    public function edit(Loan $loan)
    {
        return view('loans.form', $this->formData($loan));
    }

    public function update(Request $request, Loan $loan)
    {
        $data = $this->resolveCounter($this->validated($request, $loan));
        $before = [$loan->balance(), $loan->repayments()->count()];
        $loan = $this->loans->update($loan, $data, auth()->id());

        return redirect()->route('loans.show', $loan)->with('success', sprintf('Loan updated. Balance %s (was %s); %d counted payments (was %d).',
            \App\Support\Cedis::format($loan->balance()), \App\Support\Cedis::format($before[0]), $loan->repayments()->count(), $before[1]));
    }

    public function adjust(Request $request, Loan $loan)
    {
        $data = $request->validate([
            'kind'               => 'required|in:owed_more,owed_less,repayment',
            'amount'             => 'required|numeric|min:0.01',
            'adjustment_date'    => 'required|date',
            'reason'             => 'required|string|min:5|max:500',
            'counter_account_id' => 'required|string',
        ], ['reason.required' => 'Give the reason for this adjustment.']);

        $counter = $data['counter_account_id'] === self::OPENING_EQUITY
            ? $this->loans->openingEquityAccount()->id
            : (int) $data['counter_account_id'];
        if (! Account::whereKey($counter)->where('is_group', false)->exists()) {
            throw ValidationException::withMessages(['counter_account_id' => 'Choose an account.']);
        }

        $amount = (float) $data['amount'];
        $this->loans->adjust($loan, $data['kind'] === 'repayment' ? 'repayment' : 'correction',
            $data['kind'] === 'owed_less' ? -$amount : $amount, $data['adjustment_date'], $data['reason'], $counter, auth()->id());

        return redirect()->route('loans.show', $loan)->with('success', 'Adjustment recorded. Balance now ' . \App\Support\Cedis::format($loan->fresh()->balance()) . '.');
    }

    public function recalculate(Loan $loan)
    {
        $s = $this->loans->rebuild($loan, auth()->id());

        return redirect()->route('loans.show', $loan)->with('success',
            "Recalculated: {$s['added']} added, {$s['changed']} changed, {$s['removed']} removed.");
    }

    public function export(Loan $loan): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $export = new LoanExport($loan, $this->loans->ledgerBalance($loan));

        return response()->streamDownload(fn () => $export->write(), $export->filename(), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ── helpers ────────────────────────────────────────────────────────

    private function formData(?Loan $loan): array
    {
        $liabilities = Account::where('type', 'Liability')->where('is_group', false)->orderBy('code')->get();

        return [
            'loan'         => $loan,
            'types'        => self::TYPES,
            'claimed'      => $this->loans->claimedTypes($loan),
            'liabilities'  => $liabilities,
            'counters'     => $this->counterOptions(),
            'cashAccounts' => FinanceController::cashAccounts(),
            'defaults'     => [
                'liability'  => $loan?->liability_account_id ?? $liabilities->firstWhere('code', '2100')?->id,
                'counter'    => $loan?->opening_counter_account_id ?? (Account::where('code', LoanService::OPENING_EQUITY_CODE)->value('id') ?? self::OPENING_EQUITY),
                'start'      => $loan?->repayment_start_date?->toDateString() ?? now()->addDay()->toDateString(),
                'opening'    => $loan?->opening_date?->toDateString() ?? now()->toDateString(),
                'types'      => $loan?->counted_types ?? Loan::DEFAULT_TYPES,
                'source'     => $loan?->repayment_source ?? Loan::SOURCE_RECEIVED,
                'fixed'      => $loan?->repayment_account_id ?? Account::where('code', '1536')->value('id'),
            ],
        ];
    }

    /** Accounts the opening balance can be set against: equity first (3200 even before it exists), then the rest. */
    private function counterOptions(bool $equityOnly = false): array
    {
        $options = [];
        if (! Account::where('code', LoanService::OPENING_EQUITY_CODE)->exists()) {
            $options[self::OPENING_EQUITY] = LoanService::OPENING_EQUITY_CODE . ' — Opening Balance Equity (recommended; created on save)';
        }
        $types = $equityOnly ? ['Equity'] : ['Equity', 'Asset'];
        foreach (Account::where('is_group', false)->whereIn('type', $types)->orderByRaw("type = 'Equity' DESC")->orderBy('code')->get() as $a) {
            $options[$a->id] = "{$a->code} — {$a->name}" . ($a->code === LoanService::OPENING_EQUITY_CODE ? ' (recommended)' : '');
        }

        return $options;
    }

    private function validated(Request $request, ?Loan $loan): array
    {
        $data = $request->validate([
            'lender'                     => 'required|string|max:255',
            'description'                => 'nullable|string|max:2000',
            'liability_account_id'       => ['required', Rule::exists('accounts', 'id')->where('type', 'Liability')->where('is_group', false)],
            'opening_balance'            => 'required|numeric|min:0.01',
            'opening_date'               => 'required|date',
            'opening_counter_account_id' => 'required|string',
            'repayment_start_date'       => 'required|date',
            'counted_types'              => 'required|array|min:1',
            'counted_types.*'            => ['string', Rule::in(self::TYPES)],
            'repayment_source'           => ['required', Rule::in([Loan::SOURCE_RECEIVED, Loan::SOURCE_FIXED])],
            'repayment_account_id'       => ['nullable', 'required_if:repayment_source,fixed', Rule::exists('accounts', 'id')->where('type', 'Asset')->where('is_group', false)],
        ], [
            'counted_types.required'           => 'Choose at least one transaction type that repays the loan.',
            'repayment_account_id.required_if' => 'Choose the account the repayments are paid from.',
        ]);

        if ($data['opening_counter_account_id'] !== self::OPENING_EQUITY
            && ! Account::whereKey($data['opening_counter_account_id'])->where('is_group', false)->exists()) {
            throw ValidationException::withMessages(['opening_counter_account_id' => 'Choose the account the opening balance is set against.']);
        }
        $shared = Loan::where('status', 'Active')->where('liability_account_id', $data['liability_account_id'])
            ->when($loan, fn ($q) => $q->whereKeyNot($loan->id))->exists();
        if ($shared) {
            throw ValidationException::withMessages(['liability_account_id' => 'Another active loan already uses this account: give each loan its own liability account.']);
        }
        if ($data['repayment_source'] === Loan::SOURCE_RECEIVED) {
            $data['repayment_account_id'] = null;
        }
        $data['counted_types'] = array_values(array_unique($data['counted_types']));

        return $data;
    }

    private function resolveCounter(array $data): array
    {
        if ($data['opening_counter_account_id'] === self::OPENING_EQUITY) {
            $data['opening_counter_account_id'] = $this->loans->openingEquityAccount()->id;
        }
        $data['opening_counter_account_id'] = (int) $data['opening_counter_account_id'];

        return $data;
    }
}

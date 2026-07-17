<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BankTransactionController extends Controller
{
    public function index()
    {
        $transfers = JournalEntry::with('lines.account')
            ->where('source_type', 'bank_transfer')
            ->latest('entry_date')->latest('id')
            ->paginate(20);

        $currentAssetsGroup = Account::where('code', '1000')->first();
        $accounts = Account::where('is_group', false)
            ->where('type', 'Asset')
            ->when($currentAssetsGroup, fn($q) => $q->where('parent_id', $currentAssetsGroup->id))
            ->orderBy('sort_order')->get();

        $balances = [];
        foreach ($accounts as $a) {
            $sum = JournalLine::where('account_id', $a->id)
                ->selectRaw('SUM(debit) - SUM(credit) as bal')->value('bal');
            $balances[$a->id] = (float) $sum;
        }

        return view('bank.index', compact('transfers', 'accounts', 'balances'));
    }

    public function create()
    {
        $accounts = $this->currentAssetAccounts();
        $cash = $accounts->firstWhere('code', '1100');
        $bank = $accounts->firstWhere('code', '1200');
        return view('bank.create', compact('accounts', 'cash', 'bank'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateTransfer($request);

        $amount = (float) $validated['amount'];
        $fromId = (int) $validated['from_account_id'];
        $toId   = (int) $validated['to_account_id'];
        $desc   = $this->buildDescription($validated, $fromId, $toId);

        (new LedgerService())->postEntry([
            ['account_id' => $toId,   'debit' => $amount, 'credit' => 0],
            ['account_id' => $fromId, 'debit' => 0,       'credit' => $amount],
        ], $validated['entry_date'], $desc, 'bank_transfer', null);

        return redirect()->route('bank.index')
            ->with('success', ucfirst($validated['kind']) . " of GHS " . number_format($amount, 2) . " recorded.");
    }

    /**
     * Edit an existing bank transfer.
     */
    public function edit(JournalEntry $bank)
    {
        // Only bank transfers are editable here
        if ($bank->source_type !== 'bank_transfer') {
            return redirect()->route('bank.index')->with('error', 'That entry is not a bank transaction.');
        }

        $bank->load('lines');
        $debitLine  = $bank->lines->firstWhere('debit', '>', 0);   // destination (money in)
        $creditLine = $bank->lines->firstWhere('credit', '>', 0);  // source (money out)

        $accounts = $this->currentAssetAccounts();

        $current = [
            'amount'          => $debitLine ? (float) $debitLine->debit : 0,
            'to_account_id'   => $debitLine?->account_id,
            'from_account_id' => $creditLine?->account_id,
            'entry_date'      => $bank->entry_date,
            'description'     => $bank->description,
        ];

        return view('bank.edit', compact('bank', 'accounts', 'current'));
    }

    /**
     * Update an existing bank transfer — rewrites its two journal lines.
     */
    public function update(Request $request, JournalEntry $bank)
    {
        if ($bank->source_type !== 'bank_transfer') {
            return redirect()->route('bank.index')->with('error', 'That entry is not a bank transaction.');
        }

        $validated = $this->validateTransfer($request);
        $amount = (float) $validated['amount'];
        $fromId = (int) $validated['from_account_id'];
        $toId   = (int) $validated['to_account_id'];
        $desc   = $this->buildDescription($validated, $fromId, $toId);

        DB::transaction(function () use ($bank, $amount, $fromId, $toId, $validated, $desc) {
            // Rewrite the entry header
            $bank->update([
                'entry_date'  => $validated['entry_date'],
                'description' => $desc,
            ]);

            // Delete old lines, write corrected ones
            $bank->lines()->delete();
            JournalLine::create(['journal_entry_id' => $bank->id, 'account_id' => $toId,   'debit' => $amount, 'credit' => 0]);
            JournalLine::create(['journal_entry_id' => $bank->id, 'account_id' => $fromId, 'debit' => 0,       'credit' => $amount]);
        });

        return redirect()->route('bank.index')
            ->with('success', "Bank transaction updated.");
    }

    /**
     * Delete a bank transfer — removes the journal entry and its lines,
     * reversing its effect on the ledger.
     */
    public function destroy(JournalEntry $bank)
    {
        if ($bank->source_type !== 'bank_transfer') {
            return redirect()->route('bank.index')->with('error', 'That entry is not a bank transaction.');
        }

        DB::transaction(function () use ($bank) {
            $bank->lines()->delete();
            $bank->delete();
        });

        return redirect()->route('bank.index')->with('success', 'Bank transaction deleted.');
    }

    // ── helpers ───────────────────────────────────────────────
    private function currentAssetAccounts()
    {
        $currentAssetsGroup = Account::where('code', '1000')->first();
        return Account::where('is_group', false)
            ->where('type', 'Asset')
            ->when($currentAssetsGroup, fn($q) => $q->where('parent_id', $currentAssetsGroup->id))
            ->orderBy('sort_order')->get();
    }

    private function validateTransfer(Request $request): array
    {
        return $request->validate([
            'kind'            => 'required|in:deposit,withdrawal',
            'from_account_id' => 'required|exists:accounts,id|different:to_account_id',
            'to_account_id'   => 'required|exists:accounts,id',
            'amount'          => 'required|numeric|min:0.01',
            'entry_date'      => 'required|date',
            'reference'       => 'nullable|string|max:100',
            'description'     => 'nullable|string|max:255',
        ]);
    }

    private function buildDescription(array $v, int $fromId, int $toId): string
    {
        if (!empty($v['description'])) return $v['description'];
        $fromName = Account::find($fromId)->name;
        $toName   = Account::find($toId)->name;
        return ucfirst($v['kind']) . ": {$fromName} → {$toName}";
    }
}
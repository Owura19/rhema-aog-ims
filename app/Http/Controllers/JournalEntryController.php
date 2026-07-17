<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\LedgerService;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    /**
     * List all journal entries (auto-posted and manual).
     */
    public function index()
    {
        $entries = JournalEntry::with(['lines.account', 'createdBy'])
            ->latest('entry_date')
            ->latest('id')
            ->paginate(25);

        return view('finance.journals.index', compact('entries'));
    }

    /**
     * Show the manual journal entry form.
     */
    public function create()
    {
        $accounts = Account::where('is_group', false)
            ->orderBy('type')
            ->orderBy('code')
            ->get();

        return view('finance.journals.create', compact('accounts'));
    }

    /**
     * Store a manual journal entry.
     * Validates that total debits == total credits before posting.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'entry_date'          => 'required|date',
            'description'         => 'required|string|max:255',
            'lines'               => 'required|array|min:2',
            'lines.*.account_id'  => 'required|exists:accounts,id',
            'lines.*.debit'       => 'nullable|numeric|min:0',
            'lines.*.credit'      => 'nullable|numeric|min:0',
        ]);

        // Build clean lines, dropping empty rows
        $lines = [];
        foreach ($validated['lines'] as $line) {
            $debit  = (float) ($line['debit']  ?? 0);
            $credit = (float) ($line['credit'] ?? 0);

            if ($debit == 0 && $credit == 0) {
                continue; // skip blank lines
            }
            if ($debit > 0 && $credit > 0) {
                return back()->withInput()->with('error', 'Each line can have a debit OR a credit, not both.');
            }

            $lines[] = [
                'account_id' => (int) $line['account_id'],
                'debit'      => $debit,
                'credit'     => $credit,
            ];
        }

        if (count($lines) < 2) {
            return back()->withInput()->with('error', 'A journal entry needs at least two lines.');
        }

        $totalDebit  = round(array_sum(array_column($lines, 'debit')), 2);
        $totalCredit = round(array_sum(array_column($lines, 'credit')), 2);

        if ($totalDebit !== $totalCredit) {
            return back()->withInput()->with('error', "Entry does not balance — debits (GHS {$totalDebit}) must equal credits (GHS {$totalCredit}).");
        }

        try {
            (new LedgerService())->postEntry(
                $lines,
                $validated['entry_date'],
                $validated['description'],
                'manual',
                null
            );
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Could not post entry: ' . $e->getMessage());
        }

        return redirect()->route('finance.journals.index')
            ->with('success', 'Journal entry posted successfully.');
    }

    /**
     * View a single journal entry.
     */
    public function show(JournalEntry $journal)
    {
        $journal->load(['lines.account', 'createdBy']);
        return view('finance.journals.show', compact('journal'));
    }

    /**
     * Delete a manual journal entry (only manual ones, to protect auto-posted).
     */
    public function destroy(JournalEntry $journal)
    {
        if ($journal->source_type !== 'manual') {
            return back()->with('error', 'Only manual journal entries can be deleted here. Auto-posted entries are managed via their source transaction.');
        }

        $journal->lines()->delete();
        $journal->delete();

        return redirect()->route('finance.journals.index')
            ->with('success', 'Journal entry deleted.');
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalLine;
use App\Models\Transaction;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index()
    {
        $income    = $this->groupsOf('Income');
        $expense   = $this->groupsOf('Expense');
        $asset     = $this->groupsOf('Asset');
        $liability = $this->groupsOf('Liability');
        $equity    = $this->groupsOf('Equity');

        $counts = [
            'total'     => Account::count(),
            'income'    => Account::where('type', 'Income')->count(),
            'expense'   => Account::where('type', 'Expense')->count(),
            'asset'     => Account::where('type', 'Asset')->count(),
            'liability' => Account::where('type', 'Liability')->count(),
            'equity'    => Account::where('type', 'Equity')->count(),
        ];

        // Group headings, for the "add account" parent dropdown
        $groups = Account::where('is_group', true)->orderBy('type')->orderBy('sort_order')->get();

        return view('accounts.index', compact('income', 'expense', 'asset', 'liability', 'equity', 'counts', 'groups'));
    }

    private function groupsOf(string $type)
    {
        return Account::with('children')
            ->whereNull('parent_id')
            ->where('type', $type)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Store a new account. Code is auto-generated within the type's range.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'type'      => 'required|in:Income,Expense,Asset,Liability,Equity',
            'parent_id' => 'nullable|exists:accounts,id',
            'ref'       => 'nullable|string|max:50',
            'is_group'  => 'nullable|boolean',
        ]);

        $validated['code']      = $this->nextCode($validated['type']);
        $validated['is_group']  = $request->boolean('is_group');
        $validated['is_active'] = true;
        $validated['sort_order'] = (int) $validated['code'];

        Account::create($validated);

        return back()->with('success', "Account \"{$validated['name']}\" created with code {$validated['code']}.");
    }

    /**
     * Update an existing account (name, group, active status).
     * Type and code are not changed here to protect posted history.
     */
    public function update(Request $request, Account $account)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'parent_id' => 'nullable|exists:accounts,id',
            'ref'       => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        $account->update([
            'name'      => $validated['name'],
            'parent_id' => $validated['parent_id'] ?? $account->parent_id,
            'ref'       => $validated['ref'] ?? $account->ref,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', "Account \"{$account->name}\" updated.");
    }

    /**
     * Delete an account — ONLY if it has no transactions and no journal lines.
     * Otherwise refuse and suggest deactivating instead.
     */
    public function destroy(Account $account)
    {
        // Groups with children cannot be deleted while children exist
        if ($account->is_group && $account->children()->exists()) {
            return back()->with('error', "\"{$account->name}\" is a group with sub-accounts. Move or delete the sub-accounts first.");
        }

        $hasJournal     = JournalLine::where('account_id', $account->id)->exists();
        $hasTransaction = Transaction::where('account_id', $account->id)->exists();

        if ($hasJournal || $hasTransaction) {
            return back()->with('error', "\"{$account->name}\" has posted activity and cannot be deleted. Deactivate it instead to hide it from new entries while keeping its history.");
        }

        $name = $account->name;
        $account->delete();

        return back()->with('success', "Account \"{$name}\" deleted.");
    }

    /**
     * Toggle active/inactive (safe alternative to deletion).
     */
    public function toggleActive(Account $account)
    {
        $account->update(['is_active' => ! $account->is_active]);
        $state = $account->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "Account \"{$account->name}\" {$state}.");
    }

    /**
     * Next available code within a type's numeric range.
     *   Asset 1000s, Liability 2000s, Equity 3000s, Income 4000s, Expense 5000s
     */
    private function nextCode(string $type): string
    {
        $base = [
            'Asset'     => 1000,
            'Liability' => 2000,
            'Equity'    => 3000,
            'Income'    => 4000,
            'Expense'   => 5000,
        ][$type];

        $ceiling = $base + 999;

        // Highest existing numeric code in this range
        $max = Account::where('type', $type)
            ->whereRaw('code REGEXP "^[0-9]+$"')
            ->whereRaw('CAST(code AS UNSIGNED) BETWEEN ? AND ?', [$base, $ceiling])
            ->selectRaw('MAX(CAST(code AS UNSIGNED)) as m')
            ->value('m');

        $next = $max ? $max + 1 : $base + 1;

        // Avoid collisions with group headings (which often sit on round hundreds)
        while (Account::where('code', (string) $next)->exists()) {
            $next++;
        }

        return (string) $next;
    }
}
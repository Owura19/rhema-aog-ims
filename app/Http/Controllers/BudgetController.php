<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Transaction;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    /**
     * Form to set/edit budget figures for account groups for a year.
     */
    public function edit(Request $request)
    {
        $year = (int) $request->get('year', now()->year);

        // Top-level groups (income then expense)
        $groups = Account::whereNull('parent_id')
            ->orderBy('type')
            ->orderBy('sort_order')
            ->get();

        // Existing budget figures for this year, keyed by account_id
        $budgets = Budget::where('year', $year)->pluck('amount', 'account_id');

        return view('finance.budget-edit', compact('groups', 'budgets', 'year'));
    }

    /**
     * Save the budget figures.
     */
    public function update(Request $request)
    {
        $year   = (int) $request->get('year', now()->year);
        $amounts = $request->input('amounts', []); // [account_id => amount]

        foreach ($amounts as $accountId => $amount) {
            $amount = $amount === null || $amount === '' ? 0 : (float) $amount;

            Budget::updateOrCreate(
                ['account_id' => $accountId, 'year' => $year],
                ['amount' => $amount]
            );
        }

        return redirect()
            ->route('finance.budget.report', ['year' => $year])
            ->with('success', "Budget for {$year} saved.");
    }

    /**
     * Budget vs Actual report for a year.
     */
    public function report(Request $request)
    {
        $year = (int) $request->get('year', now()->year);

        $from = "{$year}-01-01";
        $to   = "{$year}-12-31";

        // Actual totals per account_id for the year (all postable accounts)
        $actualByAccount = Transaction::query()
            ->whereNotNull('account_id')
            ->whereBetween('transaction_date', [$from, $to])
            ->where('status', 'Confirmed')
            ->selectRaw('account_id, SUM(amount) as total')
            ->groupBy('account_id')
            ->pluck('total', 'account_id');

        // Budget figures for the year, keyed by account_id (group level)
        $budgetByAccount = Budget::where('year', $year)->pluck('amount', 'account_id');

        $income  = $this->buildRows('Income', $actualByAccount, $budgetByAccount);
        $expense = $this->buildRows('Expense', $actualByAccount, $budgetByAccount);

        $totals = [
            'income_budget'  => $income->sum('budget'),
            'income_actual'  => $income->sum('actual'),
            'expense_budget' => $expense->sum('budget'),
            'expense_actual' => $expense->sum('actual'),
        ];

        return view('finance.budget-report', compact('income', 'expense', 'totals', 'year'));
    }

    /**
     * Build budget-vs-actual rows for a section, at group level.
     * A group's actual = its own postings + all its children's postings.
     */
    private function buildRows(string $type, $actualByAccount, $budgetByAccount)
    {
        $groups = Account::with('children')
            ->whereNull('parent_id')
            ->where('type', $type)
            ->orderBy('sort_order')
            ->get();

        return $groups->map(function ($group) use ($actualByAccount, $budgetByAccount) {
            $actual = (float) ($actualByAccount[$group->id] ?? 0);
            foreach ($group->children as $child) {
                $actual += (float) ($actualByAccount[$child->id] ?? 0);
            }

            $budget   = (float) ($budgetByAccount[$group->id] ?? 0);
            $variance = $actual - $budget;
            $percent  = $budget > 0 ? round(($actual / $budget) * 100, 1) : 0;

            return (object) [
                'ref'      => $group->ref,
                'name'     => $group->name,
                'budget'   => $budget,
                'actual'   => $actual,
                'variance' => $variance,
                'percent'  => $percent,
            ];
        });
    }
}
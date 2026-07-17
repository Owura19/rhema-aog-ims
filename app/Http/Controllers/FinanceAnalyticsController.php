<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;

class FinanceAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) $request->get('year', now()->year);

        // ── Key totals ───────────────────────────────────────────
        $incomeYear  = Transaction::where('category', 'Income')->whereYear('transaction_date', $year)->sum('amount');
        $expenseYear = Transaction::where('category', 'Expense')->whereYear('transaction_date', $year)->sum('amount');
        $netYear     = $incomeYear - $expenseYear;

        $incomeMonth  = Transaction::where('category', 'Income')->whereYear('transaction_date', now()->year)->whereMonth('transaction_date', now()->month)->sum('amount');
        $expenseMonth = Transaction::where('category', 'Expense')->whereYear('transaction_date', now()->year)->whereMonth('transaction_date', now()->month)->sum('amount');

        // ── Income by TYPE (pie) ─────────────────────────────────
        $incomeByType = Transaction::selectRaw('type, SUM(amount) as total')
            ->where('category', 'Income')
            ->whereYear('transaction_date', $year)
            ->groupBy('type')
            ->orderByDesc('total')
            ->get();

        // ── Expense by TYPE/SUBCATEGORY (pie) ────────────────────
        $expenseByType = Transaction::selectRaw('COALESCE(NULLIF(subcategory, ""), type) as label, SUM(amount) as total')
            ->where('category', 'Expense')
            ->whereYear('transaction_date', $year)
            ->groupBy('label')
            ->orderByDesc('total')
            ->take(10)
            ->get();

        // ── Monthly income vs expense (bar/line) ─────────────────
        $months = [];
        $monthlyIncome = [];
        $monthlyExpense = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[] = date('M', mktime(0, 0, 0, $m, 1));
            $monthlyIncome[] = (float) Transaction::where('category', 'Income')
                ->whereYear('transaction_date', $year)->whereMonth('transaction_date', $m)->sum('amount');
            $monthlyExpense[] = (float) Transaction::where('category', 'Expense')
                ->whereYear('transaction_date', $year)->whereMonth('transaction_date', $m)->sum('amount');
        }

        // Years available for the filter
        $years = Transaction::selectRaw('DISTINCT YEAR(transaction_date) as y')->orderByDesc('y')->pluck('y');
        if ($years->isEmpty()) {
            $years = collect([now()->year]);
        }

        return view('finance.analytics', compact(
            'year', 'years',
            'incomeYear', 'expenseYear', 'netYear', 'incomeMonth', 'expenseMonth',
            'incomeByType', 'expenseByType',
            'months', 'monthlyIncome', 'monthlyExpense'
        ));
    }
}
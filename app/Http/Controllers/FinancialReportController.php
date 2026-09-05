<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FinancialReportController extends Controller
{
    /**
     * Income & Expenditure Statement for a date range.
     * Shows each account group with its total (summed from posted transactions).
     */
    public function statement(Request $request)
    {
        // Default range: start of current year to today
        $from = $request->filled('from')
            ? Carbon::parse($request->from)->startOfDay()
            : now()->startOfYear();
        $to = $request->filled('to')
            ? Carbon::parse($request->to)->endOfDay()
            : now()->endOfDay();

        // Sum of confirmed transactions per account_id within the range
        $totalsByAccount = Transaction::query()
            ->whereNotNull('account_id')
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->where('status', 'Confirmed')
            ->selectRaw('account_id, SUM(amount) as total')
            ->groupBy('account_id')
            ->pluck('total', 'account_id'); // [account_id => total]

        // Build the income and expense sections as nested groups
        $income  = $this->buildSection('Income', $totalsByAccount);
        $expense = $this->buildSection('Expense', $totalsByAccount);

        $totalIncome  = $income->sum('total');
        $totalExpense = $expense->sum('total');
        $netBalance   = $totalIncome - $totalExpense;

        return view('finance.statement', [
            'income'       => $income,
            'expense'      => $expense,
            'totalIncome'  => $totalIncome,
            'totalExpense' => $totalExpense,
            'netBalance'   => $netBalance,
            'from'         => $from->toDateString(),
            'to'           => $to->toDateString(),
        ]);
    }

    /**
     * Receipts & Payments — cash, mobile money, and cheque, by account.
     */
    public function cashSummary(Request $request)
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->from)->startOfDay()
            : now()->startOfYear();
        $to = $request->filled('to')
            ? Carbon::parse($request->to)->endOfDay()
            : now()->endOfDay();

        $cashMethods = ['Cash', 'Mobile Money', 'Cheque'];

        $rows = Transaction::query()
            ->with('account')
            ->whereNotNull('account_id')
            ->whereIn('payment_method', $cashMethods)
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->where('status', 'Confirmed')
            ->selectRaw('account_id, category, SUM(amount) as total')
            ->groupBy('account_id', 'category')
            ->get();

        $receipts = $rows->where('category', 'Income')
            ->map(fn($r) => (object)['account' => optional($r->account)->name ?? '—', 'total' => (float)$r->total])
            ->sortByDesc('total')->values();
        $payments = $rows->where('category', 'Expense')
            ->map(fn($r) => (object)['account' => optional($r->account)->name ?? '—', 'total' => (float)$r->total])
            ->sortByDesc('total')->values();

        $totalReceipts = $receipts->sum('total');
        $totalPayments = $payments->sum('total');
        $netCash       = $totalReceipts - $totalPayments;

        return view('finance.cash-summary', [
            'receipts'      => $receipts,
            'payments'      => $payments,
            'totalReceipts' => $totalReceipts,
            'totalPayments' => $totalPayments,
            'netCash'       => $netCash,
            'from'          => $from->toDateString(),
            'to'            => $to->toDateString(),
        ]);
    }
    /**
     * Actuals — condensed statement: group-level totals only.
     */
    public function actuals(Request $request)
    {
        [$from, $to, $totals] = $this->rangeTotals($request);

        $income  = $this->buildSection('Income', $totals);
        $expense = $this->buildSection('Expense', $totals);

        $totalIncome  = $income->sum('total');
        $totalExpense = $expense->sum('total');

        return view('finance.actuals', [
            'income'       => $income,
            'expense'      => $expense,
            'totalIncome'  => $totalIncome,
            'totalExpense' => $totalExpense,
            'netBalance'   => $totalIncome - $totalExpense,
            'from'         => $from->toDateString(),
            'to'           => $to->toDateString(),
        ]);
    }

    public function hub()
    {
        return view('finance.reports-hub');
    }

    /**
     * Master financial report PDF — full chart of accounts with amounts + totals.
     */
    public function masterReportPdf(Request $request)
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->from)->startOfDay()
            : now()->startOfYear();
        $to = $request->filled('to')
            ? Carbon::parse($request->to)->endOfDay()
            : now()->endOfDay();

        $totalsByAccount = Transaction::query()
            ->whereNotNull('account_id')
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->where('status', 'Confirmed')
            ->selectRaw('account_id, SUM(amount) as total')
            ->groupBy('account_id')
            ->pluck('total', 'account_id');

        $income  = $this->buildSection('Income', $totalsByAccount);
        $expense = $this->buildSection('Expense', $totalsByAccount);

        $totalIncome  = $income->sum('total');
        $totalExpense = $expense->sum('total');
        $netBalance   = $totalIncome - $totalExpense;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('finance.pdf.master-report', [
            'income'       => $income,
            'expense'      => $expense,
            'totalIncome'  => $totalIncome,
            'totalExpense' => $totalExpense,
            'netBalance'   => $netBalance,
            'from'         => $from->toDateString(),
            'to'           => $to->toDateString(),
            'churchName'   => config('app.name'),
            'generatedAt'  => now()->format('M d, Y g:i A'),
        ])->setPaper('a4', 'portrait');

        $filename = 'Financial-Report-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.pdf';
        return $pdf->download($filename);
    }

    public function statementPdf(Request $request)
    {
        [$from, $to, $totals] = $this->rangeTotals($request);
        $income  = $this->buildSection('Income', $totals);
        $expense = $this->buildSection('Expense', $totals);
        $totalIncome  = $income->sum('total');
        $totalExpense = $expense->sum('total');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('finance.pdf.master-report', [
            'income' => $income, 'expense' => $expense,
            'totalIncome' => $totalIncome, 'totalExpense' => $totalExpense,
            'netBalance' => $totalIncome - $totalExpense,
            'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'churchName' => config('app.name'),
            'generatedAt' => now()->format('M d, Y g:i A'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('Statement-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.pdf');
    }

    public function incomeNotePdf(Request $request)
    {
        [$from, $to, $totals] = $this->rangeTotals($request);
        $groups = $this->buildSection('Income', $totals);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('finance.pdf.note', [
            'title' => 'Income Note', 'accent' => '#16a34a',
            'groups' => $groups, 'grandTotal' => $groups->sum('total'),
            'grandLabel' => 'TOTAL INCOME',
            'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'churchName' => config('app.name'),
            'generatedAt' => now()->format('M d, Y g:i A'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('Income-Note-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.pdf');
    }

    public function expenditureNotePdf(Request $request)
    {
        [$from, $to, $totals] = $this->rangeTotals($request);
        $groups = $this->buildSection('Expense', $totals);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('finance.pdf.note', [
            'title' => 'Expenditure Note', 'accent' => '#dc2626',
            'groups' => $groups, 'grandTotal' => $groups->sum('total'),
            'grandLabel' => 'TOTAL EXPENDITURE',
            'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'churchName' => config('app.name'),
            'generatedAt' => now()->format('M d, Y g:i A'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('Expenditure-Note-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.pdf');
    }

    /**
     * Shared helper: resolve date range + account totals from the request.
     */
    private function rangeTotals(Request $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->from)->startOfDay()
            : now()->startOfYear();
        $to = $request->filled('to')
            ? Carbon::parse($request->to)->endOfDay()
            : now()->endOfDay();

        $totals = Transaction::query()
            ->whereNotNull('account_id')
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->where('status', 'Confirmed')
            ->selectRaw('account_id, SUM(amount) as total')
            ->groupBy('account_id')
            ->pluck('total', 'account_id');

        return [$from, $to, $totals];
    }

    /**
     * Income Note — detailed breakdown of every income sub-account.
     */
    public function incomeNote(Request $request)
    {
        return $this->note($request, 'Income', 'finance.income-note');
    }

    /**
     * Expenditure Note — detailed breakdown of every expense sub-account.
     */
    public function expenditureNote(Request $request)
    {
        return $this->note($request, 'Expense', 'finance.expenditure-note');
    }

    /**
     * Shared logic for the detail notes.
     */
    private function note(Request $request, string $type, string $view)
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->from)->startOfDay()
            : now()->startOfYear();
        $to = $request->filled('to')
            ? Carbon::parse($request->to)->endOfDay()
            : now()->endOfDay();

        $totalsByAccount = Transaction::query()
            ->whereNotNull('account_id')
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->where('status', 'Confirmed')
            ->selectRaw('account_id, SUM(amount) as total')
            ->groupBy('account_id')
            ->pluck('total', 'account_id');

        $groups     = $this->buildSection($type, $totalsByAccount);
        $grandTotal = $groups->sum('total');

        return view($view, [
            'groups'     => $groups,
            'grandTotal' => $grandTotal,
            'from'       => $from->toDateString(),
            'to'         => $to->toDateString(),
        ]);
    }

    /**
     * Build a section (Income or Expense) as top-level groups,
     * each carrying its own total and its children's totals.
     */
    private function buildSection(string $type, $totalsByAccount)
    {
        $groups = Account::with('children')
            ->whereNull('parent_id')
            ->where('type', $type)
            ->orderBy('sort_order')
            ->get();

        return $groups->map(function ($group) use ($totalsByAccount) {
            // A group's own posted total (for groups with no children, e.g. Tithe)
            $ownTotal = (float) ($totalsByAccount[$group->id] ?? 0);

            // Children with their individual totals
            $children = $group->children->map(function ($child) use ($totalsByAccount) {
                return (object) [
                    'code'  => $child->code,
                    'ref'   => $child->ref,
                    'name'  => $child->name,
                    'total' => (float) ($totalsByAccount[$child->id] ?? 0),
                ];
            });

            // Drop children with exactly zero for the period
            $children = $children->filter(fn($c) => (float) $c->total != 0.0)->values();

            $childrenTotal = $children->sum('total');

            return (object) [
                'code'     => $group->code,
                'ref'      => $group->ref,
                'name'     => $group->name,
                'children' => $children,
                // Group total = its own postings + all children postings
                'total'    => $ownTotal + $childrenTotal,
            ];
        })
        // Drop groups that are entirely zero for the period (no own total, no children left)
        ->filter(fn($g) => (float) $g->total != 0.0 || $g->children->isNotEmpty())
        ->values();
    }
}
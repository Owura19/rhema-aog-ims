<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Member;
use App\Models\ChurchService;
use App\Exports\FinanceExport;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function index(Request $request)
    {
        $query = Transaction::with(['member', 'churchService', 'recordedBy'])->latest('transaction_date');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhere('payer_name', 'like', "%{$search}%")
                  ->orWhereHas('member', fn($m) => $m->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%"));
            });
        }

        $transactions = $query->paginate(20)->withQueryString();

        $stats = [
            'total_income'    => Transaction::income()->thisMonth()->sum('amount'),
            'total_expense'   => Transaction::expense()->thisMonth()->sum('amount'),
            'total_tithes'    => Transaction::where('type', 'Tithe')->thisMonth()->sum('amount'),
            'total_offerings' => Transaction::where('type', 'Offering')->thisMonth()->sum('amount'),
            'net_balance'     => Transaction::income()->thisMonth()->sum('amount') - Transaction::expense()->thisMonth()->sum('amount'),
            'total_count'     => Transaction::thisMonth()->count(),
        ];

        return view('finance.index', compact('transactions', 'stats'));
    }

    public function create()
    {
        $members  = Member::orderBy('first_name')->get();
        $services = ChurchService::latest('service_date')->take(20)->get();

        // Income/Expense accounts for the "Post to Account" dropdown
        $accounts = \App\Models\Account::where('is_group', false)
            ->whereIn('type', ['Income', 'Expense'])
            ->orderBy('sort_order')
            ->get();

        // Cash / Bank accounts only (Current Assets group) for the "Paid to / from" dropdown
        $currentAssetsGroup = \App\Models\Account::where('code', '1000')->first();
        $cashAccounts = \App\Models\Account::where('is_group', false)
            ->where('type', 'Asset')
            ->when($currentAssetsGroup, fn($q) => $q->where('parent_id', $currentAssetsGroup->id))
            ->orderBy('sort_order')
            ->get();

        // Balance-sheet accounts (all Asset + Liability) for asset/liability transactions
        $balanceSheetAccounts = \App\Models\Account::where('is_group', false)
            ->whereIn('type', ['Asset', 'Liability'])
            ->orderBy('type')
            ->orderBy('sort_order')
            ->get();

        return view('finance.create', compact('members', 'services', 'accounts', 'cashAccounts', 'balanceSheetAccounts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type'                 => 'required|in:Tithe,Offering,First Fruit,Seed,Pledge,Donation,Expense,Other',
            'subcategory'          => 'nullable|string|max:100',
            'account_id'           => 'nullable|exists:accounts,id',
            'cash_account_id'      => 'nullable|exists:accounts,id',
            'category'             => 'required|in:Income,Expense,Asset,Liability',
            'direction'            => 'nullable|in:in,out',
            'amount'               => 'required|numeric|min:0.01',
            'member_id'            => 'nullable|exists:members,id',
            'payer_name'           => 'nullable|string|max:255',
            'transaction_date'     => 'required|date',
            'church_service_id'    => 'nullable|exists:church_services,id',
            'payment_method'       => 'required|in:Cash,Mobile Money,Bank Transfer,Cheque,Other',
            'mobile_money_number'  => 'nullable|string|max:20',
            'cheque_number'        => 'nullable|string|max:50',
            'bank_name'            => 'nullable|string|max:255',
            'status'               => 'required|in:Pending,Confirmed,Cancelled',
            'description'          => 'nullable|string',
        ]);

        $validated['recorded_by'] = auth()->id();

        // Pull out fields that aren't columns on transactions
        $cashAccountId = $request->input('cash_account_id');
        $direction     = $request->input('direction');
        unset($validated['cash_account_id'], $validated['direction']);

        // Asset/Liability transactions store as Income or Expense category on the
        // transaction record (based on money direction), but post to the ledger
        // against the chosen balance-sheet account.
        $balanceSheetType = null;
        if (in_array($validated['category'], ['Asset', 'Liability'])) {
            $balanceSheetType = $validated['category']; // remember it for posting
            // Money IN behaves like income (cash up), money OUT like expense (cash down)
            $validated['category'] = ($direction === 'in') ? 'Income' : 'Expense';
        }

        $finance = Transaction::create($validated);

        // ── DOUBLE-ENTRY POSTING ─────────────────────────────────
        if ($finance->account_id && $cashAccountId && $finance->status === 'Confirmed') {
            $ledger = new \App\Services\LedgerService();
            $desc = "{$finance->type} — " . ($finance->reference ?? 'Transaction');

            if ($balanceSheetType) {
                // ASSET or LIABILITY movement
                $amount = (float) $finance->amount;
                if ($direction === 'in') {
                    // Money in: debit cash/bank, credit the balance-sheet account
                    $ledger->postEntry([
                        ['account_id' => $cashAccountId,        'debit' => $amount, 'credit' => 0],
                        ['account_id' => $finance->account_id,  'debit' => 0,       'credit' => $amount],
                    ], $finance->transaction_date, $desc, 'transaction', $finance->id);
                } else {
                    // Money out: debit the balance-sheet account, credit cash/bank
                    $ledger->postEntry([
                        ['account_id' => $finance->account_id,  'debit' => $amount, 'credit' => 0],
                        ['account_id' => $cashAccountId,        'debit' => 0,       'credit' => $amount],
                    ], $finance->transaction_date, $desc, 'transaction', $finance->id);
                }
            } elseif ($finance->category === 'Income') {
                $ledger->postIncome(
                    (float) $finance->amount,
                    $finance->account_id,
                    $cashAccountId,
                    $finance->transaction_date,
                    $desc,
                    'transaction',
                    $finance->id
                );
            } else {
                $ledger->postExpense(
                    (float) $finance->amount,
                    $finance->account_id,
                    $cashAccountId,
                    $finance->transaction_date,
                    $desc,
                    'transaction',
                    $finance->id
                );
            }
        }

        return redirect()->route('finance.show', $finance)
            ->with('success', "Transaction {$finance->reference} recorded successfully!");
    }

    public function show(Transaction $finance)
    {
        $finance->load(['member', 'churchService', 'recordedBy']);
        $transaction = $finance;
        return view('finance.show', compact('transaction'));
    }

    public function edit(Transaction $finance)
    {
        $transaction = $finance;
        $members     = Member::orderBy('first_name')->get();
        $services    = ChurchService::latest('service_date')->take(20)->get();
        return view('finance.edit', compact('transaction', 'members', 'services'));
    }

    public function update(Request $request, Transaction $finance)
    {
        $validated = $request->validate([
            'type'                 => 'required|in:Tithe,Offering,First Fruit,Seed,Pledge,Donation,Expense,Other',
            'subcategory'          => 'nullable|string|max:100',
            'category'             => 'required|in:Income,Expense',
            'amount'               => 'required|numeric|min:0.01',
            'member_id'            => 'nullable|exists:members,id',
            'payer_name'           => 'nullable|string|max:255',
            'transaction_date'     => 'required|date',
            'church_service_id'    => 'nullable|exists:church_services,id',
            'payment_method'       => 'required|in:Cash,Mobile Money,Bank Transfer,Cheque,Other',
            'mobile_money_number'  => 'nullable|string|max:20',
            'cheque_number'        => 'nullable|string|max:50',
            'bank_name'            => 'nullable|string|max:255',
            'status'               => 'required|in:Pending,Confirmed,Cancelled',
            'description'          => 'nullable|string',
        ]);

        $finance->update($validated);

        return redirect()->route('finance.show', $finance)
            ->with('success', "Transaction {$finance->reference} updated successfully!");
    }

    public function destroy(Transaction $finance)
    {
        // Remove any double-entry journal tied to this transaction
        (new \App\Services\LedgerService())->removeForSource('transaction', $finance->id);

        $ref = $finance->reference;
        $finance->delete();
        return redirect()->route('finance.index')
            ->with('success', "Transaction {$ref} deleted.");
    }

    public function report(Request $request)
    {
        $year  = $request->get('year', now()->year);
        $month = $request->get('month', null);

        $query = Transaction::query();

        if ($month) {
            $query->whereYear('transaction_date', $year)
                  ->whereMonth('transaction_date', $month);
        } else {
            $query->whereYear('transaction_date', $year);
        }

        $income  = (clone $query)->where('category', 'Income')->sum('amount');
        $expense = (clone $query)->where('category', 'Expense')->sum('amount');
        $balance = $income - $expense;

        $byType = Transaction::selectRaw('type, category, SUM(amount) as total, COUNT(*) as count')
            ->whereYear('transaction_date', $year)
            ->when($month, fn($q) => $q->whereMonth('transaction_date', $month))
            ->groupBy('type', 'category')
            ->get();

        $monthly = Transaction::selectRaw('MONTH(transaction_date) as month, category, SUM(amount) as total')
            ->whereYear('transaction_date', $year)
            ->groupBy('month', 'category')
            ->orderBy('month')
            ->get();

        $topGivers = Transaction::selectRaw('member_id, SUM(amount) as total')
            ->where('category', 'Income')
            ->whereYear('transaction_date', $year)
            ->when($month, fn($q) => $q->whereMonth('transaction_date', $month))
            ->whereNotNull('member_id')
            ->groupBy('member_id')
            ->orderByDesc('total')
            ->take(10)
            ->with('member')
            ->get();

        return view('finance.report', compact(
            'income', 'expense', 'balance',
            'byType', 'monthly', 'topGivers',
            'year', 'month'
        ));
    }

    public function exportExcel(Request $request)
    {
        $year   = $request->get('year', now()->year);
        $export = new FinanceExport($year);
        $export->download();
    }

    public function receipt(Transaction $transaction)
    {
        $transaction->load(['member', 'churchService', 'recordedBy']);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('receipts.transaction', compact('transaction'));
        $pdf->setPaper([0, 0, 226.77, 600], 'portrait');
        $pdf->setOptions([
            'dpi'                  => 150,
            'defaultFont'          => 'DejaVu Sans',
            'isRemoteEnabled'      => false,
            'isHtml5ParserEnabled' => true,
        ]);
        return $pdf->download('Receipt_' . $transaction->reference . '.pdf');
    }

    public function receiptView(Transaction $transaction)
    {
        $transaction->load(['member', 'churchService', 'recordedBy']);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('receipts.transaction', compact('transaction'));
        $pdf->setPaper([0, 0, 226.77, 600], 'portrait');
        $pdf->setOptions([
            'dpi'                  => 150,
            'defaultFont'          => 'DejaVu Sans',
            'isRemoteEnabled'      => false,
            'isHtml5ParserEnabled' => true,
        ]);
        return $pdf->stream('Receipt_' . $transaction->reference . '.pdf');
    }
}
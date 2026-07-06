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
        return view('finance.create', compact('members', 'services'));
    }

    public function store(Request $request)
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

        $validated['recorded_by'] = auth()->id();

        $finance = Transaction::create($validated);

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
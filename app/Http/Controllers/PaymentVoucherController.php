<?php

namespace App\Http\Controllers;

use App\Models\PaymentVoucher;
use App\Models\Account;
use App\Models\Transaction;
use App\Services\LedgerService;
use Illuminate\Http\Request;

class PaymentVoucherController extends Controller
{
    public function index(Request $request)
    {
        $query = PaymentVoucher::with(['account', 'preparedBy'])->latest('voucher_date')->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $vouchers = $query->paginate(20)->withQueryString();

        $stats = [
            'pending'  => PaymentVoucher::where('status', 'Pending')->count(),
            'approved' => PaymentVoucher::where('status', 'Approved')->count(),
            'paid'     => PaymentVoucher::where('status', 'Paid')->sum('amount'),
        ];

        return view('vouchers.index', compact('vouchers', 'stats'));
    }

    public function create()
    {
        // Expense + Asset accounts to charge the payment to
        $accounts = Account::where('is_group', false)
            ->whereIn('type', ['Expense', 'Asset'])
            ->orderBy('type')->orderBy('sort_order')->get();

        // Cash/Bank accounts (Current Assets) to pay from
        $currentAssetsGroup = Account::where('code', '1000')->first();
        $cashAccounts = Account::where('is_group', false)
            ->where('type', 'Asset')
            ->when($currentAssetsGroup, fn($q) => $q->where('parent_id', $currentAssetsGroup->id))
            ->orderBy('sort_order')->get();

        return view('vouchers.create', compact('accounts', 'cashAccounts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'voucher_date'    => 'required|date',
            'payee'           => 'required|string|max:255',
            'description'     => 'required|string',
            'category'        => 'required|in:Expense,Asset',
            'account_id'      => 'required|exists:accounts,id',
            'cash_account_id' => 'required|exists:accounts,id',
            'amount'          => 'required|numeric|min:0.01',
            'payment_method'  => 'required|in:Cash,Cheque,Bank Transfer,Mobile Money',
            'cheque_number'   => 'nullable|string|max:50',
            'notes'           => 'nullable|string',
        ]);

        $validated['voucher_no']  = $this->nextVoucherNo();
        $validated['status']      = 'Pending';
        $validated['prepared_by'] = auth()->id();

        $voucher = PaymentVoucher::create($validated);

        return redirect()->route('vouchers.show', $voucher)
            ->with('success', "Voucher {$voucher->voucher_no} created. Print it for signatures, then approve.");
    }

    public function show(PaymentVoucher $voucher)
    {
        $voucher->load(['account', 'cashAccount', 'preparedBy', 'approvedBy', 'paidBy', 'transaction']);
        return view('vouchers.show', compact('voucher'));
    }

    /**
     * Mark approved (after physical signatures on the printout).
     */
    public function approve(PaymentVoucher $voucher)
    {
        if (! $voucher->can_approve) {
            return back()->with('error', 'Only pending vouchers can be approved.');
        }

        $voucher->update([
            'status'      => 'Approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', "Voucher {$voucher->voucher_no} approved. It can now be paid.");
    }

    /**
     * Mark paid — THIS posts to the accounts (double-entry) and creates the transaction.
     * Money only hits the books at this step, never before.
     */
    public function pay(PaymentVoucher $voucher)
    {
        if (! $voucher->can_pay) {
            return back()->with('error', 'Only approved vouchers can be paid.');
        }

        // Create the underlying transaction (recorded as an Expense outflow)
        $transaction = Transaction::create([
            'type'             => 'Expense',
            'category'         => 'Expense',
            'account_id'       => $voucher->account_id,
            'amount'           => $voucher->amount,
            'payer_name'       => $voucher->payee,
            'transaction_date' => $voucher->voucher_date,
            'payment_method'   => $voucher->payment_method,
            'cheque_number'    => $voucher->cheque_number,
            'status'           => 'Confirmed',
            'description'      => "Voucher {$voucher->voucher_no}: {$voucher->description}",
            'recorded_by'      => auth()->id(),
        ]);

        // Post double-entry: debit the expense/asset account, credit cash/bank
        if ($voucher->account_id && $voucher->cash_account_id) {
            (new LedgerService())->postEntry([
                ['account_id' => $voucher->account_id,      'debit' => (float) $voucher->amount, 'credit' => 0],
                ['account_id' => $voucher->cash_account_id, 'debit' => 0, 'credit' => (float) $voucher->amount],
            ], $voucher->voucher_date, "Voucher {$voucher->voucher_no}: {$voucher->payee}", 'transaction', $transaction->id);
        }

        $voucher->update([
            'status'         => 'Paid',
            'paid_by'        => auth()->id(),
            'paid_at'        => now(),
            'transaction_id' => $transaction->id,
        ]);

        return back()->with('success', "Voucher {$voucher->voucher_no} marked as paid and posted to the accounts.");
    }

    public function reject(PaymentVoucher $voucher)
    {
        if (! in_array($voucher->status, ['Pending', 'Approved'])) {
            return back()->with('error', 'This voucher cannot be rejected.');
        }
        $voucher->update(['status' => 'Rejected']);
        return back()->with('success', "Voucher {$voucher->voucher_no} rejected.");
    }

    public function print(PaymentVoucher $voucher)
    {
        $voucher->load(['account', 'cashAccount', 'preparedBy', 'approvedBy']);
        return view('vouchers.print', compact('voucher'));
    }

    private function nextVoucherNo(): string
    {
        $n = PaymentVoucher::count() + 1;
        return 'PV-' . str_pad($n, 5, '0', STR_PAD_LEFT);
    }
}
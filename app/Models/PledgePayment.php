<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class PledgePayment extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'pledge_id',
        'amount',
        'payment_date',
        'payment_method',
        'transaction_id',
        'notes',
        'recorded_by',
    ];
    protected $casts = [
        'payment_date' => 'date',
        'amount'       => 'decimal:2',
    ];
    // ── RELATIONSHIPS ───────────────────────────────────────────
    public function pledge()
    {
        return $this->belongsTo(Pledge::class);
    }
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
    // ── DEFAULT CASH/BANK ACCOUNT PER PAYMENT METHOD ────────────
    // (matches the manual Record Transaction form's typical choice for each method)
    public static function defaultCashAccountId(?string $paymentMethod): ?int
    {
        return match ($paymentMethod) {
            'Cash', 'Mobile Money', 'Other' => \App\Models\Account::where('code', '1100')->value('id'), // Cash on Hand
            'Bank Transfer', 'Cheque'       => \App\Models\Account::where('code', '1200')->value('id'), // Bank Account
            default                          => \App\Models\Account::where('code', '1100')->value('id'),
        };
    }
    // ── AUTO-POST TO FINANCE + UPDATE PLEDGE STATUS ─────────────
    protected static function booted(): void
    {
        // When a payment is recorded, create a matching income transaction
        // (and post the double-entry) so it flows into finance reports,
        // the Chart of Accounts, and gets a printable receipt.
        static::created(function ($payment) {
            if (!$payment->transaction_id) {
                $pledge = $payment->pledge;
                if ($pledge) {
                    $accountId = optional($pledge->purpose)->account_id
                        ?? \App\Models\Account::where('code', '4700')->value('id'); // Other Income fallback
                    $cashAccountId = self::defaultCashAccountId($payment->payment_method);

                    $purposeName = optional($pledge->purpose)->name;
                    $desc = "Pledge payment — {$pledge->reference}" . ($purposeName ? " ({$purposeName})" : '');

                    $transaction = \App\Models\Transaction::create([
                        'type'             => 'Pledge',
                        'account_id'       => $accountId,
                        'category'         => 'Income',
                        'amount'           => $payment->amount,
                        'member_id'        => $pledge->member_id,
                        'payer_name'       => $pledge->pledger_label,
                        'transaction_date' => $payment->payment_date,
                        'payment_method'   => $payment->payment_method,
                        'status'           => 'Confirmed',
                        'description'      => $desc,
                        'recorded_by'      => $payment->recorded_by,
                    ]);

                    if ($accountId && $cashAccountId) {
                        $ledger = new \App\Services\LedgerService();
                        $ledger->postIncome(
                            (float) $payment->amount,
                            $accountId,
                            $cashAccountId,
                            $payment->payment_date,
                            $desc,
                            'transaction',
                            $transaction->id
                        );
                    }

                    $payment->transaction_id = $transaction->id;
                    $payment->saveQuietly();
                }
            }
            optional($payment->pledge)->refreshStatus();
        });
        static::deleted(function ($payment) {
            // Also void the linked finance transaction so totals stay correct
            if ($payment->transaction) {
                $payment->transaction->delete();
            }
            optional($payment->pledge)->refreshStatus();
        });
    }
}

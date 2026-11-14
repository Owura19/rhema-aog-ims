<?php

namespace App\Support\Giving;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Everything ONE member has given, as rows of (source, id, reference, receipt_number,
 * date, type, amount, currency, payment_method):
 *
 *  - transactions: the member's confirmed income (voided rows are soft-deleted and left out);
 *  - pledge_payments that never became a transaction (transaction_id null: mostly older,
 *    imported payments) on the member's own pledges, shown as type "Pledge".
 *
 * A pledge payment that has a transaction is already in the first part, so nothing is
 * counted twice. The member id always comes from the signed-in login, never the request.
 */
final class MemberGiving
{
    public function __construct(private readonly int $memberId)
    {
    }

    /** The union of both sources, ready to be wrapped (fromSub) for filters, sums and paging. */
    public function rows(): Builder
    {
        $transactions = DB::table('transactions')
            ->selectRaw("'transaction' AS source, id, reference, receipt_number, transaction_date AS date, type, amount, currency, payment_method")
            ->where('member_id', $this->memberId)
            ->where('category', 'Income')
            ->where('status', 'Confirmed')
            ->whereNull('deleted_at');

        $pledgePayments = DB::table('pledge_payments')
            ->join('pledges', 'pledges.id', '=', 'pledge_payments.pledge_id')
            ->selectRaw("'pledge_payment' AS source, pledge_payments.id, pledges.reference, NULL AS receipt_number, pledge_payments.payment_date AS date, 'Pledge' AS type, pledge_payments.amount, pledges.currency, pledge_payments.payment_method")
            ->where('pledges.member_id', $this->memberId)
            ->whereNull('pledge_payments.transaction_id')
            ->whereNull('pledge_payments.deleted_at')
            ->whereNull('pledges.deleted_at');

        return $transactions->unionAll($pledgePayments);
    }

    /** The rows as a table "g", to filter, group and page. */
    public function query(): Builder
    {
        return DB::query()->fromSub($this->rows(), 'g');
    }
}

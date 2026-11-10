<?php

namespace Tests\Feature;

use App\Http\Controllers\FinanceController;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Transactions PDF export (finance.pdf):
 *  - TOTALS shows the signed NET (income − expense), exactly once, after the last row,
 *    with all rows in a single table;
 *  - with no date filter the export covers the CURRENT MONTH, and says so;
 *  - an explicit date range still overrides that default;
 *  - more than 400 rows → redirect back to Transactions with a message, no PDF built.
 *
 * The real route returns a compressed PDF, so the PDF builder is swapped for a
 * stand-in that returns the rendered HTML of finance/pdf.blade.php. The real
 * controller still runs, so data, $stats and the cap come from exportPdf().
 */
class FinancePdfTotalsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    /** Stand-in PDF builder; ->loaded tells whether a PDF was started at all. */
    private object $pdf;

    /** exportPdf() raises memory_limit (and sets a 55 s time limit); both are put back after each test. */
    private string $originalMemoryLimit;

    protected function setUp(): void
    {
        $this->originalMemoryLimit = (string) ini_get('memory_limit');

        parent::setUp();

        // Freeze "now" at 15 June 2026 so "current month" = June 2026 in every test.
        $this->travelTo(now()->setDate(2026, 6, 15)->setTime(12, 0));

        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();
        Permission::findOrCreate('view finance', 'web');

        $this->user = User::factory()->create();
        $this->user->givePermissionTo('view finance');
        $registrar->forgetCachedPermissions();

        // Same calls the controller makes, but stream() returns the rendered HTML.
        // The Pdf facade resolves 'dompdf.wrapper' from the container on EVERY
        // call (it skips the facade cache), so register the stand-in there.
        $this->pdf = new class {
            public bool $loaded = false;
            private string $view = '';
            private array $data = [];

            public function loadView($view, $data = [], $mergeData = [], $encoding = null): static
            {
                $this->loaded = true;
                $this->view = $view;
                $this->data = $data;
                return $this;
            }

            public function setPaper(...$args): static { return $this; }

            public function setOptions(...$args): static { return $this; }

            public function stream($filename = 'document.pdf')
            {
                return response(view($this->view, $this->data)->render());
            }
        };
        $this->app->instance('dompdf.wrapper', $this->pdf);
    }

    protected function tearDown(): void
    {
        // FinanceController::exportPdf() calls set_time_limit(55) and ini_set('memory_limit', '512M'),
        // which would otherwise last for the rest of the test run.
        ini_set('memory_limit', $this->originalMemoryLimit);
        set_time_limit(0);

        parent::tearDown();
    }

    // ── TOTALS ─────────────────────────────────────────────────────────

    public static function totals(): array
    {
        return [
            'income exceeds expense' => [1000.00, 400.00, '+ GHS 600.00', 'income',  '1,400.00'],
            'expense exceeds income' => [300.00,  500.00, '- GHS 200.00', 'expense', '800.00'],
        ];
    }

    #[DataProvider('totals')]
    public function test_totals_row_shows_signed_net(
        float $income,
        float $expense,
        string $expectedTotal,
        string $expectedClass,
        string $wrongSum
    ): void {
        $in  = $this->transaction('Income', $income, '2026-06-10');
        $out = $this->transaction('Expense', $expense, '2026-06-11');

        $html = $this->exportHtml();

        // The test data really is in the PDF (an empty PDF must not pass).
        $this->assertStringContainsString('2 transactions', $html);
        $this->assertMatchesRegularExpression('#<tbody>.*' . preg_quote($in->reference, '#') . '.*</tbody>#s', $html);
        $this->assertMatchesRegularExpression('#<tbody>.*' . preg_quote($out->reference, '#') . '.*</tbody>#s', $html);

        $tfoot = $this->onlyTfoot($html);
        $this->assertStringContainsString($expectedTotal, $tfoot, 'TOTALS should show the signed net');
        $this->assertStringContainsString('class="num ' . $expectedClass . '"', $tfoot, 'TOTALS colour should follow the net sign');
        $this->assertStringNotContainsString($wrongSum, $tfoot, 'TOTALS must not be income + expense');
    }

    public function test_totals_appear_once_after_the_last_row_with_many_rows(): void
    {
        // 120 rows, all in one table. Net = 100×10 − 20×20 = 600.
        for ($i = 0; $i < 100; $i++) {
            $this->transaction('Income', 10.00, '2026-06-05');
        }
        for ($i = 0; $i < 20; $i++) {
            $this->transaction('Expense', 20.00, '2026-06-06');
        }

        $html = $this->exportHtml();

        $this->assertStringContainsString('120 transactions', $html);
        $this->assertSame(1, substr_count($html, '<table class="data">'), 'all rows should be in a single table');
        $this->assertSame(120, preg_match_all('#<td>TXN-\d+</td>#', $html), 'every row should be rendered once');

        $tfoot = $this->onlyTfoot($html);
        $this->assertStringContainsString('+ GHS 600.00', $tfoot);

        // TOTALS comes after the very last transaction row.
        preg_match_all('#<td>TXN-\d+</td>#', $html, $rows, PREG_OFFSET_CAPTURE);
        $lastRowAt = end($rows[0])[1];
        $this->assertGreaterThan($lastRowAt, strpos($html, '<tfoot>'), 'TOTALS should follow the last row');
    }

    // ── PERIOD ─────────────────────────────────────────────────────────

    public function test_no_date_filter_exports_the_current_month_and_says_so(): void
    {
        $firstOfMonth = $this->transaction('Income', 10, '2026-06-01');
        $lastOfMonth  = $this->transaction('Income', 10, '2026-06-30');
        $prevMonth    = $this->transaction('Income', 10, '2026-05-31');
        $sameYear     = $this->transaction('Income', 10, '2026-01-05');

        $html = $this->exportHtml();

        $this->assertStringContainsString('Period: June 2026', $html);
        $this->assertStringContainsString('2 transactions', $html);
        $this->assertStringContainsString($firstOfMonth->reference, $html);
        $this->assertStringContainsString($lastOfMonth->reference, $html);
        $this->assertStringNotContainsString($prevMonth->reference, $html);
        $this->assertStringNotContainsString($sameYear->reference, $html);
    }

    public function test_explicit_date_range_overrides_the_month_default(): void
    {
        $may  = $this->transaction('Income', 10, '2026-05-31');
        $june = $this->transaction('Income', 10, '2026-06-01');
        $jan  = $this->transaction('Income', 10, '2026-01-05');

        $html = $this->exportHtml(['date_from' => '2026-05-01', 'date_to' => '2026-06-30']);

        $this->assertStringContainsString('Period: May 01, 2026 to Jun 30, 2026', $html);
        $this->assertStringContainsString('2 transactions', $html);
        $this->assertStringContainsString($may->reference, $html);
        $this->assertStringContainsString($june->reference, $html);
        $this->assertStringNotContainsString($jan->reference, $html);
    }

    // ── SIZE CAP ───────────────────────────────────────────────────────

    public static function capBoundary(): array
    {
        return [
            'exactly 400 rows → PDF'  => [400, true],
            '401 rows → no PDF'       => [401, false],
        ];
    }

    #[DataProvider('capBoundary')]
    public function test_export_is_capped_at_400_rows(int $rows, bool $allowed): void
    {
        $this->assertSame(400, FinanceController::PDF_MAX_ROWS);
        $this->bulkTransactions($rows, '2026-06-10');
        $filters = ['date_from' => '2026-06-01', 'date_to' => '2026-06-30'];

        $response = $this->actingAs($this->user)->get(route('finance.pdf', $filters));

        if ($allowed) {
            $response->assertOk();
            $this->assertTrue($this->pdf->loaded, 'a PDF should be built');
            $this->assertStringContainsString('400 transactions', $response->getContent());
            return;
        }

        $response->assertRedirect(route('finance.index', $filters));
        $response->assertSessionHas('error', fn (string $message) =>
            str_contains($message, '401 transactions')
            && str_contains($message, 'maximum 400')
            && str_contains($message, 'Export Excel'));
        $this->assertFalse($this->pdf->loaded, 'no PDF should be built over the cap');
    }

    // ── HELPERS ────────────────────────────────────────────────────────

    private function transaction(string $category, float $amount, string $date): Transaction
    {
        return Transaction::create([
            'type'             => $category === 'Income' ? 'Offering' : 'Expense',
            'category'         => $category,
            'amount'           => $amount,
            'payer_name'       => 'Test ' . $category,
            'transaction_date' => $date,
            'payment_method'   => 'Cash',
            'status'           => 'Confirmed',
        ]);
    }

    /** Fast insert for the cap tests (bypasses model events, so references are set here). */
    private function bulkTransactions(int $count, string $date): void
    {
        $now  = now();
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = [
                'reference'        => sprintf('BULK-%05d', $i),
                'type'             => 'Offering',
                'category'         => 'Income',
                'amount'           => 1,
                'transaction_date' => $date,
                'payment_method'   => 'Cash',
                'status'           => 'Confirmed',
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('transactions')->insert($chunk);
        }
    }

    private function exportHtml(array $query = []): string
    {
        $response = $this->actingAs($this->user)->get(route('finance.pdf', $query));
        $response->assertOk();
        $this->assertTrue($this->pdf->loaded, 'a PDF should be built');

        return $response->getContent();
    }

    /** Asserts there is exactly one TOTALS row and returns it (whitespace collapsed). */
    private function onlyTfoot(string $html): string
    {
        $this->assertSame(1, substr_count($html, '<tfoot>'), 'there should be exactly one TOTALS row');
        $this->assertSame(1, substr_count($html, 'TOTALS'), '"TOTALS" should appear exactly once');
        preg_match('#<tfoot>(.*?)</tfoot>#s', $html, $m);

        return preg_replace('/\s+/', ' ', $m[1]);
    }
}

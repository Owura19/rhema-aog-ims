<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The transactions PDF (finance.pdf) TOTALS row must show the NET
 * (income − expense), signed and coloured like the rows — not income + expense.
 *
 * The real route returns a compressed PDF, so the PDF builder is swapped for a
 * stand-in that returns the rendered HTML of finance/pdf.blade.php. The real
 * controller still runs, so $stats comes from FinanceController::exportPdf().
 */
class FinancePdfTotalsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // exportPdf() defaults to the current year when no date filter is given;
        // freeze the clock so the test transactions below are always "this year".
        $this->travelTo(now()->setDate(2026, 6, 15)->setTime(12, 0));

        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();
        Permission::findOrCreate('view finance', 'web');

        $this->user = User::factory()->create();
        $this->user->givePermissionTo('view finance');
        $registrar->forgetCachedPermissions();

        // Stand-in PDF builder: same calls the controller makes, but stream()
        // returns the rendered HTML so the TOTALS row can be inspected.
        // The Pdf facade resolves 'dompdf.wrapper' from the container on EVERY
        // call (it skips the facade cache), so register the stand-in there.
        $this->app->instance('dompdf.wrapper', new class {
            private string $view = '';
            private array $data = [];

            public function loadView($view, $data = [], $mergeData = [], $encoding = null): static
            {
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
        });
    }

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
        $in = Transaction::create([
            'type'             => 'Offering',
            'category'         => 'Income',
            'amount'           => $income,
            'payer_name'       => 'Test Payer',
            'transaction_date' => '2026-06-10',
            'payment_method'   => 'Cash',
            'status'           => 'Confirmed',
        ]);
        $out = Transaction::create([
            'type'             => 'Expense',
            'category'         => 'Expense',
            'amount'           => $expense,
            'payer_name'       => 'Test Vendor',
            'transaction_date' => '2026-06-11',
            'payment_method'   => 'Cash',
            'status'           => 'Confirmed',
        ]);

        $response = $this->actingAs($this->user)->get(route('finance.pdf'));
        $response->assertOk();
        $html = $response->getContent();

        // The test data really is in the PDF (an empty PDF must not pass).
        $this->assertStringContainsString('2 transactions', $html);
        $this->assertMatchesRegularExpression('#<tbody>.*' . preg_quote($in->reference, '#') . '.*</tbody>#s', $html);
        $this->assertMatchesRegularExpression('#<tbody>.*' . preg_quote($out->reference, '#') . '.*</tbody>#s', $html);

        // Isolate the TOTALS row.
        $this->assertSame(1, preg_match('#<tfoot>(.*?)</tfoot>#s', $html, $m), 'TOTALS row (tfoot) not found');
        $tfoot = preg_replace('/\s+/', ' ', $m[1]);

        $this->assertStringContainsString('TOTALS', $tfoot);
        $this->assertStringContainsString($expectedTotal, $tfoot, 'TOTALS should show the signed net');
        $this->assertStringContainsString('class="num ' . $expectedClass . '"', $tfoot, 'TOTALS colour should follow the net sign');
        $this->assertStringNotContainsString($wrongSum, $tfoot, 'TOTALS must not be income + expense');
    }
}

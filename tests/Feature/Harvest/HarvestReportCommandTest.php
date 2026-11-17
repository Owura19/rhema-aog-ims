<?php

namespace Tests\Feature\Harvest;

use App\Models\Harvest;
use App\Models\HarvestLinkEvent;
use App\Models\JournalEntry;
use App\Models\Member;
use App\Models\Pledge;
use App\Models\PledgePayment;
use App\Models\PledgePurpose;
use App\Models\Transaction;
use App\Services\HarvestLinker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** harvest:report — read-only, counts and references only. */
class HarvestReportCommandTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $h = Harvest::create(['name' => 'Harvest 2026', 'year' => 2026, 'target_amount' => 1, 'pledge_opens' => '2026-08-01', 'harvest_date' => '2026-12-12', 'status' => 'Active']);
        $purpose = PledgePurpose::where('name', 'Harvest')->value('id');
        $kofi = Member::create(['first_name' => 'Kofi', 'last_name' => 'Mensah']);
        $ama  = Member::create(['first_name' => 'Ama', 'last_name' => 'Owusu']);
        $pledge = Pledge::create(['member_id' => $kofi->id, 'pledge_purpose_id' => $purpose, 'harvest_id' => $h->id, 'amount_pledged' => 777, 'date_pledged' => '2026-08-02', 'status' => 'Active']);
        $paid = Pledge::create(['pledger_name' => 'Yaw Boateng', 'pledge_purpose_id' => $purpose, 'harvest_id' => $h->id, 'amount_pledged' => 50, 'date_pledged' => '2026-08-02', 'status' => 'Active']);
        DB::table('pledge_payments')->insert(['pledge_id' => $paid->id, 'amount' => 50, 'payment_date' => '2026-09-01', 'payment_method' => 'Cash', 'created_at' => now(), 'updated_at' => now()]);
        $loose = Pledge::create(['member_id' => $ama->id, 'pledge_purpose_id' => $purpose, 'amount_pledged' => 10, 'date_pledged' => '2026-08-02', 'status' => 'Active']);

        $tx = fn (Member $m, string $desc, string $date = '2026-10-04') => Transaction::create([
            'type' => 'Harvest', 'category' => 'Income', 'amount' => 123.45, 'member_id' => $m->id,
            'transaction_date' => $date, 'payment_method' => 'Cash', 'status' => 'Confirmed', 'description' => $desc,
        ]);
        $linked = $tx($kofi, 'HARVEST 2026');
        $tx($kofi, 'HARVEST 2026');
        $tx($ama, 'Harvest', '2026-09-01');
        app(HarvestLinker::class)->linkTo($linked, $h, $pledge, null, HarvestLinkEvent::SOURCE_REVIEW);

        return compact('h', 'paid', 'loose');
    }

    public function test_it_reports_counts_and_references_and_changes_nothing(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 10:00:00'));
        $s = $this->scenario();
        $count = fn () => [Pledge::withTrashed()->count(), PledgePayment::withTrashed()->count(), Transaction::withTrashed()->count(), JournalEntry::count(), HarvestLinkEvent::count(), Pledge::pluck('status', 'id')->all()];
        $before = $count();

        $this->assertSame(0, Artisan::call('harvest:report'));
        $out = Artisan::output();

        $this->assertSame($before, $count());
        $this->assertStringContainsString('Harvest transactions waiting to be linked: 2 (without flags: 1)', $out);
        $this->assertStringContainsString("suggested #{$s['h']->id}:", $out);
        $this->assertMatchesRegularExpression('/flag Campaign from the date: check:\s+1/', $out);
        $this->assertMatchesRegularExpression('/from the review page:\s+1/', $out);
        $this->assertMatchesRegularExpression('/Active but fully paid:\s+1/', $out);
        $this->assertStringContainsString($s['paid']->reference, $out);
        $this->assertStringContainsString('Harvest-purpose pledges with no harvest set (fix by hand): 1', $out);
        $this->assertStringContainsString($s['loose']->reference, $out);

        foreach (['Kofi', 'Mensah', 'Ama', 'Owusu', 'Yaw', 'Boateng', '123.45', '777', 'Harvest 2026'] as $secret) {
            $this->assertStringNotContainsString($secret, $out);
        }
    }

    public function test_the_harvest_option_and_unknown_harvest(): void
    {
        $s = $this->scenario();

        $this->assertSame(0, Artisan::call('harvest:report', ['--harvest' => $s['h']->id]));
        $this->assertStringContainsString("Pledge status checks (harvest #{$s['h']->id})", Artisan::output());

        $this->assertSame(1, Artisan::call('harvest:report', ['--harvest' => 999]));
    }

    public function test_the_old_linking_command_is_gone(): void
    {
        $this->assertArrayNotHasKey('harvest:link-transactions', Artisan::all());
        $this->assertArrayHasKey('harvest:report', Artisan::all());
    }
}

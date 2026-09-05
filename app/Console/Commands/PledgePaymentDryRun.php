<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Pledge;
use App\Models\Member;

class PledgePaymentDryRun extends Command
{
    protected $signature = 'pledgepay:dryrun {file}';
    protected $description = 'Dry-run pledge payment import — reports, writes nothing';

    public function handle()
    {
        $path = $this->argument('file');
        if (!is_readable($path)) { $this->error("Cannot read {$path}"); return 1; }
        $lines = array_filter(array_map('trim', file($path)));

        $titles = ['MR','MRS','MISS','MAD','MADAM','DEAC','DEACON','DEA','NANA','HON','REV','DR','PROF'];
        $norm = function($s) use ($titles) {
            $s = strtoupper(str_replace(['&','.',"'"], ' ', $s));
            $s = preg_replace('/\([^)]*\)/', '', $s);
            $parts = array_filter(preg_split('/\s+/', $s), fn($w) => $w && !in_array($w,$titles) && !in_array($w,['FAMILY','WIFE','THE','JNR','SNR']) && strlen($w)>1);
            sort($parts);
            return implode(' ', $parts);
        };

        // index existing pledges by normalized name (member name or pledger_name)
        $pledgeIdx = [];
        foreach (Pledge::whereNull('deleted_at')->get() as $pl) {
            if ($pl->member_id) {
                $m = Member::find($pl->member_id);
                $nm = $m ? trim($m->first_name.' '.$m->last_name) : '';
            } else {
                $nm = $pl->pledger_name ?? '';
            }
            if ($nm) $pledgeIdx[$norm($nm)][] = $pl->id;
        }

        $matchedPmts = 0; $matchedAmt = 0; $matchHit = 0; $matchMiss = [];
        $newPayers = []; $newPmts = 0; $newAmt = 0;

        foreach ($lines as $ln) {
            $p = explode('|', $ln);
            if (count($p) < 5) continue;
            [$tag,$payer,$pledgeName,$amt,$date] = $p;
            $amt = (float)$amt;
            if ($tag === 'M') {
                $key = $norm($pledgeName);
                if (isset($pledgeIdx[$key])) { $matchHit++; $matchedPmts++; $matchedAmt += $amt; }
                else { $matchMiss[$pledgeName] = ($matchMiss[$pledgeName] ?? 0) + $amt; }
            } else {
                $newPayers[$payer] = ($newPayers[$payer] ?? 0) + $amt;
                $newPmts++; $newAmt += $amt;
            }
        }

        $this->info("=== PLEDGE PAYMENT DRY-RUN (nothing written) ===");
        $this->line("MATCHED payments landing on existing pledges: {$matchedPmts}  (GHS " . number_format($matchedAmt,2) . ")");
        $this->line("NEW-giver payments: {$newPmts} across " . count($newPayers) . " new pledges  (GHS " . number_format($newAmt,2) . ")");
        $this->line("");
        if ($matchMiss) {
            $this->error("!! " . count($matchMiss) . " matched-tag names did NOT find their pledge (would be skipped):");
            foreach ($matchMiss as $nm=>$a) $this->line("   MISS: {$nm}  (GHS " . number_format($a,2) . ")");
        } else {
            $this->info("All 'M' payments found their existing pledge. ✓");
        }
        $this->line("");
        $this->line("TOTAL to import: " . ($matchedPmts+$newPmts) . " payments, GHS " . number_format($matchedAmt+$newAmt,2));
        return 0;
    }
}

<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Member;
use App\Models\Pledge;
use Illuminate\Support\Facades\DB;

class PledgeImport extends Command
{
    protected $signature = 'pledges:import {file} {--purpose=2}';
    protected $description = 'Import pledges from a pipe-delimited file';

    public function handle()
    {
        $path = $this->argument('file');
        $purposeId = (int) $this->option('purpose');
        if (!is_readable($path)) { $this->error("Cannot read {$path}"); return 1; }
        $lines = array_filter(array_map('trim', file($path)));
        $titles = ['MR','MRS','MISS','MAD','MADAM','DEAC','DEACON','DEA','NANA','HON','REV','DR','PROF'];

        // next reference number
        $last = Pledge::withTrashed()->max('id') ?? 0;
        $seq = $last;

        $matched = 0; $free = 0; $total = 0.0;

        DB::beginTransaction();
        try {
            foreach ($lines as $ln) {
                $p = array_pad(explode('|', $ln), 3, '');
                $name = trim($p[0]); $pledged = (float)$p[1];
                $total += $pledged;
                if ($pledged <= 0 || $name === '') continue;

                $isCouple = (strpos($name, '&') !== false) || preg_match('/\b(FAMILY|WIFE|HUSBAND)\b/i', $name);
                $matchId = null;
                if (!$isCouple) {
                    $parts = preg_split('/\s+/', $name);
                    while (count($parts)) {
                        $head = strtoupper(rtrim($parts[0], '.'));
                        if (in_array($head, $titles)) array_shift($parts); else break;
                    }
                    $parts = array_values(array_filter($parts, fn($x) => strlen(rtrim($x, '.')) > 1));
                    if (count($parts) >= 2) {
                        $m = Member::whereRaw('LOWER(first_name)=?', [strtolower($parts[0])])
                             ->whereRaw('LOWER(last_name)=?', [strtolower(end($parts))])->first();
                        if ($m) $matchId = $m->id;
                    }
                }

                $seq++;
                $ref = 'PLG-' . str_pad($seq, 5, '0', STR_PAD_LEFT);

                Pledge::create([
                    'reference'         => $ref,
                    'member_id'         => $matchId,
                    'pledger_name'      => $matchId ? null : $name,
                    'pledge_purpose_id' => $purposeId,
                    'amount_pledged'    => $pledged,
                    'currency'          => 'GHS',
                    'date_pledged'      => now()->toDateString(),
                    'status'            => 'Active',
                    'notes'             => 'Imported from Harvest pledge sheet',
                    'recorded_by'       => 1,
                ]);

                if ($matchId) $matched++; else $free++;
            }
            DB::commit();
            $this->info("=== IMPORT COMPLETE ===");
            $this->line("Imported: " . ($matched + $free) . " pledges");
            $this->line("  Matched to members: {$matched}");
            $this->line("  Free-text: {$free}");
            $this->line("  Total pledged: GHS " . number_format($total, 2));
            return 0;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("ROLLED BACK: " . $e->getMessage());
            return 1;
        }
    }
}

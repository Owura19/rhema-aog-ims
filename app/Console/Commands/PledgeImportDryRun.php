<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Member;

class PledgeImportDryRun extends Command
{
    protected $signature = 'pledges:dryrun {file}';
    protected $description = 'Dry-run pledge import — reports matches, writes nothing';

    public function handle()
    {
        $path = $this->argument('file');
        if (!is_readable($path)) { $this->error("Cannot read {$path}"); return 1; }
        $lines = array_filter(array_map('trim', file($path)));
        $titles = ['MR','MRS','MISS','MAD','MADAM','DEAC','DEACON','DEA','NANA','HON','REV','DR','PROF'];

        $matched = 0; $free = 0; $total = 0.0; $samples = []; $freeSamples = [];
        foreach ($lines as $ln) {
            $p = array_pad(explode('|', $ln), 3, '');
            $name = trim($p[0]); $total += (float)$p[1];
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
            if ($matchId) { $matched++; if (count($samples) < 20) $samples[] = "  {$name}  ->  #{$matchId}"; }
            else { $free++; if (count($freeSamples) < 12) $freeSamples[] = "  {$name}"; }
        }
        $this->info("=== PLEDGE IMPORT DRY-RUN (nothing written) ===");
        $this->line("Total pledges: " . count($lines));
        $this->line("Total pledged: GHS " . number_format($total, 2));
        $this->line("Matched to members: {$matched}");
        $this->line("Free-text: {$free}");
        $this->line("\n--- sample MATCHES (verify!) ---");
        foreach ($samples as $s) $this->line($s);
        $this->line("\n--- sample FREE-TEXT ---");
        foreach ($freeSamples as $s) $this->line($s);
        return 0;
    }
}

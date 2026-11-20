<?php

namespace App\Console\Commands;

use App\Models\Member;
use Illuminate\Console\Command;

/**
 * READ-ONLY: fingerprint IDs given to more than one member. Punches with such an ID cannot be
 * attributed to anyone (they stay unmatched) until the duplicates are fixed by hand.
 * Prints IDs and member counts only: no names.
 */
class BiometricDuplicateIds extends Command
{
    protected $signature = 'biometric:duplicate-ids';

    protected $description = 'Read-only: list fingerprint IDs shared by more than one member (IDs and counts only)';

    public function handle(): int
    {
        $rows = Member::whereNotNull('fingerprint_id')
            ->selectRaw('fingerprint_id, COUNT(*) AS members')
            ->groupBy('fingerprint_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('fingerprint_id')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No duplicate fingerprint IDs.');

            return self::SUCCESS;
        }

        $this->line("Fingerprint IDs shared by more than one member: {$rows->count()}");
        foreach ($rows as $row) {
            $this->line("  ID {$row->fingerprint_id}: {$row->members} members");
        }

        return self::SUCCESS;
    }
}

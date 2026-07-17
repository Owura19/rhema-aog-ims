<?php

namespace App\Console\Commands;

use App\Models\Member;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportMembers extends Command
{
    protected $signature = 'members:import {file : Path to the CSV file} {--dry-run : Preview without saving} {--created-by=1 : User ID to record as creator}';
    protected $description = 'Import members from a cleaned CSV file';

    public function handle()
    {
        $path = $this->argument('file');
        if (!file_exists($path)) {
            $this->error("File not found: {$path}");
            return 1;
        }

        $dryRun    = $this->option('dry-run');
        $createdBy = (int) $this->option('created-by');

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);
        $header = array_map('trim', $header);

        // Find the highest existing RIMS number so we continue the sequence
        $lastNum = Member::where('member_id', 'like', 'RIMS-%')
            ->get()
            ->map(fn($m) => (int) str_replace('RIMS-', '', $m->member_id))
            ->max() ?? 0;

        $created = 0; $skipped = 0; $errors = 0; $rowNum = 1;
        $seq = $lastNum;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            $data = array_combine($header, $row);

            $first = trim($data['first_name'] ?? '');
            $last  = trim($data['last_name'] ?? '');

            if ($first === '' || $last === '') {
                $skipped++;
                continue;
            }

            // Skip if an identical member already exists (same first+last+phone)
            $exists = Member::where('first_name', $first)
                ->where('last_name', $last)
                ->where('phone', $data['phone'] ?? '')
                ->when(($data['phone'] ?? '') === '', fn($q) => $q->whereNull('phone')->orWhere('phone', ''))
                ->exists();
            if ($exists) {
                $skipped++;
                continue;
            }

            $seq++;
            $memberId = 'RIMS-' . str_pad($seq, 4, '0', STR_PAD_LEFT);

            $attributes = [
                'member_id'         => $memberId,
                'first_name'        => $first,
                'other_name'        => trim($data['other_name'] ?? '') ?: null,
                'last_name'         => $last,
                'gender'            => trim($data['gender'] ?? '') ?: null,
                'date_of_birth'     => trim($data['date_of_birth'] ?? '') ?: null,
                'marital_status'    => trim($data['marital_status'] ?? '') ?: null,
                'phone'             => trim($data['phone'] ?? '') ?: null,
                'membership_status' => trim($data['membership_status'] ?? '') ?: 'Active',
                'notes'             => trim($data['notes'] ?? '') ?: null,
                'created_by'        => $createdBy,
            ];

            if ($dryRun) {
                $created++;
                if ($created <= 5) {
                    $this->line("[DRY] {$memberId}: {$first} {$last} ({$attributes['membership_status']})");
                }
                continue;
            }

            try {
                Member::create($attributes);
                $created++;
                if ($created % 100 === 0) {
                    $this->info("...{$created} imported");
                }
            } catch (\Throwable $e) {
                $errors++;
                $this->warn("Row {$rowNum} ({$first} {$last}): " . $e->getMessage());
            }
        }
        fclose($handle);

        $this->newLine();
        $this->info($dryRun ? "DRY RUN complete." : "Import complete.");
        $this->table(['Result', 'Count'], [
            ['Created', $created],
            ['Skipped (blank/duplicate)', $skipped],
            ['Errors', $errors],
        ]);

        return 0;
    }
}

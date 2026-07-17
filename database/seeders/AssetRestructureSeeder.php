<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\JournalLine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Restructures the ASSET accounts to match the church manager's spec:
 *
 *   CURRENT ASSETS (operational money — needed for double-entry postings)
 *     - Cash on Hand
 *     - Bank Account
 *
 *   FIXED ASSETS (master code)
 *     - Computer & Accessories
 *     - Furniture and Fittings
 *     - Equipment and Fixtures
 *     - Buildings
 *
 *   LIQUID INVESTMENT (master code)
 *     - Treasury Bill
 *     - Fixed Deposit
 *
 * Liabilities and Equity are left unchanged.
 * Accounts that already have journal activity are preserved (reused), never deleted.
 */
class AssetRestructureSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {

            // ── Helper: does this account code have journal activity? ──
            $hasActivity = function (?Account $a): bool {
                if (!$a) return false;
                return JournalLine::where('account_id', $a->id)->exists();
            };

            // ── 1. CURRENT ASSETS group ──────────────────────────
            $current = Account::updateOrCreate(
                ['code' => '1000'],
                ['ref' => 'CA', 'name' => 'CURRENT ASSETS', 'type' => 'Asset', 'parent_id' => null, 'is_group' => true, 'is_active' => true, 'sort_order' => 1000]
            );

            // Cash on Hand — reuse existing 1100 (may hold journal activity)
            Account::updateOrCreate(
                ['code' => '1100'],
                ['name' => 'Cash on Hand', 'type' => 'Asset', 'parent_id' => $current->id, 'is_group' => false, 'is_active' => true, 'sort_order' => 1110]
            );

            // Bank Account — reuse existing 1200
            Account::updateOrCreate(
                ['code' => '1200'],
                ['name' => 'Bank Account', 'type' => 'Asset', 'parent_id' => $current->id, 'is_group' => false, 'is_active' => true, 'sort_order' => 1120]
            );

            // ── 2. FIXED ASSETS group ────────────────────────────
            $fixed = Account::updateOrCreate(
                ['code' => '1400'],
                ['ref' => 'FA', 'name' => 'FIXED ASSETS', 'type' => 'Asset', 'parent_id' => null, 'is_group' => true, 'is_active' => true, 'sort_order' => 1400]
            );

            $fixedAccounts = [
                ['1410', 'Computer & Accessories'],
                ['1420', 'Furniture and Fittings'],
                ['1430', 'Equipment and Fixtures'],
                ['1440', 'Buildings'],
            ];
            foreach ($fixedAccounts as [$code, $name]) {
                Account::updateOrCreate(
                    ['code' => $code],
                    ['name' => $name, 'type' => 'Asset', 'parent_id' => $fixed->id, 'is_group' => false, 'is_active' => true, 'sort_order' => (int) $code]
                );
            }

            // ── 3. LIQUID INVESTMENT group ───────────────────────
            $liquid = Account::updateOrCreate(
                ['code' => '1500'],
                ['ref' => 'LQ', 'name' => 'LIQUID INVESTMENT', 'type' => 'Asset', 'parent_id' => null, 'is_group' => true, 'is_active' => true, 'sort_order' => 1500]
            );

            $liquidAccounts = [
                ['1510', 'Treasury Bill'],
                ['1520', 'Fixed Deposit'],
            ];
            foreach ($liquidAccounts as [$code, $name]) {
                Account::updateOrCreate(
                    ['code' => $code],
                    ['name' => $name, 'type' => 'Asset', 'parent_id' => $liquid->id, 'is_group' => false, 'is_active' => true, 'sort_order' => (int) $code]
                );
            }

            // ── 4. Clean up old generic accounts that no longer fit ──
            //     Only delete if they have NO journal activity.
            $obsolete = ['1300', '1600']; // old Bank-Savings, Motor Vehicle
            foreach ($obsolete as $code) {
                $acct = Account::where('code', $code)->first();
                if ($acct && !$hasActivity($acct)) {
                    $acct->delete();
                }
            }
        });

        $this->command->info('Asset accounts restructured: Current Assets, Fixed Assets, Liquid Investment.');
    }
}
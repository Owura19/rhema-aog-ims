<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class BalanceSheetAccountsSeeder extends Seeder
{
    /**
     * Creates the standard church Asset, Liability and Equity accounts.
     * Codes follow universal accounting order:
     *   1000s = Assets, 2000s = Liabilities, 3000s = Equity
     * (Your existing 4000s = Income, 5000s = Expense.)
     *
     * All are created as postable accounts; opening balances start at zero
     * and are entered later via journal entries / the opening-balances screen.
     */
    public function run(): void
    {
        // ── ASSETS (1000s) ──────────────────────────────────────
        $assets = Account::firstOrCreate(
            ['code' => '1000'],
            ['ref' => 'AS', 'name' => 'ASSETS', 'type' => 'Asset', 'is_group' => true, 'sort_order' => 1000]
        );

        $assetAccounts = [
            ['1100', 'Cash on Hand'],
            ['1200', 'Bank — Current Account'],
            ['1300', 'Bank — Savings / Fixed Deposit'],
            ['1400', 'Church Building'],
            ['1500', 'Furniture & Equipment'],
            ['1600', 'Motor Vehicle'],
        ];
        foreach ($assetAccounts as $i => [$code, $name]) {
            Account::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'type' => 'Asset', 'parent_id' => $assets->id, 'is_group' => false, 'sort_order' => 1000 + ($i + 1) * 10]
            );
        }

        // ── LIABILITIES (2000s) ─────────────────────────────────
        $liabilities = Account::firstOrCreate(
            ['code' => '2000'],
            ['ref' => 'LI', 'name' => 'LIABILITIES', 'type' => 'Liability', 'is_group' => true, 'sort_order' => 2000]
        );

        $liabilityAccounts = [
            ['2100', 'Loans Payable'],
            ['2200', 'Accounts Payable'],
        ];
        foreach ($liabilityAccounts as $i => [$code, $name]) {
            Account::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'type' => 'Liability', 'parent_id' => $liabilities->id, 'is_group' => false, 'sort_order' => 2000 + ($i + 1) * 10]
            );
        }

        // ── EQUITY (3000s) ──────────────────────────────────────
        $equity = Account::firstOrCreate(
            ['code' => '3000'],
            ['ref' => 'EQ', 'name' => 'EQUITY', 'type' => 'Equity', 'is_group' => true, 'sort_order' => 3000]
        );

        Account::firstOrCreate(
            ['code' => '3100'],
            ['name' => 'Accumulated Fund', 'type' => 'Equity', 'parent_id' => $equity->id, 'is_group' => false, 'sort_order' => 3010]
        );

        $this->command->info('Balance sheet accounts (Assets, Liabilities, Equity) created.');
    }
}
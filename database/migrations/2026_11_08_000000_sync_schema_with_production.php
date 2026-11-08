<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Brings the migrations in line with the PRODUCTION schema.
 *
 * These three columns were changed on production with manual ALTER TABLE
 * statements that never made it into a migration, so any database built
 * from migrations (the test DB, a fresh install) had the old definitions.
 *
 * The definitions below are copied exactly from production. On production
 * (and on the local production copy) this migration therefore changes nothing.
 *
 *   members.gender          NOT NULL               -> NULL
 *   members.marital_status  NOT NULL default Single -> NULL (no default)
 *   transactions.type       adds 'Project Offering' and 'Harvest'
 */
return new class extends Migration
{
    public function up(): void
    {
        // ENUM / MODIFY syntax is MySQL-specific; this app runs on MySQL only.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE `members` MODIFY `gender` ENUM('Male','Female') NULL DEFAULT NULL");

        DB::statement("ALTER TABLE `members` MODIFY `marital_status` ENUM('Single','Married','Divorced','Widowed') NULL DEFAULT NULL");

        DB::statement("ALTER TABLE `transactions` MODIFY `type` ENUM('Tithe','Offering','Project Offering','Harvest','First Fruit','Seed','Pledge','Donation','Expense','Other') NOT NULL");
    }

    public function down(): void
    {
        // Intentionally a NO-OP. Do NOT restore the old definitions.
        //
        // Production already had these definitions before this migration existed,
        // and real data depends on them:
        //   - transactions with type 'Project Offering' / 'Harvest'
        //     (restoring the old ENUM would fail or corrupt those rows)
        //   - members with NULL gender / marital_status
        //     (restoring NOT NULL would fail or silently rewrite them)
        //
        // Rolling this migration back only removes its row from `migrations`;
        // the columns keep the production definitions, and re-running up() is harmless.
    }
};

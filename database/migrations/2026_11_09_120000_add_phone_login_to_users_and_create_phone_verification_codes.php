<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mobile app logins by phone (Step 3a).
 *
 *  - users.email becomes nullable: members who activate the app may have no email.
 *    The unique index stays (MySQL allows many NULLs in a unique column).
 *  - users.login_phone: the normalised +233XXXXXXXXX the member logs in with.
 *    UNIQUE = one login per phone, enforced by the database. users.phone (staff
 *    contact number, typed freely) is left untouched.
 *  - phone_verification_codes: SMS one-time codes for activation / password reset.
 *    Codes and session tokens are stored only as HMAC-SHA256 hashes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('login_phone', 16)->nullable()->unique()->after('phone');
        });

        Schema::create('phone_verification_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 16);                  // normalised +233XXXXXXXXX
            $table->string('purpose', 20);                // 'activation' | 'password_reset'
            $table->char('code_hash', 64);                // HMAC-SHA256 of the 6-digit code
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');              // code valid 10 minutes
            $table->timestamp('verified_at')->nullable();
            $table->char('session_token_hash', 64)->nullable()->unique(); // after a correct code
            $table->timestamp('session_expires_at')->nullable();          // session valid 15 minutes
            $table->timestamp('consumed_at')->nullable();
            $table->string('request_ip', 45)->nullable();
            $table->timestamps();

            $table->index(['phone', 'purpose']);
        });
    }

    public function down(): void
    {
        // Check FIRST, so a refused rollback leaves everything exactly as it was.
        $withoutEmail = DB::table('users')->whereNull('email')->count();
        if ($withoutEmail > 0) {
            throw new RuntimeException(
                "Refusing to roll back: {$withoutEmail} user(s) have no email (phone-only app accounts). "
                . 'users.email cannot be made NOT NULL again until they are removed or given an email.'
            );
        }

        Schema::dropIfExists('phone_verification_codes');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['login_phone']);
            $table->dropColumn('login_phone');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};

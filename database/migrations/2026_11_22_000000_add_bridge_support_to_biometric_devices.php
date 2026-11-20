<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Biometric "bridge": a small program on a church PC reads the device on the LAN and POSTs the
 * punches to /api/v1/bridge. Such a device has no reachable IP, so ip_address / port become
 * optional, and the device gets a serial number and a connection key (stored only as a sha256
 * hash). Every punch received is kept in biometric_punches; re-sending one is harmless.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biometric_devices', function (Blueprint $table) {
            $table->string('serial_number', 100)->nullable()->unique()->after('name');
            $table->string('api_token_hash', 64)->nullable()->unique()->after('notes');
            $table->timestamp('token_created_at')->nullable()->after('api_token_hash');
            $table->timestamp('last_seen_at')->nullable()->after('last_synced_at');
            $table->timestamp('last_punch_at')->nullable()->after('last_seen_at');

            $table->string('ip_address')->nullable()->change();
            $table->integer('port')->nullable()->default(4370)->change();
        });

        Schema::create('biometric_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biometric_device_id')->constrained('biometric_devices')->cascadeOnDelete();
            $table->unsignedInteger('device_user_id');                 // the user number on the device
            $table->dateTime('punched_at');                            // device clock, as sent
            $table->unsignedSmallInteger('verify_type')->nullable();   // fingerprint / card / password ...
            $table->string('status', 20)->default('unmatched');        // matched | unmatched | no_service | duplicate_ignored | kept_manual
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('church_services')->nullOnDelete();
            $table->foreignId('attendance_log_id')->nullable()->constrained('attendance_logs')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            // Sending the same punch again changes nothing.
            $table->unique(['biometric_device_id', 'device_user_id', 'punched_at'], 'biometric_punches_unique_punch');
            $table->index(['biometric_device_id', 'status']);
            $table->index('punched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_punches');

        // ip_address / port stay nullable: rows without them may exist by now.
        Schema::table('biometric_devices', function (Blueprint $table) {
            $table->dropUnique(['serial_number']);
            $table->dropUnique(['api_token_hash']);
            $table->dropColumn(['serial_number', 'api_token_hash', 'token_created_at', 'last_seen_at', 'last_punch_at']);
        });
    }
};

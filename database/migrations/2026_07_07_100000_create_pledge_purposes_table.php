<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pledge_purposes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();      // e.g. Building Fund, Harvest 2026
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true); // hide without deleting
            $table->timestamps();
        });

        // Seed sensible defaults so the pledge form works immediately.
        $now = now();
        DB::table('pledge_purposes')->insert([
            ['name' => 'Building Fund',  'description' => 'Contributions toward church building projects', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Harvest',        'description' => 'Annual harvest contributions',                  'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Missions',       'description' => 'Support for missions and outreach',             'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Welfare',        'description' => 'Member welfare and benevolence fund',           'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Church Project', 'description' => 'General church projects and initiatives',        'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pledge_purposes');
    }
};
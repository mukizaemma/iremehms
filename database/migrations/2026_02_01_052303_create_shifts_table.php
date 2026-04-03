<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Morning Shift", "SYSTEM_SHIFT_2026-02-01"
            $table->string('code')->unique(); // Unique identifier for the shift
            $table->time('start_time'); // When shift starts (e.g., 08:00:00)
            $table->time('end_time'); // When shift ends (e.g., 16:00:00)
            $table->boolean('is_active')->default(true); // Can be enabled/disabled
            $table->boolean('is_system_generated')->default(false); // True for SYSTEM_SHIFT_* shifts
            $table->text('description')->nullable();
            $table->integer('order')->default(0); // For display ordering
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};

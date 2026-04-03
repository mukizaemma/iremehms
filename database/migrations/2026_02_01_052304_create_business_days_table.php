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
        Schema::create('business_days', function (Blueprint $table) {
            $table->id();
            $table->date('business_date')->unique(); // The business date (system-calculated)
            $table->date('calendar_date_start'); // Calendar date when business day started
            $table->timestamp('opened_at'); // When business day was opened
            $table->timestamp('closed_at')->nullable(); // When business day was closed (null if still open)
            $table->foreignId('opened_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('closed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_days');
    }
};

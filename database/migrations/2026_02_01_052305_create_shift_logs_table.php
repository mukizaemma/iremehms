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
        Schema::create('shift_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained('shifts')->onDelete('cascade');
            $table->date('business_date'); // Business date this shift belongs to
            $table->timestamp('opened_at'); // When shift was opened
            $table->timestamp('closed_at')->nullable(); // When shift was closed (null if still open)
            $table->foreignId('opened_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('closed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('open_type', ['auto', 'manual'])->default('auto'); // How shift was opened
            $table->enum('close_type', ['auto', 'manual'])->nullable(); // How shift was closed
            $table->decimal('opening_cash', 10, 2)->nullable(); // Cash at shift opening
            $table->decimal('closing_cash', 10, 2)->nullable(); // Cash at shift closing
            $table->text('notes')->nullable(); // Notes for manual close/reopen
            $table->boolean('is_locked')->default(false); // True when shift is closed and locked
            $table->foreignId('reopened_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reopened_at')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->timestamps();
            
            // Index for quick lookups
            $table->index(['shift_id', 'business_date']);
            $table->index(['business_date', 'is_locked']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_logs');
    }
};

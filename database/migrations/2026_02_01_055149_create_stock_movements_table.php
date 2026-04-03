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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_id')->constrained('stocks')->onDelete('cascade');
            $table->enum('movement_type', ['OPENING', 'PURCHASE', 'TRANSFER', 'WASTE', 'ADJUST', 'SALE'])->default('PURCHASE');
            $table->decimal('quantity', 10, 2); // Positive for IN, negative for OUT
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->decimal('total_value', 10, 2)->nullable();
            $table->foreignId('from_department_id')->nullable()->constrained('departments')->onDelete('set null');
            $table->foreignId('to_department_id')->nullable()->constrained('departments')->onDelete('set null');
            $table->text('reason')->nullable(); // Required for ADJUST and WASTE
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->onDelete('restrict');
            $table->date('business_date'); // From TimeAndShiftResolver
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Indexes for reporting
            $table->index(['stock_id', 'movement_type']);
            $table->index(['business_date', 'shift_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};

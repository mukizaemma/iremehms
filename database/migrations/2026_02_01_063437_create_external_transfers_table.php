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
        Schema::create('external_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('substock_id')->constrained('stocks')->onDelete('cascade'); // The substock transferring out
            $table->enum('transfer_type', ['client', 'event'])->default('client');
            $table->string('recipient_name'); // Client name or event name
            $table->text('recipient_details')->nullable(); // Address, contact, event details, etc.
            $table->json('items'); // Array of items with quantities and prices: [{"stock_id": 1, "quantity": 10, "unit_price": 5.50, "total": 55.00}, ...]
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->onDelete('restrict');
            $table->date('business_date'); // From TimeAndShiftResolver
            $table->timestamp('transfer_date');
            $table->timestamps();
            
            // Indexes
            $table->index(['substock_id', 'transfer_type']);
            $table->index(['business_date', 'shift_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_transfers');
    }
};

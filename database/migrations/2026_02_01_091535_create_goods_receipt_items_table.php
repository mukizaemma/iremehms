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
        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->bigIncrements('line_id');
            $table->unsignedBigInteger('receipt_id');
            $table->foreign('receipt_id')->references('receipt_id')->on('goods_receipts')->onDelete('cascade');
            $table->foreignId('item_id')->constrained('stocks')->onDelete('restrict');
            $table->foreignId('location_id')->constrained('stock_locations')->onDelete('restrict');
            $table->decimal('quantity_received', 10, 2); // In purchase unit
            $table->string('unit_id')->nullable(); // Unit identifier
            $table->decimal('unit_cost', 10, 2); // Actual cost
            $table->decimal('total_cost', 10, 2); // Derived: quantity_received * unit_cost
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('receipt_id');
            $table->index('item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
    }
};

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
        Schema::create('bill_of_menu_items', function (Blueprint $table) {
            $table->id('bom_line_id');
            $table->foreignId('bom_id')->constrained('bill_of_menu', 'bom_id')->onDelete('cascade');
            $table->foreignId('stock_item_id')->constrained('stocks', 'id')->onDelete('restrict');
            $table->decimal('quantity', 10, 4); // Quantity needed (can be fractional)
            $table->string('unit', 50); // Unit of measurement (e.g., 'g', 'ml', 'pcs')
            $table->boolean('is_primary')->default(false); // Primary ingredient flag
            $table->text('notes')->nullable();
            $table->integer('display_order')->default(0);
            $table->timestamps();
            
            // Indexes
            $table->index('bom_id');
            $table->index('stock_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bill_of_menu_items');
    }
};

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
        Schema::create('purchase_requisition_items', function (Blueprint $table) {
            $table->bigIncrements('line_id');
            $table->unsignedBigInteger('requisition_id');
            $table->foreign('requisition_id')->references('requisition_id')->on('purchase_requisitions')->onDelete('cascade');
            $table->foreignId('item_id')->constrained('stocks')->onDelete('restrict');
            $table->decimal('quantity_requested', 10, 2); // In purchase unit
            $table->string('unit_id')->nullable(); // Unit identifier
            $table->decimal('estimated_unit_cost', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('requisition_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_requisition_items');
    }
};

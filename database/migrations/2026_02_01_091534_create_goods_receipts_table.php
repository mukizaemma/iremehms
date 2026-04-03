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
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->bigIncrements('receipt_id');
            $table->unsignedBigInteger('requisition_id')->nullable();
            $table->foreign('requisition_id')->references('requisition_id')->on('purchase_requisitions')->onDelete('set null');
            $table->unsignedBigInteger('supplier_id');
            $table->foreign('supplier_id')->references('supplier_id')->on('suppliers')->onDelete('restrict');
            $table->foreignId('received_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('department_id')->constrained('departments')->onDelete('restrict');
            $table->date('business_date'); // System-assigned
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->onDelete('restrict');
            $table->enum('receipt_status', ['PARTIAL', 'COMPLETE'])->default('COMPLETE');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['business_date', 'shift_id']);
            $table->index(['supplier_id', 'business_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
    }
};

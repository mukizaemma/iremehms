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
        Schema::create('purchase_requisitions', function (Blueprint $table) {
            $table->bigIncrements('requisition_id');
            $table->unsignedBigInteger('supplier_id');
            $table->foreign('supplier_id')->references('supplier_id')->on('suppliers')->onDelete('restrict');
            $table->foreignId('requested_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('department_id')->constrained('departments')->onDelete('restrict');
            $table->enum('status', ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED', 'CANCELLED'])->default('DRAFT');
            $table->date('business_date'); // System-assigned
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->onDelete('restrict');
            $table->decimal('total_estimated_cost', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'business_date']);
            $table->index(['department_id', 'business_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_requisitions');
    }
};

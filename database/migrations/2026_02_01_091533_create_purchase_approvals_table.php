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
        Schema::create('purchase_approvals', function (Blueprint $table) {
            $table->bigIncrements('approval_id');
            $table->unsignedBigInteger('requisition_id')->unique();
            $table->foreign('requisition_id')->references('requisition_id')->on('purchase_requisitions')->onDelete('cascade');
            $table->foreignId('approved_by')->constrained('users')->onDelete('restrict');
            $table->timestamp('approved_at');
            $table->text('approval_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_approvals');
    }
};

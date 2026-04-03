<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('restrict');
            $table->enum('payment_method', ['CASH', 'MOMO', 'CARD', 'CREDIT']);
            $table->decimal('amount', 12, 2);
            $table->foreignId('received_by')->constrained('users')->onDelete('restrict');
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

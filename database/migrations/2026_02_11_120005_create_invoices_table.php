<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('restrict');
            $table->string('invoice_number', 50)->unique();
            $table->decimal('total_amount', 12, 2);
            $table->enum('invoice_status', ['UNPAID', 'PAID'])->default('UNPAID');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['invoice_status', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

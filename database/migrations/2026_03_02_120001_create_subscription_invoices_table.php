<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->string('invoice_number', 50)->unique();
            $table->decimal('amount', 12, 2);
            $table->date('due_date');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('status', 20)->default('draft'); // draft, sent, paid, overdue
            $table->timestamp('reminder_7d_sent_at')->nullable();
            $table->timestamp('reminder_24h_sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['hotel_id', 'due_date']);
            $table->index(['hotel_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_invoices');
    }
};

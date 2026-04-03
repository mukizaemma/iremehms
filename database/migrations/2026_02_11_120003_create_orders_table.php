<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->nullable()->constrained('restaurant_tables')->onDelete('set null');
            $table->foreignId('waiter_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('session_id')->constrained('pos_sessions')->onDelete('restrict');
            $table->enum('order_status', ['OPEN', 'CONFIRMED', 'PAID', 'CANCELLED'])->default('OPEN');
            // Optional transfer metadata: when an order is reassigned between users (e.g. waiter shift change)
            $table->foreignId('transferred_from_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('transfer_comment')->nullable();
            $table->timestamps();

            $table->index(['order_status', 'session_id']);
            $table->index(['table_id', 'order_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

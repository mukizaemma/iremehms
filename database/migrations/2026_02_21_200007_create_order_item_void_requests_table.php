<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Void workflow: waiter requests -> authorized role approves.
     * Logs: who requested, who approved, why. Stock reversal handled separately.
     */
    public function up(): void
    {
        Schema::create('order_item_void_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('requested_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->string('status', 20)->default('pending')->comment('pending, approved, rejected');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['order_item_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_void_requests');
    }
};

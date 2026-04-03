<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Log order transfers: from_user -> to_user, reason. No item loss.
     */
    public function up(): void
    {
        Schema::create('order_transfer_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_transfer_logs');
    }
};

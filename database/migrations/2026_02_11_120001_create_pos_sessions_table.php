<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_log_id')->constrained('shift_logs')->onDelete('restrict');
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->enum('status', ['OPEN', 'CLOSED'])->default('OPEN');
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['shift_log_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sessions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_request_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_request_id')->constrained('stock_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['stock_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_request_comments');
    }
};

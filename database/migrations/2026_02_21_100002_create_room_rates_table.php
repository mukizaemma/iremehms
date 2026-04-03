<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->string('rate_type', 50);
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->unique(['room_id', 'rate_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_rates');
    }
};

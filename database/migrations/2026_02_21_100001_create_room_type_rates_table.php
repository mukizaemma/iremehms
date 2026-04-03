<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_type_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained('room_types')->cascadeOnDelete();
            $table->string('rate_type', 50); // e.g. 'Locals', 'EAC', 'International'
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->unique(['room_type_id', 'rate_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_type_rates');
    }
};

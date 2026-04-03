<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('item_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // RAW, FINISHED, SEMI_FINISHED, NON_CONSUMABLE, EQUIPMENT, NOT_FOR_SALE
            $table->string('name'); // Human readable name
            $table->text('description')->nullable();
            $table->boolean('is_consumable')->default(true);
            $table->boolean('is_sellable')->default(false); // Phase 3: No sales yet
            $table->boolean('allows_waste')->default(true);
            $table->boolean('allows_transfer')->default(true);
            $table->boolean('allows_adjustment')->default(true);
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_types');
    }
};

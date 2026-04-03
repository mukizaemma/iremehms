<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('additional_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->text('description')->nullable();
            $table->decimal('default_amount', 12, 2)->nullable();
            $table->string('charge_rule', 50)->default('per_instance'); // per_night, per_person, per_booking, per_instance, per_adult, per_child, per_quantity
            $table->boolean('is_tax_inclusive')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('additional_charges');
    }
};

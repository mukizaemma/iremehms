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
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id('menu_item_id');
            $table->foreignId('category_id')->constrained('menu_categories', 'category_id')->onDelete('restrict');
            $table->foreignId('menu_item_type_id')->constrained('menu_item_types', 'type_id')->onDelete('restrict');
            $table->string('name', 255); // POS name
            $table->string('code', 50)->unique()->nullable();
            $table->text('description')->nullable();
            $table->decimal('sale_price', 10, 2);
            $table->string('currency', 3)->default('RWF');
            $table->string('sale_unit', 50); // e.g., 'pcs', 'plate', 'serving'
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);
            $table->string('image')->nullable(); // Menu item image
            $table->timestamps();
            
            // Indexes
            $table->index(['category_id', 'is_active']);
            $table->index('menu_item_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};

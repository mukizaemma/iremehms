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
        Schema::create('bill_of_menu', function (Blueprint $table) {
            $table->id('bom_id');
            $table->foreignId('menu_item_id')->constrained('menu_items', 'menu_item_id')->onDelete('cascade');
            $table->integer('version')->default(1);
            $table->boolean('is_active')->default(false);
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Note: Unique constraint for active BoM handled in application logic
            // Multiple active BoMs can exist during transition, but only one should be active at a time
            
            // Indexes
            $table->index(['menu_item_id', 'version']);
            $table->index(['menu_item_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bill_of_menu');
    }
};

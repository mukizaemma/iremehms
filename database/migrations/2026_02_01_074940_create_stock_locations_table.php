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
        Schema::create('stock_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Main Stock, Kitchen, Bar, etc.
            $table->string('code')->nullable()->unique();
            $table->text('description')->nullable();
            $table->foreignId('parent_location_id')->nullable()->constrained('stock_locations')->onDelete('cascade');
            $table->boolean('is_main_location')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Indexes
            $table->index('parent_location_id');
            $table->index('is_main_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_locations');
    }
};

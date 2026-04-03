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
        Schema::create('menu_item_types', function (Blueprint $table) {
            $table->id('type_id');
            $table->string('code', 50)->unique(); // FINISHED_GOOD, PREPARED_ITEM, SERVICE, EQUIPMENT
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('requires_bom')->default(false);
            $table->boolean('allows_bom')->default(true);
            $table->boolean('affects_stock')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_item_types');
    }
};

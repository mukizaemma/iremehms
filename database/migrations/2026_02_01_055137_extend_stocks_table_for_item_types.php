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
        Schema::table('stocks', function (Blueprint $table) {
            $table->foreignId('item_type_id')->nullable()->after('department_id')->constrained('item_types')->onDelete('restrict');
            $table->boolean('is_sellable')->nullable()->after('item_type_id'); // Can override item type default
            $table->boolean('is_consumable')->nullable()->after('is_sellable'); // Can override item type default
            $table->enum('tracking_method', ['quantity', 'serial', 'batch'])->default('quantity')->after('is_consumable');
            $table->integer('expected_lifespan')->nullable()->after('tracking_method'); // For equipment (in days)
            $table->decimal('reorder_level', 10, 2)->nullable()->after('expected_lifespan');
            $table->decimal('reorder_quantity', 10, 2)->nullable()->after('reorder_level');
            $table->string('location')->nullable()->after('reorder_quantity'); // Storage location
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropForeign(['item_type_id']);
            $table->dropColumn([
                'item_type_id',
                'is_sellable',
                'is_consumable',
                'tracking_method',
                'expected_lifespan',
                'reorder_level',
                'reorder_quantity',
                'location',
            ]);
        });
    }
};

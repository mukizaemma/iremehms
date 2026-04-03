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
            $table->foreignId('parent_stock_id')->nullable()->after('department_id')->constrained('stocks')->onDelete('cascade');
            $table->boolean('is_main_stock')->default(true)->after('parent_stock_id');
            $table->string('substock_name')->nullable()->after('is_main_stock'); // Name for this substock location
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropForeign(['parent_stock_id']);
            $table->dropColumn(['parent_stock_id', 'is_main_stock', 'substock_name']);
        });
    }
};

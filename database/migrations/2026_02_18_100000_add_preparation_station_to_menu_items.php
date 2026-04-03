<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Preparation station: where the item is prepared (Kitchen, Bar, Coffee Station, etc.)
     */
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('preparation_station', 50)->nullable()->after('display_order');
            $table->index('preparation_station');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropIndex(['preparation_station']);
            $table->dropColumn('preparation_station');
        });
    }
};

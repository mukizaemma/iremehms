<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->boolean('use_bom_for_menu_items')->default(true)->after('order_slip_hide_price')
                ->comment('When true, hotel uses Bill of Menu for menu items; when false, manager can set price directly without BoM.');
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn('use_bom_for_menu_items');
        });
    }
};

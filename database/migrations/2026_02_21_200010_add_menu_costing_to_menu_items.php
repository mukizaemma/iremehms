<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menu cost = ingredient cost (from BoM) + cost_extra. Selling price = menu_cost + margin.
     * Configurable per menu item for profit analysis and controller audits.
     */
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->decimal('menu_cost', 12, 2)->nullable()->after('sale_price')->comment('Ingredient cost + cost_extra');
            $table->decimal('cost_extra', 12, 2)->default(0)->after('menu_cost')->comment('Transport, tax, overheads');
            $table->decimal('margin_percent', 8, 2)->nullable()->after('cost_extra')->comment('Margin % for selling price');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn(['menu_cost', 'cost_extra', 'margin_percent']);
        });
    }
};

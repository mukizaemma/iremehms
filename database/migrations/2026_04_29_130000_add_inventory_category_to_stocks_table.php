<?php

use App\Models\Stock;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->string('inventory_category', 40)->default('dry_goods')->after('item_type_id');
            $table->index('inventory_category');
        });

        $map = [
            'ASSETS' => 'assets',
            'EXPENSES' => 'non_food_materials',
            'FINISHED_PRODUCT' => 'dry_goods',
            'RAW_MATERIAL' => 'dry_goods',
            'SERVICE' => 'dry_goods',
        ];

        Stock::query()->with('itemType')->chunkById(200, function ($stocks) use ($map) {
            foreach ($stocks as $stock) {
                $code = $stock->itemType?->code;
                $stock->inventory_category = $map[$code] ?? 'dry_goods';
                $stock->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropIndex('stocks_inventory_category_index');
            $table->dropColumn('inventory_category');
        });
    }
};

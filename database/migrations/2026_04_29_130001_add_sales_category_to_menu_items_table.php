<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('sales_category', 20)->default('food')->after('menu_item_type_id');
            $table->index('sales_category');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropIndex(['sales_category']);
            $table->dropColumn('sales_category');
        });
    }
};

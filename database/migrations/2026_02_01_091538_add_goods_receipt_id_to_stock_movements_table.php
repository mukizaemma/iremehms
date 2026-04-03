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
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('goods_receipt_item_id')->nullable()->after('stock_id');
            $table->foreign('goods_receipt_item_id')->references('line_id')->on('goods_receipt_items')->onDelete('set null');
            
            $table->index('goods_receipt_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['goods_receipt_item_id']);
            $table->dropIndex(['goods_receipt_item_id']);
            $table->dropColumn('goods_receipt_item_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->unsignedBigInteger('purchase_requisition_item_id')->nullable()->after('notes');
            $table->foreign('purchase_requisition_item_id')->references('line_id')->on('purchase_requisition_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->dropForeign(['purchase_requisition_item_id']);
        });
    }
};

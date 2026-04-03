<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_request_items', function (Blueprint $table) {
            $table->decimal('quantity_issued', 16, 4)->default(0)->after('quantity');
            $table->string('issue_status', 30)->default('pending')->after('quantity_issued'); // pending, partial, issued, on_requisition
            $table->unsignedBigInteger('purchase_requisition_item_id')->nullable()->after('edit_data');
            $table->foreign('purchase_requisition_item_id')->references('line_id')->on('purchase_requisition_items')->nullOnDelete();
        });

        Schema::table('purchase_requisition_items', function (Blueprint $table) {
            $table->foreignId('stock_request_item_id')->nullable()->after('notes')->constrained('stock_request_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requisition_items', function (Blueprint $table) {
            $table->dropForeign(['stock_request_item_id']);
        });
        Schema::table('stock_request_items', function (Blueprint $table) {
            $table->dropForeign(['purchase_requisition_item_id']);
            $table->dropColumn(['quantity_issued', 'issue_status', 'purchase_requisition_item_id']);
        });
    }
};
